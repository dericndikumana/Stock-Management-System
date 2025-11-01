<?php
session_start();
include '../../php/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "Access denied. Please log in.";
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Sanitize function
function sanitize($data) {
    return htmlspecialchars(trim($data));
}

// Determine user_id (admin can specify)
$user_id = $logged_in_user_id;
if ($role === 'admin' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
}

$action = $_POST['action'] ?? '';

// Add new stock out
if ($action === 'add') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $note = sanitize($_POST['note'] ?? '');

    if ($item_id <= 0 || $quantity <= 0) {
        echo "Invalid item or quantity.";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO stock_transactions (user_id, item_id, transaction_type, quantity, note, created_at, deleted) VALUES (?, ?, 'out', ?, ?, NOW(), 0)");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("iiis", $user_id, $item_id, $quantity, $note);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: stock_out.php" . ($role === 'admin' ? "?selected_user_id=$user_id" : ""));
        exit();
    } else {
        echo "Database error: " . $stmt->error;
    }
    $stmt->close();

// Soft delete a single record
} elseif ($action === 'soft_delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo "Invalid record ID.";
        exit();
    }

    $stmt = $conn->prepare("UPDATE stock_transactions SET deleted = 1 WHERE id = ? AND transaction_type = 'out'");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: stock_out.php" . ($role === 'admin' ? "?selected_user_id=$user_id" : ""));
        exit();
    } else {
        echo "Database error: " . $stmt->error;
    }
    $stmt->close();

// ✅ Soft delete a full group (admin only)
} elseif ($action === 'soft_delete_group' && $role === 'admin') {
    $created_at = $_POST['created_at'] ?? '';
    if (!$created_at || !$user_id) {
        echo "Invalid group delete request.";
        exit();
    }

    $stmt = $conn->prepare("UPDATE stock_transactions SET deleted = 1 WHERE user_id = ? AND created_at = ? AND transaction_type = 'out'");
    $stmt->bind_param("is", $user_id, $created_at);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: stock_out.php?selected_user_id=$user_id");
        exit();
    } else {
        echo "Database error: " . $stmt->error;
    }
    $stmt->close();

// Restore a record
} elseif ($action === 'restore') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo "Invalid record ID.";
        exit();
    }

    $stmt = $conn->prepare("UPDATE stock_transactions SET deleted = 0 WHERE id = ? AND transaction_type = 'out'");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: stock_out.php" . ($role === 'admin' ? "?selected_user_id=$user_id" : ""));
        exit();
    } else {
        echo "Database error: " . $stmt->error;
    }
    $stmt->close();

// Permanently delete a record
} elseif ($action === 'permanent_delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo "Invalid record ID.";
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM stock_transactions WHERE id = ? AND transaction_type = 'out'");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: stock_out.php" . ($role === 'admin' ? "?selected_user_id=$user_id" : ""));
        exit();
    } else {
        echo "Database error: " . $stmt->error;
    }
    $stmt->close();

} else {
    echo "Invalid action.";
    exit();
}
