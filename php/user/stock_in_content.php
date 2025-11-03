<?php
// stock_in_content.php
// NOTE: No session_start() here, must be started in the caller script (stock_in.php)

include '../../php/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Access denied. Please log in.</div>';
    exit();
}


$user_id = $_SESSION['user_id'];

// Pagination and search params
$page_active = isset($_GET['page_active']) ? intval($_GET['page_active']) : 1;
$page_deleted = isset($_GET['page_deleted']) ? intval($_GET['page_deleted']) : 1;
$search_active = isset($_GET['search_active']) ? trim($_GET['search_active']) : '';
$search_deleted = isset($_GET['search_deleted']) ? trim($_GET['search_deleted']) : '';

$limit = 10;

function getTotalRecords($conn, $user_id, $search, $deleted) {
    $sql = "SELECT COUNT(*) FROM stock_transactions st
            INNER JOIN items i ON st.item_id = i.id
            WHERE st.user_id = ? AND st.transaction_type = 'in' AND st.deleted = ?";
    $params = [$user_id, $deleted];
    $types = "ii";

    if ($search !== '') {
        $sql .= " AND (i.item_name LIKE ? OR st.note LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "ss";
    }

    $stmt = $conn->prepare($sql);

    // bind params dynamically (up to 4 params here)
    if (count($params) === 2) {
        $stmt->bind_param($types, $params[0], $params[1]);
    } elseif (count($params) === 4) {
        $stmt->bind_param($types, $params[0], $params[1], $params[2], $params[3]);
    }

    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    return $total;
}

