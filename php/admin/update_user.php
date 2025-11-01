<?php
session_start();
include '../../php/db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    header("Location: profile.php");
    exit();
}

$id = intval($_POST['id'] ?? 0);
$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$role = $_POST['role'] ?? 'user';

if ($id === 0 || $full_name === '' || $username === '') {
    $_SESSION['error'] = "Invalid input.";
    header("Location: profile.php");
    exit();
}

// Check username uniqueness (exclude current user)
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->bind_param("si", $username, $id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    $_SESSION['error'] = "Username already taken.";
    header("Location: profile.php");
    exit();
}
$stmt->close();

// Update user data
if ($password !== '') {
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, password = ?, role = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $full_name, $username, $password, $role, $id);
} else {
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, role = ? WHERE id = ?");
    $stmt->bind_param("sssi", $full_name, $username, $role, $id);
}

if ($stmt->execute()) {
    $_SESSION['success'] = "User updated successfully.";
} else {
    $_SESSION['error'] = "Failed to update user.";
}
$stmt->close();
$conn->close();

header("Location: profile.php");
exit();
