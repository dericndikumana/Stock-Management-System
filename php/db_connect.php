<?php
$host = 'localhost';
$username = 'root';
$password = ''; // or your real password
$database = 'stock_management'; // ensure this is correct

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
