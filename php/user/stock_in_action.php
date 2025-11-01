<?php
// Start session if not started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../php/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page or show error
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        if ($item_id <= 0 || $quantity <= 0) {
            $_SESSION['error'] = "Invalid input.";
            header("Location: stock_in.php");
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO stock_transactions (user_id, item_id, transaction_type, quantity, note, created_at, deleted) VALUES (?, ?, 'in', ?, ?, NOW(), 0)");
        $stmt->bind_param("iiis", $user_id, $item_id, $quantity, $note);
        if ($stmt->execute()) {
            $stmt2 = $conn->prepare("UPDATE items SET quantity = quantity + ? WHERE id = ? AND user_id = ?");
            $stmt2->bind_param("iii", $quantity, $item_id, $user_id);
            $stmt2->execute();
            $stmt2->close();

            $_SESSION['message'] = "Stock added successfully.";
        } else {
            $_SESSION['error'] = "Failed to add stock.";
        }
        $stmt->close();

    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        if ($id <= 0 || $item_id <= 0 || $quantity <= 0) {
            $_SESSION['error'] = "Invalid input.";
            header("Location: stock_in.php");
            exit();
        }

        // Get old data
        $stmtCheck = $conn->prepare("SELECT item_id, quantity FROM stock_transactions WHERE id = ? AND user_id = ? AND deleted = 0");
        $stmtCheck->bind_param("ii", $id, $user_id);
        $stmtCheck->execute();
        $stmtCheck->bind_result($old_item_id, $old_quantity);

        if ($stmtCheck->fetch()) {
            $stmtCheck->close();

            $stmtUpd = $conn->prepare("UPDATE stock_transactions SET item_id = ?, quantity = ?, note = ? WHERE id = ? AND user_id = ?");
            // FIXED bind_param here:
            $stmtUpd->bind_param("iissi", $item_id, $quantity, $note, $id, $user_id);
            if ($stmtUpd->execute()) {
                if ($old_item_id == $item_id) {
                    $diff = $quantity - $old_quantity;
                    $stmtItemUpd = $conn->prepare("UPDATE items SET quantity = GREATEST(0, quantity + ?) WHERE id = ? AND user_id = ?");
                    $stmtItemUpd->bind_param("iii", $diff, $item_id, $user_id);
                    $stmtItemUpd->execute();
                    $stmtItemUpd->close();
                } else {
                    $stmtRollbackOld = $conn->prepare("UPDATE items SET quantity = GREATEST(0, quantity - ?) WHERE id = ? AND user_id = ?");
                    $stmtRollbackOld->bind_param("iii", $old_quantity, $old_item_id, $user_id);
                    $stmtRollbackOld->execute();
                    $stmtRollbackOld->close();

                    $stmtAddNew = $conn->prepare("UPDATE items SET quantity = quantity + ? WHERE id = ? AND user_id = ?");
                    $stmtAddNew->bind_param("iii", $quantity, $item_id, $user_id);
                    $stmtAddNew->execute();
                    $stmtAddNew->close();
                }
                $_SESSION['message'] = "Stock updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update stock.";
            }
            $stmtUpd->close();
        } else {
            $_SESSION['error'] = "Stock record not found or deleted.";
        }

    } elseif ($action === 'delete' || $action === 'soft_delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = "Invalid delete request.";
            header("Location: stock_in.php");
            exit();
        }

        $stmt = $conn->prepare("SELECT item_id, quantity FROM stock_transactions WHERE id = ? AND user_id = ? AND deleted = 0");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $stmt->bind_result($item_id, $quantity);

        if ($stmt->fetch()) {
            $stmt->close();

            $stmtUpdateItem = $conn->prepare("UPDATE items SET quantity = GREATEST(0, quantity - ?) WHERE id = ? AND user_id = ?");
            $stmtUpdateItem->bind_param("iii", $quantity, $item_id, $user_id);
            $stmtUpdateItem->execute();
            $stmtUpdateItem->close();

            $stmtDel = $conn->prepare("UPDATE stock_transactions SET deleted = 1 WHERE id = ? AND user_id = ?");
            $stmtDel->bind_param("ii", $id, $user_id);
            $stmtDel->execute();
            $stmtDel->close();

            $_SESSION['message'] = "Stock record soft deleted.";
        } else {
            $_SESSION['error'] = "Stock record not found or already deleted.";
        }

    } elseif ($action === 'rollback') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = "Invalid rollback request.";
            header("Location: stock_in.php");
            exit();
        }

        $stmt = $conn->prepare("SELECT item_id, quantity FROM stock_transactions WHERE id = ? AND user_id = ? AND deleted = 1");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $stmt->bind_result($item_id, $quantity);

        if ($stmt->fetch()) {
            $stmt->close();

            $stmtAdd = $conn->prepare("UPDATE items SET quantity = quantity + ? WHERE id = ? AND user_id = ?");
            $stmtAdd->bind_param("iii", $quantity, $item_id, $user_id);
            $stmtAdd->execute();
            $stmtAdd->close();

            $stmtUpd = $conn->prepare("UPDATE stock_transactions SET deleted = 0 WHERE id = ? AND user_id = ?");
            $stmtUpd->bind_param("ii", $id, $user_id);
            $stmtUpd->execute();
            $stmtUpd->close();

            $_SESSION['message'] = "Stock rollback successful.";
        } else {
            $_SESSION['error'] = "Deleted stock record not found.";
        }

    } elseif ($action === 'delete_permanent') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error'] = "Invalid delete request.";
            header("Location: stock_in.php");
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM stock_transactions WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['message'] = "Stock record permanently deleted.";
    }

    header("Location: stock_in.php");
    exit();
}

// If no POST, redirect to stock_in.php
header("Location: stock_in.php");
exit();
