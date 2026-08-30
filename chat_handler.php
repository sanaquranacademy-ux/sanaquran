<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 1. پیغامات فارمیٹ شدہ وقت کے ساتھ حاصل کرنا
if ($action === 'fetch_messages') {
    $other_id = (int)($_GET['other_id'] ?? 0);
    
    $stmt = $conn->prepare("
        SELECT c.*, u.name as sender_name,
               DATE_FORMAT(c.created_at, '%h:%i %p') as msg_time,
               DATE_FORMAT(c.created_at, '%d %b') as msg_date
        FROM admin_teacher_chat c 
        JOIN users u ON c.sender_id = u.id 
        WHERE (c.sender_id = ? AND c.receiver_id = ?) 
           OR (c.sender_id = ? AND c.receiver_id = ?) 
        ORDER BY c.created_at ASC
    ");
    $stmt->bind_param("iiii", $user_id, $other_id, $other_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    echo json_encode(['status' => 'success', 'messages' => $messages, 'my_id' => $user_id]);
    exit;
}

// 2. ٹیکسٹ یا کوئیک الرٹ بھیجنا
if ($action === 'send_text') {
    $receiver_id = (int)$_POST['receiver_id'];
    $message = trim($_POST['message']);
    $type = $_POST['type'] ?? 'text';

    if (!empty($message) && $receiver_id > 0) {
        $stmt = $conn->prepare("INSERT INTO admin_teacher_chat (sender_id, receiver_id, message_type, message_text) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $user_id, $receiver_id, $type, $message);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        }
    }
    exit;
}

// 3. وائس میسج محفوظ کرنا
if ($action === 'send_voice' && isset($_FILES['voice_data'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $dir = 'uploads/voice_notes/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $filename = 'voice_' . time() . '_' . rand(1000, 9999) . '.webm';
    $filepath = $dir . $filename;

    if (move_uploaded_file($_FILES['voice_data']['tmp_name'], $filepath)) {
        $stmt = $conn->prepare("INSERT INTO admin_teacher_chat (sender_id, receiver_id, message_type, voice_file) VALUES (?, ?, 'voice', ?)");
        $stmt->bind_param("iis", $user_id, $receiver_id, $filename);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    }
    exit;
}