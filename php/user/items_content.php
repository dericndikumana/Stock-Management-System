<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../php/db_connect.php'; // Adjust path if needed

// Show success/error messages
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success mt-3 mx-3">' . htmlspecialchars($_SESSION['message']) . '</div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger mt-3 mx-3">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}

$user_id = $_SESSION['user_id'] ?? 0;
$search = $_GET['search'] ?? '';
$searchQuery = '%' . $search . '%';

$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Get total count
if ($search !== '') {
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM items WHERE user_id = ? AND (item_name LIKE ? OR description LIKE ?)");
    $stmtCount->bind_param("iss", $user_id, $searchQuery, $searchQuery);
} else {
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM items WHERE user_id = ?");
    $stmtCount->bind_param("i", $user_id);
}
$stmtCount->execute();
$stmtCount->bind_result($totalItems);
$stmtCount->fetch();
$stmtCount->close();

$totalPages = max(1, ceil($totalItems / $itemsPerPage));

// Fetch items
if ($search !== '') {
    $stmt = $conn->prepare("SELECT id, item_name, description, quantity, unit_price, created_at FROM items WHERE user_id = ? AND (item_name LIKE ? OR description LIKE ?) ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("issii", $user_id, $searchQuery, $searchQuery, $itemsPerPage, $offset);
} else {
    $stmt = $conn->prepare("SELECT id, item_name, description, quantity, unit_price, created_at FROM items WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $user_id, $itemsPerPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container mt-4">
  <h2 class="mb-4">Your Items</h2>

  <!-- Buttons: Add & Search -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
      ➕ Add New Item
    </button>

    <form method="GET" class="d-flex" role="search" aria-label="Item search form">
      <input type="text" name="search" class="form-control me-2" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
      <button type="submit" class="btn btn-outline-primary">🔍 Search</button>
    </form>
  </div>

  <!-- Scrollable Table -->
  <div style="max-height: 400px; overflow-y: auto;">
    <table class="table table-bordered table-striped mb-0">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Category</th>
          <th>Quantity</th>
          <th>Unit Price</th>
          <th>Created At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $serial = $offset + 1;
        if ($result->num_rows > 0):
          while ($row = $result->fetch_assoc()):
        ?>
          <tr>
            <td><?= $serial++ ?></td>
            <td><?= htmlspecialchars($row['item_name']) ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td><?= htmlspecialchars($row['quantity']) ?></td>
            <td><?= number_format($row['unit_price'], 2) ?></td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
            <td>
              <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editItemModal"
                data-id="<?= $row['id'] ?>"
                data-name="<?= htmlspecialchars($row['item_name'], ENT_QUOTES) ?>"
                data-description="<?= htmlspecialchars($row['description'], ENT_QUOTES) ?>"
                data-quantity="<?= $row['quantity'] ?>"
                data-unit_price="<?= $row['unit_price'] ?>"
              >Edit</button>

              <form method="POST" action="item_actions.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this item?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php
          endwhile;
        else:
        ?>
          <tr><td colspan="7" class="text-center">No items found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <nav aria-label="Pagination" class="mt-3">
    <ul class="pagination justify-content-center mb-0">
      <?php if ($page > 1): ?>
        <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Previous</a></li>
      <?php else: ?>
        <li class="page-item disabled"><span class="page-link">Previous</span></li>
      <?php endif; ?>

      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>

      <?php if ($page < $totalPages): ?>
        <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Next</a></li>
      <?php else: ?>
        <li class="page-item disabled"><span class="page-link">Next</span></li>
      <?php endif; ?>
    </ul>
  </nav>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="item_actions.php" method="POST" class="modal-content" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="addItemModalLabel">Add New Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

        <div class="mb-3">
          <label for="add_item_name" class="form-label">Item Name</label>
          <input type="text" name="item_name" id="add_item_name" class="form-control" required>
        </div>

        <div class="mb-3">
          <label for="add_description" class="form-label">Category</label>
          <select name="description" id="add_description" class="form-control" required>
            <option value="">Select Category</option>
            <option value="Beverage">Beverage</option>
            <option value="Snack">Snack</option>
            <option value="Dairy">Dairy</option>
            <option value="Electronics">Electronics</option>
            <option value="Cleaning Supplies">Cleaning Supplies</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div class="mb-3">
          <label for="add_quantity" class="form-label">Quantity</label>
          <input type="number" name="quantity" id="add_quantity" class="form-control" min="0" required>
        </div>

        <div class="mb-3">
  <label for="currency" class="form-label">Currency</label>
  <select name="currency" class="form-select" required>
    <option value="USD">US Dollar (USD)</option>
    <option value="ZIG">ZIG</option>
  </select>
</div>

        
        <div class="mb-3">
          <label for="add_unit_price" class="form-label">Unit Price</label>
          <input type="number" step="0.01" name="unit_price" id="add_unit_price" class="form-control" min="0" required>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Item</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="item_actions.php" method="POST" class="modal-content" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="editItemModalLabel">Edit Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

        <div class="mb-3">
          <label for="edit_item_name" class="form-label">Item Name</label>
          <input type="text" name="item_name" id="edit_item_name" class="form-control" required>
        </div>

        <div class="mb-3">
          <label for="edit_description" class="form-label">Category</label>
          <select name="description" id="edit_description" class="form-control" required>
            <option value="">Select Category</option>
            <option value="Beverage">Beverage</option>
            <option value="Snack">Snack</option>
            <option value="Dairy">Dairy</option>
            <option value="Electronics">Electronics</option>
            <option value="Cleaning Supplies">Cleaning Supplies</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div class="mb-3">
          <label for="edit_quantity" class="form-label">Quantity</label>
          <input type="number" name="quantity" id="edit_quantity" class="form-control" min="0" required>
        </div>

        <div class="mb-3">
          <label for="edit_unit_price" class="form-label">Unit Price</label>
          <input type="number" step="0.01" name="unit_price" id="edit_unit_price" class="form-control" min="0" required>
        </div>

        <div class="mb-3">
          <label for="edit_currency" class="form-label">Currency</label>
          <select name="currency" id="edit_currency" class="form-select" required>
            <option value="USD">US Dollar (USD)</option>
            <option value="ZIG">ZIG</option>
          </select>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>


<script>
// Populate Edit Modal with data from button
var editItemModal = document.getElementById('editItemModal');
editItemModal.addEventListener('show.bs.modal', function (event) {
  var button = event.relatedTarget;

  var id = button.getAttribute('data-id');
  var name = button.getAttribute('data-name');
  var description = button.getAttribute('data-description');
  var quantity = button.getAttribute('data-quantity');
  var unit_price = button.getAttribute('data-unit_price');

  document.getElementById('edit_id').value = id;
  document.getElementById('edit_item_name').value = name;

  var selectDesc = document.getElementById('edit_description');
  for (var i = 0; i < selectDesc.options.length; i++) {
    if (selectDesc.options[i].value === description) {
      selectDesc.selectedIndex = i;
      break;
    }
  }

  document.getElementById('edit_quantity').value = quantity;
  document.getElementById('edit_unit_price').value = unit_price;
});
</script>
