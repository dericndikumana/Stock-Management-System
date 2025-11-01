<?php
session_start();
include '../../php/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $item_ids = $_POST['item_ids'] ?? [];
    $quantities = $_POST['quantities'] ?? [];

    if (empty($customer_name) || empty($item_ids) || empty($quantities) || count($item_ids) !== count($quantities)) {
        $_SESSION['error'] = "Invalid input.";
        header("Location: stock_out.php");
        exit();
    }

    // ✅ Start transaction BEFORE inserting into receipt
    $conn->begin_transaction();

    try {
        // Insert into receipt table first
        $stmtReceipt = $conn->prepare("INSERT INTO receipt (user_id, customer_name, total_amount, created_at) VALUES (?, ?, 0, NOW())");
        $stmtReceipt->bind_param("is", $user_id, $customer_name);
        $stmtReceipt->execute();
        $receipt_id = $stmtReceipt->insert_id;
        $stmtReceipt->close();

        // Prepare insert into stock_transactions
        $insertStmt = $conn->prepare("INSERT INTO stock_transactions (user_id, item_id, transaction_type, quantity, note, deleted, receipt_id, created_at, customer_name) VALUES (?, ?, 'out', ?, '', 0, ?, NOW(), ?)");

        for ($i = 0; $i < count($item_ids); $i++) {
            $item_id = (int)$item_ids[$i];
            $quantity = (int)$quantities[$i];

            if ($quantity < 1) {
                throw new Exception("Quantity must be at least 1");
            }

            // Check if stock is available
            $stockCheck = $conn->prepare("SELECT quantity FROM items WHERE id = ? AND user_id = ? AND quantity >= ?");
            $stockCheck->bind_param("iii", $item_id, $user_id, $quantity);
            $stockCheck->execute();
            $stockCheck->store_result();

            if ($stockCheck->num_rows === 0) {
                throw new Exception("Not enough stock for item ID $item_id");
            }
            $stockCheck->close();

            // Insert into stock_transactions
            $insertStmt->bind_param("iiisi", $user_id, $item_id, $quantity, $customer_name, $receipt_id);
            $insertStmt->execute();

            // Update items table (reduce quantity)
            $update = $conn->prepare("UPDATE items SET quantity = quantity - ? WHERE id = ? AND user_id = ?");
            $update->bind_param("iii", $quantity, $item_id, $user_id);
            $update->execute();
            $update->close();
        }

        $insertStmt->close();
        $conn->commit();

        $_SESSION['success'] = "Stock out saved successfully. Receipt ID: $receipt_id";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    header("Location: stock_out.php");
    exit();
}



elseif ($action === 'delete_group') {
    $receipt_id = $_POST['receipt_id'] ?? '';

    if (empty($receipt_id)) {
        $_SESSION['error'] = "Invalid receipt ID.";
        header("Location: stock_out.php");
        exit();
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("SELECT item_id, quantity FROM stock_transactions WHERE receipt_id = ? AND user_id = ? AND transaction_type = 'out' AND deleted = 0");
        $stmt->bind_param("ii", $receipt_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $item_id = $row['item_id'];
            $qty = $row['quantity'];

            $restore = $conn->prepare("UPDATE items SET quantity = quantity + ? WHERE id = ? AND user_id = ?");
            $restore->bind_param("iii", $qty, $item_id, $user_id);
            $restore->execute();
            $restore->close();
        }
        $stmt->close();

        $del = $conn->prepare("UPDATE stock_transactions SET deleted = 1 WHERE receipt_id = ? AND user_id = ?");
        $del->bind_param("ii", $receipt_id, $user_id);
        $del->execute();
        $del->close();

        $conn->commit();
        $_SESSION['success'] = "Receipt deleted and stock restored.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Delete failed: " . $e->getMessage();
    }

    header("Location: stock_out.php");
    exit();
}

elseif ($action === 'update') {
    $receipt_id = $_POST['receipt_id'] ?? '';
    $customer_name = trim($_POST['customer_name'] ?? '');

    if (empty($receipt_id) || empty($customer_name)) {
        $_SESSION['error'] = "Missing receipt ID or customer name.";
        header("Location: stock_out.php");
        exit();
    }

    try {
        $stmt = $conn->prepare("UPDATE stock_transactions SET customer_name = ? WHERE receipt_id = ? AND user_id = ?");
        $stmt->bind_param("sii", $customer_name, $receipt_id, $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt2 = $conn->prepare("UPDATE receipt SET customer_name = ? WHERE id = ? AND user_id = ?");
        $stmt2->bind_param("sii", $customer_name, $receipt_id, $user_id);
        $stmt2->execute();
        $stmt2->close();

        $_SESSION['success'] = "Customer name updated successfully.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Update failed: " . $e->getMessage();
    }

    header("Location: stock_out.php");
    exit();
}

header("Location: stock_out.php");
exit();
