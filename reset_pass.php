<?php
require_once 'db.php';

$username = 'admin';
$new_plain_password = '987hafsa';

// PHP کا آفیشل اور 100٪ پرفیکٹ ہیش جنریٹر
$hashed_password = password_hash($new_plain_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $hashed_password, $username);

if ($stmt->execute()) {
    echo "<h2 style='color:green; font-family:sans-serif;'>✅ Password successfully updated for user: <u>$username</u>!</h2>";
    echo "<p style='font-family:sans-serif;'>New Password: <b>$new_plain_password</b></p>";
    echo "<a href='login.php' style='display:inline-block; padding:10px 20px; background:#10b981; color:white; text-decoration:none; border-radius:6px;'>Go to Login Page →</a>";
} else {
    echo "Error updating password: " . $conn->error;
}
?>