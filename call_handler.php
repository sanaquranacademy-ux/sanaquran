<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 1. کال شروع کرنا (ایڈمن یا ٹیچر کوئی بھی ملائے)
if ($action === 'start_call') {
    $caller_name = $_SESSION['user_name'] ?? 'User';
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);

    if ($receiver_id <= 0) {
        $adm = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc();
        $receiver_id = (int)($adm['id'] ?? 1);
    }

    // پچھلی تمام پرانی کالز ہٹائیں
    $conn->query("DELETE FROM live_calls WHERE caller_id = $user_id OR receiver_id = $user_id OR caller_id = $receiver_id OR receiver_id = $receiver_id");
    
    $stmt = $conn->prepare("INSERT INTO live_calls (caller_id, caller_name, receiver_id, status) VALUES (?, ?, ?, 'calling')");
    $stmt->bind_param("isi", $user_id, $caller_name, $receiver_id);
    $stmt->execute();
    $call_id = $stmt->insert_id;
    
    echo json_encode(['status' => 'calling', 'call_id' => $call_id, 'receiver_id' => $receiver_id]);
    exit;
}

// 2. انکمنگ کال چیک کرنا (ہر یوزر کے لیے اپنے اکاؤنٹ کی کال)
if ($action === 'check_incoming') {
    $res = $conn->query("SELECT * FROM live_calls WHERE receiver_id = $user_id AND status = 'calling' ORDER BY id DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $call = $res->fetch_assoc();
        echo json_encode([
            'status' => 'incoming',
            'call_id' => (int)$call['id'],
            'caller_id' => (int)$call['caller_id'],
            'caller_name' => $call['caller_name']
        ]);
    } else {
        echo json_encode(['status' => 'idle']);
    }
    exit;
}

// 3. کال اسٹیٹس چیک کرنا (دونوں یوزرز کے درمیان)
if ($action === 'check_call_status') {
    $target_id = (int)($_GET['target_id'] ?? 0);
    $res = $conn->query("SELECT status FROM live_calls WHERE (caller_id = $user_id AND receiver_id = $target_id) OR (caller_id = $target_id AND receiver_id = $user_id) ORDER BY id DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo json_encode(['status' => $row['status']]);
    } else {
        echo json_encode(['status' => 'calling']);
    }
    exit;
}

// 4. کال قبول کرنا یا مسترد کرنا
if ($action === 'respond_call') {
    $call_id = (int)$_POST['call_id'];
    $response = $_POST['response']; // 'accepted' یا 'rejected'
    
    $call_info = $conn->query("SELECT * FROM live_calls WHERE id = $call_id")->fetch_assoc();
    if ($call_info) {
        $caller_id = (int)$call_info['caller_id'];
        $caller_name = $call_info['caller_name'];
        $my_name = $_SESSION['user_name'] ?? 'User';

        if ($response === 'rejected') {
            $stmt = $conn->prepare("INSERT INTO call_logs (caller_id, caller_name, receiver_id, receiver_name, status, duration_seconds) VALUES (?, ?, ?, ?, 'declined', 0)");
            $stmt->bind_param("isis", $caller_id, $caller_name, $user_id, $my_name);
            $stmt->execute();

            $msg = "❌ Call Declined / Missed";
            $chat_stmt = $conn->prepare("INSERT INTO admin_teacher_chat (sender_id, receiver_id, message_type, message_text) VALUES (?, ?, 'alert', ?)");
            $chat_stmt->bind_param("iis", $user_id, $caller_id, $msg);
            $chat_stmt->execute();
        }
    }

    $conn->query("UPDATE live_calls SET status = '$response' WHERE id = $call_id");
    echo json_encode(['status' => 'updated']);
    exit;
}

// 5. لائیو کال ختم کرنا
if ($action === 'end_active_call') {
    $target_id = (int)($_POST['target_id'] ?? 0);
    $duration = (int)($_POST['duration'] ?? 0);

    $t_user = $conn->query("SELECT name FROM users WHERE id = $target_id")->fetch_assoc();
    $target_name = $t_user['name'] ?? 'User';
    $my_name = $_SESSION['user_name'] ?? 'User';

    if ($duration > 0) {
        $stmt = $conn->prepare("INSERT INTO call_logs (caller_id, caller_name, receiver_id, receiver_name, status, duration_seconds) VALUES (?, ?, ?, ?, 'answered', ?)");
        $stmt->bind_param("isisi", $user_id, $my_name, $target_id, $target_name, $duration);
        $stmt->execute();
        
        $mins = floor($duration / 60);
        $secs = $duration % 60;
        $dur_str = sprintf("%02d:%02d", $mins, $secs);
        
        $msg = "📞 Audio Call Ended (Duration: $dur_str)";
        $chat_stmt = $conn->prepare("INSERT INTO admin_teacher_chat (sender_id, receiver_id, message_type, message_text) VALUES (?, ?, 'alert', ?)");
        $chat_stmt->bind_param("iis", $user_id, $target_id, $msg);
        $chat_stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO call_logs (caller_id, caller_name, receiver_id, receiver_name, status, duration_seconds) VALUES (?, ?, ?, ?, 'missed', 0)");
        $stmt->bind_param("isis", $user_id, $my_name, $target_id, $target_name);
        $stmt->execute();

        $msg = "⚠️ Missed Call from $my_name";
        $chat_stmt = $conn->prepare("INSERT INTO admin_teacher_chat (sender_id, receiver_id, message_type, message_text) VALUES (?, ?, 'alert', ?)");
        $chat_stmt->bind_param("iis", $user_id, $target_id, $msg);
        $chat_stmt->execute();
    }

    $conn->query("UPDATE live_calls SET status = 'ended' WHERE (caller_id = $user_id AND receiver_id = $target_id) OR (caller_id = $target_id AND receiver_id = $user_id)");
    echo json_encode(['status' => 'ended']);
    exit;
}