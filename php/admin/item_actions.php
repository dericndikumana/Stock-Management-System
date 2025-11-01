<?php
session_start();
include '../../php/db_connect.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: ../../login.php');
  exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';
$action = $_POST['action'] ?? '';

$page = $_POST['page'] ?? 1;
$search = $_POST['search'] ?? '';
$user_query = isset($_GET['user_id']) ? '&user_id=' . intval($_GET['user_id']) : '';

function redirect_back($extra = '') {
  global $page, $search, $user_query;
  header("Location: items.php?page=$page&search=" . urlencode($search) . $user_query . $extra);
  exit();
}

// ADD ITEM
if ($action === 'add') {
  $name = $_POST['item_name'] ?? '';
  $description = $_POST['description'] ?? '';
  $quantity = intval($_POST['quantity'] ?? 0);
  $unit_price = floatval($_POST['unit_price'] ?? 0);
  $currency = $_POST['currency'] ?? 'USD';

  $stmt = $conn->prepare("INSERT INTO items (user_id, item_name, description, quantity, unit_price, currency) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("issids", $user_id, $name, $description, $quantity, $unit_price, $currency);
  $stmt->execute();
  $stmt->close();

  redirect_back();
}

// EDIT ITEM
if ($action === 'edit') {
  $id = intval($_POST['id']);
  $name = $_POST['item_name'] ?? '';
  $description = $_POST['description'] ?? '';
  $quantity = intval($_POST['quantity'] ?? 0);
  $unit_price = floatval($_POST['unit_price'] ?? 0);
  $currency = $_POST['currency'] ?? 'USD';

  // Make sure user owns item or is admin
  $check = $conn->prepare("SELECT user_id FROM items WHERE id = ?");
  $check->bind_param("i", $id);
  $check->execute();
  $check->bind_result($item_owner);
  $check->fetch();
  $check->close();

  if ($item_owner == $user_id || $user_role === 'admin') {
    $stmt = $conn->prepare("UPDATE items SET item_name=?, description=?, quantity=?, unit_price=?, currency=? WHERE id=?");
    $stmt->bind_param("ssidsi", $name, $description, $quantity, $unit_price, $currency, $id);
    $stmt->execute();
    $stmt->close();
  }

  redirect_back();
}

// DELETE ITEM
if ($action === 'delete') {
  $id = intval($_POST['id']);

  // Check ownership
  $check = $conn->prepare("SELECT user_id FROM items WHERE id = ?");
  $check->bind_param("i", $id);
  $check->execute();
  $check->bind_result($item_owner);
  $check->fetch();
  $check->close();

  if ($item_owner == $user_id || $user_role === 'admin') {
    // Check if item is used in stock_transactions
    $check_tx = $conn->prepare("SELECT COUNT(*) FROM stock_transactions WHERE item_id = ?");
    $check_tx->bind_param("i", $id);
    $check_tx->execute();
    $check_tx->bind_result($count);
    $check_tx->fetch();
    $check_tx->close();

    if ($count > 0) {
      // Cannot delete
      redirect_back("&error=linked_to_stock_transactions");
    } else {
      $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $stmt->close();
    }
  }

  redirect_back();
}


// Unknown action fallback
redirect_back('&error=unknown_action');
