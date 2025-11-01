<?php
// File: /php/user/profile_content.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../../php/db_connect.php';

$user_id = $_SESSION['user_id'];

// Fetch user info including role and password (plain text as requested)
$stmt = $conn->prepare("SELECT full_name, username, password, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($full_name, $username, $password, $role);
$stmt->fetch();
$stmt->close();
?>

<?php
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
  <h4>👤 My Profile</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">Edit Profile</button>
</div>

<div class="mb-4">
  <p><strong>Full Name:</strong> <span id="displayFullName"><?= htmlspecialchars($full_name) ?></span></p>
  <p><strong>Username:</strong> <span id="displayUsername"><?= htmlspecialchars($username) ?></span></p>
  <p><strong>Role:</strong> <span id="displayRole"><?= htmlspecialchars($role) ?></span></p>
</div>

<div class="form-check form-switch">
  <input class="form-check-input" type="checkbox" id="darkModeToggle">
  <label class="form-check-label" for="darkModeToggle">Dark Mode</label>
</div>


<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="update_profile.php" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Update Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($full_name) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($username) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="text" name="password" class="form-control" placeholder="Leave blank to keep current password">
        </div>
        <div class="mb-3">
          <label class="form-label">Role (cannot be changed)</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($role) ?>" readonly>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Save</button>
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
    // Update all texts and modal backgrounds to white/dark
    document.querySelectorAll('input, label, p, span, .modal-content, .form-control').forEach(el => {
      el.classList.add('text-white');
      if (el.classList.contains('form-control')) {
        el.style.backgroundColor = '#343a40'; // dark background for inputs
        el.style.color = 'white';
      } else {
        el.style.backgroundColor = '';
      }
    });
  } else {
    document.body.classList.add('bg-light', 'text-dark');
    document.body.classList.remove('bg-dark', 'text-white');
    document.querySelectorAll('input, label, p, span, .modal-content, .form-control').forEach(el => {
      el.classList.remove('text-white');
      if (el.classList.contains('form-control')) {
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

// Initialize on page load
window.addEventListener('DOMContentLoaded', () => {
  toggleDarkMode(darkToggle.checked);
});
</script>
