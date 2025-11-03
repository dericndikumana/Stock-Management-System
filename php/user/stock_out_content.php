<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../php/db_connect.php';
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Access denied. Please log in.</div>';
    exit();
}

$user_id = $_SESSION['user_id'];
$page_out = isset($_GET['page_out']) ? intval($_GET['page_out']) : 1;
$search_out = isset($_GET['search_out']) ? trim($_GET['search_out']) : '';
$limit = 10;

function getTotalStockOut($conn, $user_id, $search) {
    $sql = "SELECT COUNT(DISTINCT receipt_id) as total FROM stock_transactions 
            WHERE user_id = ? AND transaction_type = 'out' AND deleted = 0";
    $params = [$user_id];
    $types = "i";

    if ($search !== '') {
        $sql .= " AND (customer_name LIKE ? OR receipt_id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "ss";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    return $total;
}

function getStockOutGroups($conn, $user_id, $search, $limit, $offset) {
    $sql = "SELECT receipt_id, customer_name, MAX(created_at) as date, COUNT(*) as items_count
            FROM stock_transactions
            WHERE user_id = ? AND transaction_type = 'out' AND deleted = 0";
    $params = [$user_id];
    $types = "i";

    if ($search !== '') {
        $sql .= " AND (customer_name LIKE ? OR receipt_id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "ss";
    }
    $sql .= " GROUP BY receipt_id, customer_name ORDER BY date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

// Handle form submissions - FIXED WITH JAVASCRIPT REDIRECTS
$redirect_script = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $item_ids = $_POST['item_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];

        if (empty($customer_name) || count($item_ids) !== count($quantities)) {
            $_SESSION['error'] = "Invalid input.";
            $redirect_script = '<script>window.location.href = "stock_out.php";</script>';
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO receipt (user_id, customer_name, total_amount, created_at) VALUES (?, ?, 0, NOW())");
                $stmt->bind_param("is", $user_id, $customer_name);
                $stmt->execute();
                $receipt_id = $stmt->insert_id;
                $stmt->close();

                // Prepare insert statement once outside the loop
                $insertStmt = $conn->prepare("INSERT INTO stock_transactions (user_id, item_id, transaction_type, quantity, note, deleted, receipt_id, created_at, customer_name) VALUES (?, ?, 'out', ?, '', 0, ?, NOW(), ?)");
                
                for ($i = 0; $i < count($item_ids); $i++) {
                    $item_id = (int)$item_ids[$i];
                    $qty = (int)$quantities[$i];

                    if ($qty < 1) {
                        throw new Exception("Quantity must be at least 1");
                    }

                    $stockCheck = $conn->prepare("SELECT quantity FROM items WHERE id = ? AND user_id = ? AND quantity >= ?");
                    $stockCheck->bind_param("iii", $item_id, $user_id, $qty);
                    $stockCheck->execute();
                    $stockCheck->store_result();

                    if ($stockCheck->num_rows === 0) {
                        throw new Exception("Not enough stock for selected item");
                    }
                    $stockCheck->close();

                    // CORRECTED parameter binding
                    $insertStmt->bind_param("iiiss", $user_id, $item_id, $qty, $receipt_id, $customer_name);
                    $insertStmt->execute();

                    $update = $conn->prepare("UPDATE items SET quantity = quantity - ? WHERE id = ? AND user_id = ?");
                    $update->bind_param("iii", $qty, $item_id, $user_id);
                    $update->execute();
                    $update->close();
                }
                $insertStmt->close();
                $conn->commit();
                $_SESSION['success'] = "Stock out added successfully. Receipt ID: $receipt_id";
                $redirect_script = '<script>window.location.href = "stock_out.php";</script>';
                
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Error: " . $e->getMessage();
                $redirect_script = '<script>window.location.href = "stock_out.php";</script>';
            }
        }

    } elseif ($action === 'delete_group') {
        $receipt_id = $_POST['receipt_id'] ?? '';
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT item_id, quantity FROM stock_transactions WHERE receipt_id = ? AND user_id = ? AND transaction_type = 'out' AND deleted = 0");
            $stmt->bind_param("si", $receipt_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $restore = $conn->prepare("UPDATE items SET quantity = quantity + ? WHERE id = ? AND user_id = ?");
                $restore->bind_param("iii", $row['quantity'], $row['item_id'], $user_id);
                $restore->execute();
                $restore->close();
            }
            $stmt->close();

            $stmt = $conn->prepare("UPDATE stock_transactions SET deleted = 1 WHERE receipt_id = ? AND user_id = ?");
            $stmt->bind_param("si", $receipt_id, $user_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $_SESSION['success'] = "Receipt deleted and stock restored.";
            $redirect_script = '<script>window.location.href = "stock_out.php";</script>';
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error deleting: " . $e->getMessage();
            $redirect_script = '<script>window.location.href = "stock_out.php";</script>';
        }
    }
    
    // Output redirect script if needed
    if ($redirect_script) {
        echo $redirect_script;
        exit();
    }
}

