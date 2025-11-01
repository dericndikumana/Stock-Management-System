<?php
session_start();
include '../../php/db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$user_id = intval($_POST['user_id'] ?? 0);
$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$role = $_POST['role'] ?? 'user';

if ($user_id <= 0 || $full_name === '' || $username === '') {
    $_SESSION['error'] = "Invalid data.";
    header("Location: profile.php");
    exit();
}

// Check if username is taken by other user
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->bind_param("si", $username, $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $_SESSION['error'] = "Username already taken by another user.";
    $stmt->close();
    header("Location: profile.php");
    exit();
}
$stmt->close();

if ($password !== '') {
    // Update with new password
    $sql = "UPDATE users SET full_name = ?, username = ?, password = ?, role = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $full_name, $username, $password, $role, $user_id);
} else {
    // Update without password
    $sql = "UPDATE users SET full_name = ?, username = ?, role = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $full_name, $username, $role, $user_id);
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