function getStockData($conn, $user_id, $search, $deleted, $limit, $offset) {
    $sql = "SELECT st.id, i.item_name, st.quantity, st.note, st.created_at
            FROM stock_transactions st
            INNER JOIN items i ON st.item_id = i.id
            WHERE st.user_id = ? AND st.transaction_type = 'in' AND st.deleted = ?";
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

    // bind params dynamically (up to 6 params)
    if (count($params) === 4) {
        $stmt->bind_param($types, $params[0], $params[1], $params[2], $params[3]);
    } elseif (count($params) === 6) {
        $stmt->bind_param($types, $params[0], $params[1], $params[2], $params[3], $params[4], $params[5]);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return $result;
}

// Active stocks pagination
$total_active = getTotalRecords($conn, $user_id, $search_active, 0);
$total_pages_active = ceil($total_active / $limit);
$offset_active = ($page_active - 1) * $limit;
$activeStocks = getStockData($conn, $user_id, $search_active, 0, $limit, $offset_active);

// Deleted stocks pagination
$total_deleted = getTotalRecords($conn, $user_id, $search_deleted, 1);
$total_pages_deleted = ceil($total_deleted / $limit);
$offset_deleted = ($page_deleted - 1) * $limit;
$deletedStocks = getStockData($conn, $user_id, $search_deleted, 1, $limit, $offset_deleted);
?>

<!-- Nav tabs -->
<ul class="nav nav-tabs mb-3" id="stockTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" aria-controls="active" aria-selected="true">
      Active Inventort List
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="deleted-tab" data-bs-toggle="tab" data-bs-target="#deleted" type="button" role="tab" aria-controls="deleted" aria-selected="false">
      Deleted Inventory
    </button>
  </li>
</ul>

<div class="tab-content" id="stockTabContent">

  <!-- Active Stock In Tab -->
  <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="get" class="d-flex" style="gap:8px;">
            <input type="hidden" name="page_active" value="1" />
            <input type="text" name="search_active" class="form-control" placeholder="Search item or note..." value="<?= htmlspecialchars($search_active) ?>">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#stockInModal">+ Add Inventory</button>
    </div>

    <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Note</th>
                <th>Date & Time</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($activeStocks->num_rows > 0):
                $count = $offset_active + 1;
                while ($row = $activeStocks->fetch_assoc()): ?>
                <tr>
                    <td><?= $count++; ?></td>
                    <td><?= htmlspecialchars($row['item_name']); ?></td>
                    <td><?= htmlspecialchars($row['quantity']); ?></td>
                    <td><?= htmlspecialchars($row['note']); ?></td>
                    <td><?= htmlspecialchars($row['created_at']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning btn-edit" data-id="<?= $row['id']; ?>" data-bs-toggle="modal" data-bs-target="#stockInModal">Edit</button>

                        <form action="stock_in_action.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this entry?');">
                            <input type="hidden" name="action" value="soft_delete">
                            <input type="hidden" name="id" value="<?= $row['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile;
            else: ?>
                <tr><td colspan="6" class="text-center">No active stock in records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination Active -->
    <nav aria-label="Page navigation example">
      <ul class="pagination justify-content-center">
        <?php if ($page_active > 1): ?>
            <li class="page-item"><a class="page-link" href="?page_active=<?= $page_active - 1 ?>&search_active=<?= urlencode($search_active) ?>">Previous</a></li>
        <?php endif; ?>
        <?php for ($p = 1; $p <= $total_pages_active; $p++): ?>
            <li class="page-item <?= $p === $page_active ? 'active' : '' ?>">
                <a class="page-link" href="?page_active=<?= $p ?>&search_active=<?= urlencode($search_active) ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
        <?php if ($page_active < $total_pages_active): ?>
            <li class="page-item"><a class="page-link" href="?page_active=<?= $page_active + 1 ?>&search_active=<?= urlencode($search_active) ?>">Next</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>

  <!-- Deleted Stock In Tab -->
  <div class="tab-pane fade" id="deleted" role="tabpanel" aria-labelledby="deleted-tab">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="get" class="d-flex" style="gap:8px;">
            <input type="hidden" name="page_deleted" value="1" />
            <input type="text" name="search_deleted" class="form-control" placeholder="Search item or note..." value="<?= htmlspecialchars($search_deleted) ?>">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>

    <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Note</th>
                <th>Date & Time</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($deletedStocks->num_rows > 0):
                $count = $offset_deleted + 1;
                while ($row = $deletedStocks->fetch_assoc()): ?>
                <tr>
                    <td><?= $count++; ?></td>
                    <td><?= htmlspecialchars($row['item_name']); ?></td>
                    <td><?= htmlspecialchars($row['quantity']); ?></td>
                    <td><?= htmlspecialchars($row['note']); ?></td>
                    <td><?= htmlspecialchars($row['created_at']); ?></td>
                    <td>
                        <form action="stock_in_action.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to rollback this entry?');">
                            <input type="hidden" name="action" value="rollback">
                            <input type="hidden" name="id" value="<?= $row['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-info">Rollback</button>
                        </form>

                        <form action="stock_in_action.php" method="POST" class="d-inline" onsubmit="return confirm('Permanently delete this entry?');">
                            <input type="hidden" name="action" value="delete_permanent">
                            <input type="hidden" name="id" value="<?= $row['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete Permanently</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile;
            else: ?>
                <tr><td colspan="6" class="text-center">No deleted stock in records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination Deleted -->
    <nav aria-label="Page navigation example">
      <ul class="pagination justify-content-center">
        <?php if ($page_deleted > 1): ?>
            <li class="page-item"><a class="page-link" href="?page_deleted=<?= $page_deleted - 1 ?>&search_deleted=<?= urlencode($search_deleted) ?>">Previous</a></li>
        <?php endif; ?>
        <?php for ($p = 1; $p <= $total_pages_deleted; $p++): ?>
            <li class="page-item <?= $p === $page_deleted ? 'active' : '' ?>">
                <a class="page-link" href="?page_deleted=<?= $p ?>&search_deleted=<?= urlencode($search_deleted) ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
        <?php if ($page_deleted < $total_pages_deleted): ?>
            <li class="page-item"><a class="page-link" href="?page_deleted=<?= $page_deleted + 1 ?>&search_deleted=<?= urlencode($search_deleted) ?>">Next</a></li>
        <?php endif; ?>
      </ul>
    </nav>

  </div>

</div>

<!-- Modal for Add/Edit Stock In -->
<div class="modal fade" id="stockInModal" tabindex="-1" aria-labelledby="stockInModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="stockInForm" action="stock_in_action.php" method="POST" class="modal-content">
      <input type="hidden" name="action" value="add" id="formAction">
      <input type="hidden" name="id" id="stockInId" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="stockInModalLabel">Add Stock In</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="item_id" class="form-label">Item</label>
          <select name="item_id" id="item_id" class="form-select" required>
            <option value="">Select an item</option>
            <?php
            $stmtItems = $conn->prepare("SELECT id, item_name FROM items WHERE user_id = ?");
            $stmtItems->bind_param("i", $user_id);
            $stmtItems->execute();
            $resultItems = $stmtItems->get_result();
            while ($item = $resultItems->fetch_assoc()) {
                echo '<option value="'. $item['id'] .'">'. htmlspecialchars($item['item_name']) .'</option>';
            }
            $stmtItems->close();
            ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="quantity" class="form-label">Quantity</label>
          <input type="number" min="1" name="quantity" id="quantity" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="note" class="form-label">Note</label>
          <textarea name="note" id="note" rows="2" class="form-control"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="submitBtn">Add Stock</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Edit button fills modal form with current data
  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function() {
      const tr = this.closest('tr');
      const id = this.dataset.id;
      const itemName = tr.children[1].innerText.trim();
      const quantity = tr.children[2].innerText.trim();
      const note = tr.children[3].innerText.trim();

      document.getElementById('stockInModalLabel').innerText = 'Edit Stock In';
      document.getElementById('formAction').value = 'edit';
      document.getElementById('stockInId').value = id;

      const itemSelect = document.getElementById('item_id');
      for (let option of itemSelect.options) {
        if (option.text === itemName) {
          option.selected = true;
          break;
        }
      }

      document.getElementById('quantity').value = quantity;
      document.getElementById('note').value = note;
      document.getElementById('submitBtn').innerText = 'Update Stock';
    });
  });

  // Reset modal when closed
  var stockInModal = document.getElementById('stockInModal');
  stockInModal.addEventListener('hidden.bs.modal', function () {
    document.getElementById('stockInModalLabel').innerText = 'Add Stock In';
    document.getElementById('formAction').value = 'add';
    document.getElementById('stockInId').value = '';
    document.getElementById('item_id').selectedIndex = 0;
    document.getElementById('quantity').value = '';
    document.getElementById('note').value = '';
    document.getElementById('submitBtn').innerText = 'Add Stock';
  });
</script>
