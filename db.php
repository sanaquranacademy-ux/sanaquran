<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "quran_academy";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("ڈیٹا بیس سے رابطہ نہیں ہو سکا: " . $conn->connect_error);
}
?>