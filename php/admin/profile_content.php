<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../php/db_connect.php';

// Check admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo "<div class='alert alert-danger'>Access denied. Admins only.</div>";
    exit;
}

// Fetch all users
$result = $conn->query("SELECT id, full_name, username, role FROM users ORDER BY id ASC");

// Display flash messages
if (!empty($_SESSION['error'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
if (!empty($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>👥 Manage Users (Admin)</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">Create New User</button>
</div>

<div class="form-check form-switch mb-3">
  <input class="form-check-input" type="checkbox" id="darkModeToggle">
  <label class="form-check-label" for="darkModeToggle">Dark Mode</label>
</div>

<table class="table table-bordered table-striped">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
<?php 
$counter = 1; // Add this counter variable
while ($row = $result->fetch_assoc()): ?>
    <tr id="userRow<?= $row['id'] ?>">
        <td><?= $counter ?></td>  <!-- Change this line -->
        <td><?= htmlspecialchars($row['full_name']) ?></td>
        <td><?= htmlspecialchars($row['username']) ?></td>
        <td><?= htmlspecialchars($row['role']) ?></td>
        <td>
            <button class="btn btn-sm btn-warning" 
                    data-bs-toggle="modal" 
                    data-bs-target="#editUserModal" 
                    data-id="<?= $row['id'] ?>" 
                    data-full_name="<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>" 
                    data-username="<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>" 
                    data-role="<?= htmlspecialchars($row['role'], ENT_QUOTES) ?>">Edit</button>

            <button class="btn btn-sm btn-danger" 
                    data-bs-toggle="modal" 
                    data-bs-target="#deleteUserModal" 
                    data-id="<?= $row['id'] ?>" 
                    data-username="<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>">Delete</button>
        </td>
    </tr>
<?php 
$counter++; // Increment the counter
endwhile; ?>
</tbody>
</table>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="create_user.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Role</label>
          <select name="role" class="form-select" required>
            <option value="user" selected>User</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Create</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="update_user.php" class="modal-content" id="editUserForm">
      <div class="modal-header">
        <h5 class="modal-title">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editUserId">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" id="editFullName" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" id="editUsername" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password (leave blank to keep current)</label>
          <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
        </div>
        <div class="mb-3">
          <label class="form-label">Role</label>
          <select name="role" id="editRole" class="form-select" required>
            <option value="user">User</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Update</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="delete_user.php" class="modal-content" id="deleteUserForm">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="deleteUserId">
        <p>Are you sure you want to delete user <strong id="deleteUsername"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-danger">Yes, Delete</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
// Dark Mode toggle
function toggleDarkMode(checked) {
  if (checked) {
    document.body.classList.add('bg-dark', 'text-white');
    document.body.classList.remove('bg-light', 'text-dark');
    document.querySelectorAll('input, label, p, span, .modal-content, .form-control, select, table').forEach(el => {
      el.classList.add('text-white');
      if (el.classList.contains('form-control') || el.tagName.toLowerCase() === 'select' || el.tagName.toLowerCase() === 'table') {
        el.style.backgroundColor = '#343a40';
        el.style.color = 'white';
      } else {
        el.style.backgroundColor = '';
      }
    });
  } else {
    document.body.classList.add('bg-light', 'text-dark');
    document.body.classList.remove('bg-dark', 'text-white');
    document.querySelectorAll('input, label, p, span, .modal-content, .form-control, select, table').forEach(el => {
      el.classList.remove('text-white');
      if (el.classList.contains('form-control') || el.tagName.toLowerCase() === 'select' || el.tagName.toLowerCase() === 'table') {
        el.style.backgroundColor = '';
        el.style.color = '';
      } else {
        el.style.backgroundColor = '';
      }
    });
  }
}

const darkToggle = document.getElementById('darkModeToggle');
darkToggle.addEventListener('change', function() {
  toggleDarkMode(this.checked);
});

window.addEventListener('DOMContentLoaded', () => {
  toggleDarkMode(darkToggle.checked);
});

// Fill Edit Modal on button click
var editUserModal = document.getElementById('editUserModal');
editUserModal.addEventListener('show.bs.modal', function (event) {
  var button = event.relatedTarget;
  var id = button.getAttribute('data-id');
  var full_name = button.getAttribute('data-full_name');
  var username = button.getAttribute('data-username');
  var role = button.getAttribute('data-role');

  document.getElementById('editUserId').value = id;
  document.getElementById('editFullName').value = full_name;
  document.getElementById('editUsername').value = username;
  document.getElementById('editRole').value = role;
});

// Fill Delete Modal on button click
var deleteUserModal = document.getElementById('deleteUserModal');
deleteUserModal.addEventListener('show.bs.modal', function (event) {
  var button = event.relatedTarget;
  var id = button.getAttribute('data-id');
  var username = button.getAttribute('data-username');

  document.getElementById('deleteUserId').value = id;
  document.getElementById('deleteUsername').textContent = username;
});
</script>
