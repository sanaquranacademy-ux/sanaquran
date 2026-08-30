<?php
require_once 'db.php';

$new_password = "admin";
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->bind_param("s", $hashed_password);

if ($stmt->execute()) {
    echo "<h2 style='color: green; text-align: center; margin-top: 50px;'>Password successfully updated to: admin</h2>";
    echo "<p style='text-align: center;'><a href='login.php'>Go to Login Page</a></p>";
} else {
    echo "Error updating password: " . $conn->error;
}
?>