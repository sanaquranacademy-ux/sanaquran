<?php
session_start();
require_once 'db.php';

$err = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);

    if (!empty($clean_phone) && !empty($password)) {
        $stmt = $conn->prepare("SELECT DISTINCT guardian_name, phone FROM students WHERE phone LIKE ? AND parent_password = ?");
        $search = "%" . $clean_phone . "%";
        $stmt->bind_param("ss", $search, $password);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $parent = $res->fetch_assoc();
            $_SESSION['parent_phone'] = $clean_phone;
            $_SESSION['parent_name'] = $parent['guardian_name'] ?: 'Respected Parent';
            header("Location: parent_dashboard.php");
            exit;
        } else {
            $err = "Invalid WhatsApp number or PIN! Please contact academy admin.";
        }
    } else {
        $err = "Please enter both WhatsApp number and PIN.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Portal Login - Sana Online Quran House</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #0a0d0b; color: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-card { background: #18221a; padding: 35px 30px; border-radius: 12px; border: 1px solid #283e2c; width: 100%; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.6); text-align: center; }
        .logo-img { height: 70px; margin-bottom: 15px; }
        h2 { font-size: 22px; color: #5cb85c; margin-bottom: 5px; }
        p { font-size: 13px; color: #a0aab0; margin-bottom: 25px; }
        .form-group { margin-bottom: 16px; text-align: left; }
        .form-group label { display: block; font-size: 12px; margin-bottom: 5px; color: #ccc; }
        .form-group input { width: 100%; padding: 12px; background: #0c120e; border: 1px solid #2b3f2f; color: white; border-radius: 6px; font-size: 14px; outline: none; }
        .form-group input:focus { border-color: #5cb85c; }
        .btn-login { width: 100%; background: #5cb85c; color: #000; border: none; padding: 12px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 14px; margin-top: 5px; }
        .btn-login:hover { background: #4cae4c; }
        .alert-danger { background: rgba(217, 83, 79, 0.2); border: 1px solid #d9534f; color: #ff8e8b; padding: 10px; border-radius: 6px; font-size: 12px; margin-bottom: 15px; }
        
        .back-link { margin-top: 22px; display: block; font-size: 12.5px; color: #a0aab0; text-decoration: none; }
        .back-link:hover { color: #5cb85c; }
    </style>
</head>
<body>

<div class="login-card">
    <img src="logo.jpg" alt="Logo" class="logo-img" onerror="this.src='logo.png'">
    <h2>Parent Secure Login</h2>
    <p>Enter your WhatsApp Number and Secret PIN</p>

    <?php if ($err): ?><div class="alert-danger"><?php echo $err; ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Registered WhatsApp Phone</label>
            <input type="text" name="phone" placeholder="e.g. 971527194855" required autofocus>
        </div>
        <div class="form-group">
            <label>Parent Portal Secret PIN</label>
            <input type="password" name="password" placeholder="Enter your PIN" required>
        </div>
        <button type="submit" class="btn-login">Login to Portal →</button>
    </form>

    <a href="index.php" class="back-link">← Back to Main Website</a>
</div>

</body>
</html>