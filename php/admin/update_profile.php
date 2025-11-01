<?php
session_start();
include '../../php/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

// Basic validation
if ($full_name === '' || $username === '') {
    $_SESSION['error'] = "Full Name and Username cannot be empty.";
    header("Location: profile.php");
    exit();
}

// Check if username is taken by another user
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->bind_param("si", $username, $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    $_SESSION['error'] = "Username is already taken.";
    header("Location: profile.php");
    exit();
}
$stmt->close();

// Build SQL query and params dynamically
if ($password !== '') {
    // Update password too
    $sql = "UPDATE users SET full_name = ?, username = ?, password = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $full_name, $username, $password, $user_id);
} else {
    // Update without password
    $sql = "UPDATE users SET full_name = ?, username = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $full_name, $username, $user_id);
}

if ($stmt->execute()) {
    $_SESSION['success'] = "Profile updated successfully.";
} else {
    $_SESSION['error'] = "Failed to update profile. Please try again.";
}

$stmt->close();
$conn->close();

header("Location: profile.php");
exit();
