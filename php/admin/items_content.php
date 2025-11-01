<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../php/db_connect.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = $_GET['search'] ?? '';
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Admin: allow selection of another user
$selected_user = $user_id;
if ($user_role === 'admin' && isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $selected_user = intval($_GET['user_id']);
}

// Admin: fetch all users for dropdown
$user_options = [];
if ($user_role === 'admin') {
    $result = $conn->query("SELECT id, username FROM users ORDER BY username");
    while ($row = $result->fetch_assoc()) {
        $user_options[] = $row;
    }
}

// Count total for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(*) FROM items
    WHERE user_id = ? AND item_name LIKE ?
");
$search_term = "%$search%";
$count_stmt->bind_param("is", $selected_user, $search_term);
$count_stmt->execute();
$count_stmt->bind_result($total_items);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_items / $items_per_page);

// Fetch paginated items
$stmt = $conn->prepare("
    SELECT * FROM items
    WHERE user_id = ? AND item_name LIKE ?
    ORDER BY created_at DESC
    LIMIT ?, ?
");
$stmt->bind_param("isii", $selected_user, $search_term, $offset, $items_per_page);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container">
  <h4 class="mb-3">📦 Items List</h4>


  <?php if (isset($_GET['error']) && $_GET['error'] === 'linked_to_stock_transactions'): ?>
  <div class="alert alert-danger">
    ❌ Cannot delete this item because it is used in stock transactions.
  </div>
<?php endif; ?>


  <form method="GET" class="row g-2 mb-3">
    <?php if ($user_role === 'admin'): ?>
      <div class="col-md-3">
        <select name="user_id" class="form-select" onchange="this.form.submit()">
          <option value="">-- Select User --</option>
          <?php foreach ($user_options as $user): ?>
            <option value="<?= $user['id'] ?>" <?= $user['id'] == $selected_user ? 'selected' : '' ?>>
              <?= htmlspecialchars($user['username']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="col-md-4">
      <input type="text" name="search" class="form-control" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>" />
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100">Search</button>
    </div>
  </form>

  <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
    <table class="table table-bordered table-hover table-sm">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Item Name</th>
          <th>Description</th>
          <th>Quantity</th>
          <th>Price</th>
          <th>Currency</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $index = $offset + 1;
        if ($result->num_rows > 0):
          while ($row = $result->fetch_assoc()):
        ?>
        <tr>
          <td><?= $index++ ?></td>
          <td><?= htmlspecialchars($row['item_name']) ?></td>
          <td><?= htmlspecialchars($row['description']) ?></td>
          <td><?= $row['quantity'] ?></td>
          <td><?= number_format($row['unit_price'], 2) ?></td>
          <td><?= htmlspecialchars($row['currency']) ?></td>
          <td>
            <?php if ($user_id == $row['user_id'] || $user_role === 'admin'): ?>
              <button
                class="btn btn-sm btn-info"
                data-bs-toggle="modal"
                data-bs-target="#editItemModal"
                data-id="<?= $row['id'] ?>"
                data-name="<?= htmlspecialchars($row['item_name']) ?>"
                data-description="<?= htmlspecialchars($row['description']) ?>"
                data-quantity="<?= $row['quantity'] ?>"
                data-unit_price="<?= $row['unit_price'] ?>"
                data-currency="<?= htmlspecialchars($row['currency']) ?>"
              >✏️ Edit</button>

              <button
                class="btn btn-sm btn-danger"
                data-bs-toggle="modal"
                data-bs-target="#deleteModal<?= $row['id'] ?>"
              >🗑️ Delete</button>

              <!-- Delete Modal -->
              <div class="modal fade" id="deleteModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $row['id'] ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <form action="item_actions.php" method="POST" class="modal-content">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <div class="modal-header">
                      <h5 class="modal-title" id="deleteModalLabel<?= $row['id'] ?>">Confirm Delete</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      Are you sure you want to delete <strong><?= htmlspecialchars($row['item_name']) ?></strong>?
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                  </form>
                </div>
              </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="7" class="text-center">No items found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <nav aria-label="Page navigation">
    <ul class="pagination justify-content-center mt-3">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?><?= $user_role === 'admin' ? '&user_id=' . $selected_user : '' ?>">
            <?= $i ?>
          </a>
        </li>
      <?php endfor; ?>
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
  // Populate Edit Modal with data from clicked button
  var editItemModal = document.getElementById('editItemModal');
  editItemModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;

    var id = button.getAttribute('data-id');
    var name = button.getAttribute('data-name');
    var description = button.getAttribute('data-description');
    var quantity = button.getAttribute('data-quantity');
    var unit_price = button.getAttribute('data-unit_price');
    var currency = button.getAttribute('data-currency');

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

    var selectCurrency = document.getElementById('edit_currency');
    for (var i = 0; i < selectCurrency.options.length; i++) {
      if (selectCurrency.options[i].value === currency) {
        selectCurrency.selectedIndex = i;
        break;
      }
    }
  });
</script>
