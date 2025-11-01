<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: php/admin/dashboard.php");
    } else {
        header("Location: php/user/dashboard.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Login | SMS Shop System </title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  
   <link rel="icon" href="/sms/sms.png" type="image/png" />
</head>
<body class="bg-light d-flex justify-content-center align-items-center vh-100">
  <div class="bg-white shadow p-4 rounded w-100" style="max-width: 400px;">
    <h2 class="text-center mb-4">🔐 Login to SMS</h2>
    <form action="php/login_process.php" method="POST">
      <div class="mb-3">
        <label>Username</label>
        <input type="text" name="username" class="form-control" required />
      </div>
      <div class="mb-3">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required />
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <div class="text-center mt-3">
      <small>Don't have an account? <a href="signup.php">Sign up</a></small>
    </div>
  </div>
</body>
</html>
    