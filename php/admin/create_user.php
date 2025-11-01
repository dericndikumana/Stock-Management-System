<?php
session_start();
include '../../php/db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    header("Location: profile.php");
    exit();
}

$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$role = $_POST['role'] ?? 'user';

if ($full_name === '' || $username === '' || $password === '') {
    $_SESSION['error'] = "All fields are required.";
    header("Location: profile.php");
    exit();
}

// Check username uniqueness
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    $_SESSION['error'] = "Username already exists.";
    header("Location: profile.php");
    exit();
}
$stmt->close();

// Insert new user (consider hashing password in production!)
$stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $full_name, $username, $password, $role);
if ($stmt->execute()) {
    $_SESSION['success'] = "User created successfully.";
} else {
    $_SESSION['error'] = "Failed to create user.";
}
$stmt->close();
$conn->close();

header("Location: profile.php");
exit();
