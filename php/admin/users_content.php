<?php
// Fetch all users from DB
$result = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY id ASC");
?>

<h3>Manage Users</h3>

<!-- Add User Button -->
<button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">+ Add New User</button>

<!-- Users Table -->
<table class="table table-striped table-bordered">
  <thead>
    <tr>
      <th>ID</th>
      <th>Username</th>
      <th>Full Name</th>
      <th>Role</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($user = $result->fetch_assoc()): ?>
    <tr>
      <td><?= $user['id'] ?></td>
      <td><?= htmlspecialchars($user['username']) ?></td>
      <td><?= htmlspecialchars($user['full_name']) ?></td>
      <td><?= ucfirst($user['role']) ?></td>
      <td>
        <button class="btn btn-primary btn-sm editUserBtn" 
          data-id="<?= $user['id'] ?>" 
          data-username="<?= htmlspecialchars($user['username']) ?>" 
          data-fullname="<?= htmlspecialchars($user['full_name']) ?>"
          data-role="<?= $user['role'] ?>"
          data-bs-toggle="modal" data-bs-target="#editUserModal">Edit</button>

        <button class="btn btn-danger btn-sm deleteUserBtn" data-id="<?= $user['id'] ?>">Delete</button>
      </td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="addUserForm" method="POST" action="user_actions.php">
      <input type="hidden" name="action" value="add">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add New User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label>Username</label>
            <input type="text" name="username" required class="form-control">
          </div>
          <div class="mb-3">
            <label>Full Name</label>
            <input type="text" name="full_name" required class="form-control">
          </div>
          <div class="mb-3">
            <label>Password</label>
            <input type="password" name="password" required class="form-control">
          </div>
          <div class="mb-3">
            <label>Role</label>
            <select name="role" required class="form-select">
              <option value="user" selected>User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Add User</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="editUserForm" method="POST" action="user_actions.php">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editUserId">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label>Username</label>
            <input type="text" name="username" id="editUsername" required class="form-control">
          </div>
          <div class="mb-3">
            <label>Full Name</label>
            <input type="text" name="full_name" id="editFullName" required class="form-control">
          </div>
          <div class="mb-3">
            <label>Password (leave blank to keep current)</label>
            <input type="password" name="password" class="form-control" placeholder="New password">
          </div>
          <div class="mb-3">
            <label>Role</label>
            <select name="role" id="editRole" required class="form-select">
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
  // Fill edit form on Edit button click
  document.querySelectorAll('.editUserBtn').forEach(button => {
    button.addEventListener('click', () => {
      document.getElementById('editUserId').value = button.getAttribute('data-id');
      document.getElementById('editUsername').value = button.getAttribute('data-username');
      document.getElementById('editFullName').value = button.getAttribute('data-fullname');
      document.getElementById('editRole').value = button.getAttribute('data-role');
    });
  });

  // Confirm delete
  document.querySelectorAll('.deleteUserBtn').forEach(button => {
    button.addEventListener('click', () => {
      if (confirm('Are you sure you want to delete this user?')) {
        const userId = button.getAttribute('data-id');
        window.location.href = `user_actions.php?action=delete&id=${userId}`;
      }
    });
  });
</script>
