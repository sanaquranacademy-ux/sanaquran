<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // 1. ایڈمن کے لیے ڈائریکٹ محفوظ لاگ ان
    if ($username === 'admin' && $password === '987hafsa') {
        $user_res = $conn->query("SELECT id, name FROM users WHERE role = 'admin' LIMIT 1");
        $user = $user_res ? $user_res->fetch_assoc() : null;

        $_SESSION['user_id'] = $user ? (int)$user['id'] : 1;
        $_SESSION['user_name'] = $user['name'] ?? 'Administrator';
        $_SESSION['username'] = 'admin';
        $_SESSION['role'] = 'admin';

        header("Location: admin_dashboard.php");
        exit;
    }

    // 2. دیگر اساتذہ اور اسٹاف کے لیے لاگ ان
    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, name, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $db_pass = $user['password'];

            if (password_verify($password, $db_pass) || $db_pass === md5($password) || $db_pass === $password) {
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'] ?? $user['username'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] === 'teacher') {
                    header("Location: teacher_dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $error = "Invalid password. Please try again.";
            }
        } else {
            $error = "Username not found in system.";
        }
    } else {
        $error = "Please enter both username and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Login - Sana Quran House</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body {
            background: #eef6f2;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 400px;
            padding: 42px 36px;
            border-radius: 24px;
            box-shadow: 0 14px 35px rgba(0, 0, 0, 0.05);
            text-align: center;
            border: 1px solid #e2ece6;
        }
        .icon-box {
            width: 65px;
            height: 65px;
            background: #ecfdf5;
            color: #059669;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 18px auto;
            border: 1px solid #d1fae5;
        }
        h2 {
            color: #1e293b;
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }
        .sub-title {
            color: #059669;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 28px;
        }
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 7px;
        }
        input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #d1e3d9;
            border-radius: 12px;
            font-size: 14px;
            outline: none;
            background: #fcfdfd;
            color: #1e293b;
            transition: all 0.2s ease;
        }
        input:focus {
            border-color: #10b981;
            background: #ffffff;
            box-shadow: 0 0 0 3.5px rgba(16, 185, 129, 0.12);
        }
        .btn-submit {
            width: 100%;
            background: #10b981;
            color: white;
            border: none;
            padding: 13px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14.5px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 10px;
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.25);
        }
        .btn-submit:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3);
        }
        .alert-box {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid #fca5a5;
        }
        .back-link {
            display: inline-block;
            margin-top: 22px;
            font-size: 13px;
            color: #64748b;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }
        .back-link:hover {
            color: #059669;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="icon-box">📖</div>
    <h2>Portal Login</h2>
    <p class="sub-title">Sana Online Quran House</p>

    <?php if (!empty($error)): ?>
        <div class="alert-box"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="admin" value="admin" required autofocus>
        </div>
        
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-submit">Login to Dashboard →</button>
    </form>

    <a href="index.php" class="back-link">← Return to Main Website</a>
</div>

</body>
</html>