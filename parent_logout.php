<?php
session_start();
unset($_SESSION['parent_phone']);
unset($_SESSION['parent_name']);
header("Location: parent_login.php");
exit;
?>