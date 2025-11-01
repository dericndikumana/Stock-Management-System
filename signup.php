<?php
session_start();
include 'php/db_connect.php';

$message = '';

// ✅ Only allow logged-in admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<h3>❌ Access denied. Only administrators can create accounts.</h3>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];  // Plain text password (manual)
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];

    if (empty($username) || empty($password) || empty($full_name) || empty($role)) {
        $message = "⚠️ All fields are required.";
    } else {
        // Check if username exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "❌ Username already exists.";
        } else {
            // Create new user
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $password, $full_name, $role);
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;

                // Set default settings (light theme)
                $settings = $conn->prepare("INSERT INTO user_settings (user_id, theme_mode) VALUES (?, 'light')");
                $settings->bind_param("i", $new_user_id);
                $settings->execute();

                $message = "✅ User created successfully!";
            } else {
                $message = "❌ Error inserting user.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create User | SMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width: 450px;">
  <h3 class="mb-3">👤 Create New Account</h3>

  <?php if (!empty($message)): ?>
    <div class="alert alert-info"><?= $message ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="mb-3">
      <label>Full Name</label>
      <input type="text" name="full_name" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Username</label>
      <input type="text" name="username" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Password (Manual)</label>
      <input type="text" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Role</label>
      <select name="role" class="form-select" required>
        <option value="user">Normal User</option>
        <option value="admin">Administrator</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary w-100">➕ Register</button>
  </form>
</div>
</body>
</html>
