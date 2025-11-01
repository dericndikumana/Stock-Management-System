<?php
session_start(); // Required to access $_SESSION

include '../../php/db_connect.php';

// Show all errors for debugging - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user logged in
if (!isset($_SESSION['user_id'])) {
    die('Access denied. Please log in.');
}

$logged_user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Determine target user_id:
// For admin, check if a user_id is passed via POST (from form hidden input), else fallback to logged user
if ($role === 'admin' && !empty($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
} else {
    $user_id = $logged_user_id;
}

// Get action
$action = $_POST['action'] ?? '';

if (!$user_id) {
    die('User ID is missing.');
}

if ($action === 'add') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($item_id <= 0 || $quantity <= 0) {
        die('Invalid item or quantity.');
    }

    $stmt = $conn->prepare("INSERT INTO stock_transactions (user_id, item_id, transaction_type, quantity, note, created_at, deleted) VALUES (?, ?, 'in', ?, ?, NOW(), 0)");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    if (!$stmt->bind_param("iiis", $user_id, $item_id, $quantity, $note)) {
        die("Bind param failed: " . $stmt->error);
    }
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    $stmt->close();

    header("Location: stock_in.php?success=1");
    exit();

} elseif ($action === 'edit') {
    $id = intval($_POST['id'] ?? 0);
    $item_id = intval($_POST['item_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($id <= 0 || $item_id <= 0 || $quantity <= 0) {
        die('Missing or invalid data for update.');
    }

    // Update only the record that belongs to the current user (or selected admin user)
    $stmt = $conn->prepare("UPDATE stock_transactions SET item_id = ?, quantity = ?, note = ? WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    if (!$stmt->bind_param("iisii", $item_id, $quantity, $note, $id, $user_id)) {
        die("Bind param failed: " . $stmt->error);
    }
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    $stmt->close();

    header("Location: stock_in.php?updated=1");
    exit();

} elseif ($action === 'soft_delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        die('Invalid ID for deletion.');
    }

    $stmt = $conn->prepare("UPDATE stock_transactions SET deleted = 1 WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    if (!$stmt->bind_param("ii", $id, $user_id)) {
        die("Bind param failed: " . $stmt->error);
    }
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    $stmt->close();

    header("Location: stock_in.php?deleted=1");
    exit();

} else {
    die('Unknown action.');
}
