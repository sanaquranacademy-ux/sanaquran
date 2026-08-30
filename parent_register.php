<?php
session_start();
require_once 'db.php';

$msg = '';
$err = '';
$step = 1; // Step 1: نمبر چیک کرنا | Step 2: پن سیٹ کرنا
$verified_phone = '';

// Step 1: نمبر کی تصدیق
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'verify_phone') {
    $phone = trim($_POST['phone']);
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);

    if (!empty($clean_phone)) {
        // چیک کریں کہ کیا یہ نمبر اکیڈمی ریکارڈ میں موجود ہے
        $stmt = $conn->prepare("SELECT id, name, guardian_name FROM students WHERE phone LIKE ?");
        $search = "%" . $clean_phone . "%";
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $step = 2;
            $verified_phone = $clean_phone;
            $msg = "Registered number verified! Now please create your Secret PIN.";
        } else {
            $err = "This WhatsApp number is not registered in our Academy! Please contact Admin first.";
        }
    } else {
        $err = "Please enter a valid WhatsApp number.";
    }
}

// Step 2: نیا پن کوڈ محفوظ کرنا
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'set_pin') {
    $phone = trim($_POST['phone']);
    $pin = trim($_POST['pin']);
    $confirm_pin = trim($_POST['confirm_pin']);

    if (empty($pin) || strlen($pin) < 4) {
        $err = "PIN must be at least 4 digits/characters long.";
        $step = 2;
        $verified_phone = $phone;
    } elseif ($pin !== $confirm_pin) {
        $err = "Both PINs do not match. Please re-enter carefully.";
        $step = 2;
        $verified_phone = $phone;
    } else {
        // اس نمبر سے وابستہ تمام بچوں کا پن اپڈیٹ کر دیں
        $stmt = $conn->prepare("UPDATE students SET parent_password = ? WHERE phone LIKE ?");
        $search = "%" . $phone . "%";
        $stmt->bind_param("ss", $pin, $search);

        if ($stmt->execute()) {
            $msg = "Your Secret PIN has been created successfully! You can now login.";
            $step = 3; // کامیابی کا مرحلہ
        } else {
            $err = "Error updating PIN. Please try again.";
            $step = 2;
            $verified_phone = $phone;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set / Create Parent PIN - Sana Online Quran House</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #0a0d0b; color: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card-box { background: #18221a; padding: 35px 30px; border-radius: 12px; border: 1px solid #283e2c; width: 100%; max-width: 420px; box-shadow: 0 10px 30px rgba(0,0,0,0.6); text-align: center; }
        .logo-img { height: 70px; margin-bottom: 15px; }
        h2 { font-size: 22px; color: #5cb85c; margin-bottom: 5px; }
        p { font-size: 13px; color: #a0aab0; margin-bottom: 25px; }
        .form-group { margin-bottom: 16px; text-align: left; }
        .form-group label { display: block; font-size: 12px; margin-bottom: 5px; color: #ccc; }
        .form-group input { width: 100%; padding: 12px; background: #0c120e; border: 1px solid #2b3f2f; color: white; border-radius: 6px; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #5cb85c; }
        .btn-action { width: 100%; background: #5cb85c; color: #000; border: none; padding: 12px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 14px; margin-top: 5px; }
        .btn-action:hover { background: #4cae4c; }
        .alert-danger { background: rgba(217, 83, 79, 0.2); border: 1px solid #d9534f; color: #ff8e8b; padding: 10px; border-radius: 6px; font-size: 12px; margin-bottom: 15px; }
        .alert-success { background: rgba(92, 184, 92, 0.2); border: 1px solid #5cb85c; color: #7fe67f; padding: 10px; border-radius: 6px; font-size: 12px; margin-bottom: 15px; }
        .back-link { margin-top: 15px; display: block; font-size: 12px; color: #a0aab0; text-decoration: none; }
        .back-link:hover { color: #5cb85c; }
    </style>
</head>
<body>

<div class="card-box">
    <img src="logo.jpg" alt="Logo" class="logo-img" onerror="this.src='logo.png'">
    <h2>Parent PIN Setup</h2>
    <p>Set or Reset your Parent Portal PIN code</p>

    <?php if ($err): ?><div class="alert-danger"><?php echo $err; ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert-success"><?php echo $msg; ?></div><?php endif; ?>

    <!-- Step 1: فون نمبر ویریفکیشن -->
    <?php if ($step === 1): ?>
        <form method="POST">
            <input type="hidden" name="action" value="verify_phone">
            <div class="form-group">
                <label>Registered WhatsApp Number</label>
                <input type="text" name="phone" placeholder="e.g. 971527194855 / 03001234567" required autofocus>
                <small style="font-size: 11px; color:#888;">(Must already be enrolled in academy)</small>
            </div>
            <button type="submit" class="btn-action">Verify WhatsApp Number →</button>
        </form>

    <!-- Step 2: نیا پن کوڈ سیٹ کرنا -->
    <?php elseif ($step === 2): ?>
        <form method="POST">
            <input type="hidden" name="action" value="set_pin">
            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($verified_phone); ?>">
            
            <div class="form-group">
                <label>Verified Number</label>
                <input type="text" value="<?php echo htmlspecialchars($verified_phone); ?>" disabled style="background:#222; color:#888;">
            </div>
            <div class="form-group">
                <label>Create Secret PIN (e.g. 1234 or Password)</label>
                <input type="password" name="pin" placeholder="Enter new PIN" required autofocus>
            </div>
            <div class="form-group">
                <label>Confirm Secret PIN</label>
                <input type="password" name="confirm_pin" placeholder="Re-type PIN" required>
            </div>
            <button type="submit" class="btn-action">Save My Secret PIN</button>
        </form>

    <!-- Step 3: لاگ ان بٹن -->
    <?php elseif ($step === 3): ?>
        <a href="parent_login.php" class="btn-action" style="display:block; text-decoration:none;">Go to Parent Login →</a>
    <?php endif; ?>

    <a href="parent_login.php" class="back-link">← Back to Parent Login</a>
</div>

</body>
</html>