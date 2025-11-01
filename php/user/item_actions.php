<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../php/db_connect.php';

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($action === 'add') {
    $item_name = trim($_POST['item_name']);
    $description = trim($_POST['description']);
    $quantity = intval($_POST['quantity']);
    $unit_price = floatval($_POST['unit_price']);
    $currency = trim($_POST['currency']);
    $page = $_POST['page'] ?? 1;
    $search = $_POST['search'] ?? '';

    $stmt = $conn->prepare("INSERT INTO items (user_id, item_name, description, quantity, unit_price, currency) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issids", $user_id, $item_name, $description, $quantity, $unit_price, $currency);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Item added successfully!";
        } else {
            $_SESSION['error'] = "Failed to add item: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Database error (prepare): " . $conn->error;
    }

    header("Location: items.php?page=" . urlencode($page) . "&search=" . urlencode($search));
    exit;

} elseif ($action === 'edit') {
    $id = intval($_POST['id']);
    $item_name = trim($_POST['item_name']);
    $description = trim($_POST['description']);
    $quantity = intval($_POST['quantity']);
    $unit_price = floatval($_POST['unit_price']);
    $currency = trim($_POST['currency']);
    $page = $_POST['page'] ?? 1;
    $search = $_POST['search'] ?? '';

    $stmt = $conn->prepare("UPDATE items SET item_name = ?, description = ?, quantity = ?, unit_price = ?, currency = ? WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ssidsii", $item_name, $description, $quantity, $unit_price, $currency, $id, $user_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Item updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update item: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Database error (prepare): " . $conn->error;
    }

    header("Location: items.php?page=" . urlencode($page) . "&search=" . urlencode($search));
    exit;

} elseif ($action === 'delete') {
    $id = intval($_POST['id']);
    $page = $_POST['page'] ?? 1;
    $search = $_POST['search'] ?? '';

    // First, delete from stock_transactions where item_id = this item
    $stmt = $conn->prepare("DELETE FROM stock_transactions WHERE item_id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute(); // optional check
        $stmt->close();
    }

    // Then delete the item itself
    $stmt = $conn->prepare("DELETE FROM items WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $id, $user_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Item and its transactions deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete item: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Database error (prepare): " . $conn->error;
    }

    header("Location: items.php?page=" . urlencode($page) . "&search=" . urlencode($search));
    exit;


} else {
    $_SESSION['error'] = "Invalid action!";
    header("Location: items.php");
    exit;
}
?>