// Get data for display
$total_out = getTotalStockOut($conn, $user_id, $search_out);
$total_pages_out = ceil($total_out / $limit);
$offset_out = ($page_out - 1) * $limit;
$stockOutGroups = getStockOutGroups($conn, $user_id, $search_out, $limit, $offset_out);
?>

<!-- Alerts -->
<?php if (isset($_SESSION['success'])): ?>
  <div class="alert alert-success"> <?= $_SESSION['success']; unset($_SESSION['success']); ?> </div>
<?php elseif (isset($_SESSION['error'])): ?>
  <div class="alert alert-danger"> <?= $_SESSION['error']; unset($_SESSION['error']); ?> </div>
<?php endif; ?>

<!-- Main Content -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <form method="get" class="d-flex" style="gap:8px;">
    <input type="hidden" name="page_out" value="1" />
    <input type="text" name="search_out" class="form-control" placeholder="Search customer or receipt..." value="<?= htmlspecialchars($search_out) ?>">
    <button type="submit" class="btn btn-primary">Search</button>
  </form>
  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#stockOutModal">+ Record Sale</button>
</div>

<div class="table-responsive">
  <table class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>#</th>
        <th>Receipt</th>
        <th>Customer</th>
        <th>Items</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($stockOutGroups->num_rows > 0):
        $count = $offset_out + 1;
        while ($row = $stockOutGroups->fetch_assoc()): ?>
        <tr>
          <td><?= $count++; ?></td>
          <td><?= htmlspecialchars($row['receipt_id']); ?></td>
          <td><?= htmlspecialchars($row['customer_name']); ?></td>
          <td><?= $row['items_count']; ?> item(s)</td>
          <td><?= htmlspecialchars($row['date']); ?></td>
          <td>
            <a href="print_receipt.php?receipt_id=<?= urlencode($row['receipt_id']) ?>" class="btn btn-info btn-sm">Receipt</a>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this receipt?');">
              <input type="hidden" name="action" value="delete_group">
              <input type="hidden" name="receipt_id" value="<?= htmlspecialchars($row['receipt_id']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
    <?php endwhile; else: ?>
        <tr><td colspan="6" class="text-center">No records found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal -->
<div class="modal fade" id="stockOutModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="add">
      <div class="modal-header">
        <h5 class="modal-title">Add Stock Out</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Customer Name</label>
          <input type="text" name="customer_name" class="form-control" required>
        </div>

        <div id="itemsContainer">
          <div class="row g-2 align-items-end mb-2">
            <div class="col-md-6">
              <label class="form-label">Item</label>
              <select name="item_ids[]" class="form-select" required>
                <option value="">Select item</option>
                <?php
                $stmtItems = $conn->prepare("SELECT id, item_name, quantity FROM items WHERE user_id = ? AND quantity > 0");
                $stmtItems->bind_param("i", $user_id);
                $stmtItems->execute();
                $resultItems = $stmtItems->get_result();
                while ($item = $resultItems->fetch_assoc()) {
                    echo '<option value="'. $item['id'] .'">'. htmlspecialchars($item['item_name']) .' ('. $item['quantity'] .')</option>';
                }
                $stmtItems->close();
                ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Quantity</label>
              <input type="number" name="quantities[]" class="form-control" min="1" required>
            </div>
            <div class="col-md-2">
              <button type="button" class="btn btn-danger remove-item">X</button>
            </div>
          </div>
        </div>

        <button type="button" class="btn btn-secondary" id="addMore">+ Add Item</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('addMore').addEventListener('click', function() {
  const container = document.getElementById('itemsContainer');
  const row = container.querySelector('.row').cloneNode(true);
  row.querySelector('select').selectedIndex = 0;
  row.querySelector('input').value = '';
  container.appendChild(row);
});

document.addEventListener('click', function(e) {
  if (e.target.classList.contains('remove-item')) {
    const container = document.getElementById('itemsContainer');
    if (container.children.length > 1) {
      e.target.closest('.row').remove();
    }
  }
});
</script>