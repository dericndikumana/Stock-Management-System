<?php
session_start();
include '../../php/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$user_id_to_delete = intval($_POST['id'] ?? 0);

if ($user_id_to_delete <= 0) {
    $_SESSION['error'] = "Invalid user ID.";
    header("Location: profile.php");
    exit();
}

// Prevent admin deleting themselves accidentally (optional)
if ($user_id_to_delete == $_SESSION['user_id']) {
    $_SESSION['error'] = "You cannot delete your own account.";
    header("Location: profile.php");
    exit();
}

// Delete all stock transactions related to user
$stmt = $conn->prepare("DELETE FROM stock_transactions WHERE user_id = ?");
$stmt->bind_param("i", $user_id_to_delete);
$stmt->execute();
$stmt->close();

// Now delete the user
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id_to_delete);

if ($stmt->execute()) {
    $_SESSION['success'] = "User deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete user. Please try again.";
}

$stmt->close();
$conn->close();

header("Location: profile.php");
exit();
