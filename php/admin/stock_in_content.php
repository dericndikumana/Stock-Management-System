<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../php/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Access denied. Please log in.</div>';
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Fetch users (only for admin)
$users = [];
if ($role === 'admin') {
    $stmtUsers = $conn->prepare("SELECT id, full_name FROM users ORDER BY full_name ASC");
    $stmtUsers->execute();
    $users = $stmtUsers->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtUsers->close();
}

// Determine selected user
$user_id = ($role === 'admin' && isset($_GET['selected_user_id'])) ? intval($_GET['selected_user_id']) : $logged_in_user_id;

// Pagination & search
$page_active = intval($_GET['page_active'] ?? 1);
$page_deleted = intval($_GET['page_deleted'] ?? 1);
$search_active = trim($_GET['search_active'] ?? '');
$search_deleted = trim($_GET['search_deleted'] ?? '');
$limit = 10;

function getTotalRecords($conn, $user_id, $search, $deleted) {
    $sql = "SELECT COUNT(*) FROM stock_transactions st INNER JOIN items i ON st.item_id = i.id WHERE st.user_id = ? AND st.transaction_type = 'in' AND st.deleted = ?";
    $params = [$user_id, $deleted];
    $types = "ii";

    if ($search !== '') {
        $sql .= " AND (i.item_name LIKE ? OR st.note LIKE ?)";
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

function getStockData($conn, $user_id, $search, $deleted, $limit, $offset) {
    $sql = "SELECT st.id, i.item_name, st.quantity, st.note, st.created_at FROM stock_transactions st INNER JOIN items i ON st.item_id = i.id WHERE st.user_id = ? AND st.transaction_type = 'in' AND st.deleted = ?";
    $params = [$user_id, $deleted];
    $types = "ii";

    if ($search !== '') {
        $sql .= " AND (i.item_name LIKE ? OR st.note LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "ss";
    }

    $sql .= " ORDER BY st.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Pagination logic
$total_active = getTotalRecords($conn, $user_id, $search_active, 0);
$total_pages_active = ceil($total_active / $limit);
$offset_active = ($page_active - 1) * $limit;
$activeStocks = getStockData($conn, $user_id, $search_active, 0, $limit, $offset_active);

$total_deleted = getTotalRecords($conn, $user_id, $search_deleted, 1);
$total_pages_deleted = ceil($total_deleted / $limit);
$offset_deleted = ($page_deleted - 1) * $limit;
$deletedStocks = getStockData($conn, $user_id, $search_deleted, 1, $limit, $offset_deleted);
?>

<!-- User selector (Admin only) -->
<?php if ($role === 'admin'): ?>
<form method="get" class="mb-3">
    <label for="selected_user_id">Select User:</label>
    <select id="selected_user_id" name="selected_user_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= ($u['id'] == $user_id) ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['full_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>
<?php endif; ?>

<!-- Modal Trigger -->
<?php if ($role !== 'admin'): ?>
<button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#stockInModal">+ Add Stock In</button>
<?php endif; ?>

<!-- Table -->
<div class="table-responsive">
<table class="table table-bordered align-middle">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Item</th>
            <th>Quantity</th>
            <th>Note</th>
            <th>Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($activeStocks->num_rows > 0):
            $index = $offset_active + 1;
            while ($row = $activeStocks->fetch_assoc()): ?>
            <tr>
                <td><?= $index++ ?></td>
                <td><?= htmlspecialchars($row['item_name']) ?></td>
                <td><?= $row['quantity'] ?></td>
                <td><?= htmlspecialchars($row['note']) ?></td>
                <td><?= $row['created_at'] ?></td>
                <td>
                    <?php if ($role !== 'admin'): ?>
                        <button class="btn btn-warning btn-sm btn-edit"
                                data-id="<?= $row['id'] ?>"
                                data-item="<?= htmlspecialchars($row['item_name']) ?>"
                                data-quantity="<?= $row['quantity'] ?>"
                                data-note="<?= htmlspecialchars($row['note']) ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#stockInModal">Edit</button>

                        <form action="stock_in_action.php" method="POST" class="d-inline">
                            <input type="hidden" name="action" value="soft_delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <?php if ($role === 'admin'): ?>
                                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this record?')">Delete</button>
                        </form>
                    <?php else: ?>
                        <span class="text-muted">No actions available</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="6" class="text-center">No records found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<!-- Stock In Modal -->
<?php if ($role !== 'admin'): ?>
<div class="modal fade" id="stockInModal" tabindex="-1" aria-labelledby="stockInModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="stockInForm" action="stock_in_action.php" method="POST" class="modal-content">
      <input type="hidden" name="action" id="formAction" value="add">
      <input type="hidden" name="id" id="recordId">
      <?php if ($role === 'admin'): ?>
        <input type="hidden" name="user_id" value="<?= $user_id ?>">
      <?php endif; ?>
      <div class="modal-header">
        <h5 class="modal-title" id="stockInModalLabel">Add Stock In</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label>Item</label>
            <select name="item_id" class="form-select" required>
                <option value="">Select item</option>
                <?php
                $stmt = $conn->prepare("SELECT id, item_name FROM items WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $items = $stmt->get_result();
                while ($item = $items->fetch_assoc()) {
                    echo '<option value="'. $item['id'] .'">'. htmlspecialchars($item['item_name']) .'</option>';
                }
                $stmt->close();
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Quantity</label>
            <input type="number" name="quantity" class="form-control" min="1" required>
        </div>
        <div class="mb-3">
            <label>Note</label>
            <textarea name="note" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Script: Fill modal for edit -->
<?php if ($role !== 'admin'): ?>
<script>
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('formAction').value = 'edit';
        document.getElementById('recordId').value = btn.dataset.id;
        document.querySelector('[name="quantity"]').value = btn.dataset.quantity;
        document.querySelector('[name="note"]').value = btn.dataset.note;

        let itemSelect = document.querySelector('[name="item_id"]');
        for (let opt of itemSelect.options) {
            if (opt.text === btn.dataset.item) {
                opt.selected = true;
                break;
            }
        }

        document.getElementById('stockInModalLabel').innerText = 'Edit Stock In';
    });
});

// Reset modal on close
document.getElementById('stockInModal').addEventListener('hidden.bs.modal', () => {
    document.getElementById('formAction').value = 'add';
    document.getElementById('recordId').value = '';
    document.querySelector('[name="item_id"]').selectedIndex = 0;
    document.querySelector('[name="quantity"]').value = '';
    document.querySelector('[name="note"]').value = '';
    document.getElementById('stockInModalLabel').innerText = 'Add Stock In';
});
</script>
<?php endif; ?>
