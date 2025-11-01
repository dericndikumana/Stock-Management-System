<?php
// stock_out_content.php

if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Access denied. Please log in.</div>';
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Fetch users for admin dropdown
$users = [];
if ($role === 'admin') {
    $stmtUsers = $conn->prepare("SELECT id, full_name FROM users ORDER BY full_name ASC");
    $stmtUsers->execute();
    $users = $stmtUsers->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtUsers->close();
}

// Selected user filter (admin can select user, normal user fixed)
$user_id = ($role === 'admin' && isset($_GET['selected_user_id'])) ? intval($_GET['selected_user_id']) : $logged_in_user_id;

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

// For grouping, we will group by user_id + created_at (purchase datetime)
// So first, count distinct purchase groups (user_id + created_at)

$count_sql = "SELECT COUNT(DISTINCT CONCAT(st.user_id, '_', st.created_at)) AS total_groups
FROM stock_transactions st
JOIN items i ON st.item_id = i.id
JOIN users u ON st.user_id = u.id
WHERE st.transaction_type = 'out' AND st.deleted = 0";

$params = [];
$types = "";

if ($role === 'admin') {
    $count_sql .= " AND st.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
} else {
    // normal user, filter by their user_id
    $count_sql .= " AND st.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

if ($search !== '') {
    $count_sql .= " AND (i.item_name LIKE ? OR st.note LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$stmt = $conn->prepare($count_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total_groups);
$stmt->fetch();
$stmt->close();

$total_pages = ceil($total_groups / $limit);

// Now fetch the purchase groups with pagination, grouped by user_id + created_at

$data_sql = "SELECT st.user_id, u.full_name, st.created_at
FROM stock_transactions st
JOIN users u ON st.user_id = u.id
JOIN items i ON st.item_id = i.id
WHERE st.transaction_type = 'out' AND st.deleted = 0";

if ($role === 'admin') {
    $data_sql .= " AND st.user_id = ?";
} else {
    $data_sql .= " AND st.user_id = ?";
}

if ($search !== '') {
    $data_sql .= " AND (i.item_name LIKE ? OR st.note LIKE ? OR u.full_name LIKE ?)";
}

$data_sql .= " GROUP BY st.user_id, st.created_at ORDER BY st.created_at DESC LIMIT ? OFFSET ?";

$params_data = [];
$types_data = "";

$params_data[] = $user_id;
$types_data .= "i";

if ($search !== '') {
    $params_data[] = $search_param;
    $params_data[] = $search_param;
    $params_data[] = $search_param;
    $types_data .= "sss";
}

$params_data[] = $limit;
$params_data[] = $offset;
$types_data .= "ii";

$stmt = $conn->prepare($data_sql);
$stmt->bind_param($types_data, ...$params_data);
$stmt->execute();
$result = $stmt->get_result();

?>

<!-- Admin user selector -->
<?php if ($role === 'admin'): ?>
<form method="get" class="mb-3 d-flex align-items-center flex-wrap gap-2">
    <label for="selected_user_id" class="form-label fw-bold mb-0 me-2">Select User:</label>
    <select id="selected_user_id" name="selected_user_id" class="form-select w-auto" onchange="this.form.submit()">
        <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= ($u['id'] == $user_id) ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['full_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="hidden" name="page" value="1" />
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search items, notes or customer..." class="form-control w-auto flex-grow-1" />
    <button type="submit" class="btn btn-primary">Search</button>
</form>
<?php else: ?>
<!-- Normal user search and add stock out button horizontally -->
<div class="d-flex justify-content-between align-items-center mb-3 gap-2">
  <form method="get" class="d-flex flex-grow-1 gap-2">
    <input type="hidden" name="page" value="1" />
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search items or note..." class="form-control" />
    <button type="submit" class="btn btn-primary">Search</button>
  </form>
  <!-- <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#stockOutModal">+ Add Stock Out</button> -->
</div>
<?php endif; ?>

<?php if ($role === 'admin'): ?>
<div class="mb-3 text-end">
  <!-- <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#stockOutModal">+ Add Stock Out</button> -->
</div>
<?php endif; ?>

<!-- Stock out table -->
<div class="table-responsive">
  <table class="table table-striped table-bordered align-middle">
    <thead class="table-dark">
      <tr>
        <th>#</th>
        <th>Customer</th>
        <th>Items Purchased</th>
        <th>Date & Time</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
<?php 
if ($result->num_rows > 0): 
    $count = $offset + 1;
    while ($group = $result->fetch_assoc()): 
        // Fetch all items in this purchase group (user_id + created_at)
        $stmtItems = $conn->prepare("SELECT i.item_name, st.quantity 
                                     FROM stock_transactions st 
                                     JOIN items i ON st.item_id = i.id
                                     WHERE st.user_id = ? AND st.created_at = ? AND st.transaction_type = 'out' AND st.deleted = 0");
        $stmtItems->bind_param("is", $group['user_id'], $group['created_at']);
        $stmtItems->execute();
        $items_result = $stmtItems->get_result();

        $items_list = [];
        while ($item = $items_result->fetch_assoc()) {
            $items_list[] = htmlspecialchars($item['item_name']) . " (x" . htmlspecialchars($item['quantity']) . ")";
        }
        $stmtItems->close();
?>
  <tr>
    <td><?= $count++; ?></td>
    <td><?= htmlspecialchars($group['full_name']); ?></td>
    <td><?= implode("<br>", $items_list); ?></td>
    <td><?= htmlspecialchars($group['created_at']); ?></td>
    <td>
      <!-- Use created_at and user_id as unique key for the purchase group -->
      <a href="print_receipt.php?user_id=<?= $group['user_id'] ?>&created_at=<?= urlencode($group['created_at']) ?>" target="_blank" class="btn btn-sm btn-info me-1" title="Print Receipt">
        Receipt
      </a>

      <form method="post" action="stock_out_action.php" onsubmit="return confirm('Are you sure you want to delete this purchase?');" class="d-inline">
        <input type="hidden" name="action" value="soft_delete_group" />
        <input type="hidden" name="user_id" value="<?= $group['user_id'] ?>" />
        <input type="hidden" name="created_at" value="<?= htmlspecialchars($group['created_at']); ?>" />
        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
      </form>
    </td>
  </tr>
<?php 
    endwhile; 
else: ?>
  <tr>
    <td colspan="5" class="text-center">No stock out records found.</td>
  </tr>
<?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<nav aria-label="Page navigation" class="mt-3">
  <ul class="pagination justify-content-center flex-wrap gap-2">
    <?php if ($page > 1): ?>
      <li class="page-item">
        <a class="page-link" href="?<?= ($role === 'admin') ? "selected_user_id=$user_id&" : "" ?>page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Previous</a>
      </li>
    <?php endif; ?>

    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
      <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
        <a class="page-link" href="?<?= ($role === 'admin') ? "selected_user_id=$user_id&" : "" ?>page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
      <li class="page-item">
        <a class="page-link" href="?<?= ($role === 'admin') ? "selected_user_id=$user_id&" : "" ?>page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Next</a>
      </li>
    <?php endif; ?>
  </ul>
</nav>
