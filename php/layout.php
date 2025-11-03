<?php
// db_connect.php
$host = 'localhost';
$username = 'root';
$password = ''; // Set your password if needed
$database = 'stock_management';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// layout.php
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
$shop_name = "SMS Shop System";
$basePath = ($role === 'admin') ? '/sms/php/admin/' : '/sms/php/user/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>SMS Shop System | <?= ucfirst($role) ?></title>
  <!-- <title>Dashboard | <?= ucfirst($role) ?></title> -->
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="icon" href="/sms/sms.png" type="image/png" />

  <style>
  body.dark-mode {
    background-color: #121212 !important;
    color: #ffffff !important;
  }
  body.dark-mode .bg-white {
    background-color: #1e1e1e !important;
    color: #ffffff !important;
  }
  body.dark-mode .modal-content {
    background-color: #1e1e1e !important;
    color: #ffffff !important;
  }
  body.dark-mode input,
  body.dark-mode textarea,
  body.dark-mode select {
    background-color: #333 !important;
    color: #fff !important;
    border-color: #555;
  }
</style>
</head>
<body id="main-body" class="bg-gray-100 d-flex flex-column min-vh-100">

<!-- Navbar toggle for mobile -->
<nav class="d-md-none bg-dark text-white p-2 d-flex justify-content-between align-items-center">
  <span><strong><?= htmlspecialchars($shop_name) ?></strong></span>
  <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
    ☰
  </button>
</nav>

<div class="d-flex flex-grow-1" style="min-height: 100vh;">
  <!-- Sidebar for desktop -->
  <div class="bg-dark text-white p-3 d-none d-md-block" style="width: 250px;">
    <h4 class="mb-4"><?= htmlspecialchars($shop_name) ?></h4>
    <ul class="nav flex-column">
      <li class="nav-item mb-2"><a href="<?= $basePath ?>dashboard.php" class="nav-link text-white">🏠 Dashboard</a></li>
      <li class="nav-item mb-2 dropdown">
        <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown" class="btn btn-success">📦 Inventory</a>
        <ul class="dropdown-menu dropdown-menu-dark">
          <li><a class="dropdown-item" href="<?= $basePath ?>items.php" >🧾 Inventory List</a></li>
          <li><a class="dropdown-item" href="<?= $basePath ?>stock_in.php">📥 Receive Stock</a></li>
          <li><a class="dropdown-item" href="<?= $basePath ?>stock_out.php">📤 Sales</a></li>
        </ul>
      </li>
      <li class="nav-item mb-2"><a href="<?= $basePath ?>receipt_history.php" class="nav-link text-white">🧾 System Reports</a></li>
      <li class="nav-item mb-2"><a href="<?= $basePath ?>profile.php" class="nav-link text-white">⚙️ Settings</a></li>
      <li class="nav-item"><a href="/sms/php/logout.php" class="nav-link text-danger"> Logout</a></li>
    </ul>
  </div>

  <!-- Offcanvas Sidebar for mobile -->
  <div class="offcanvas offcanvas-start text-bg-dark" id="mobileSidebar">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title"><?= htmlspecialchars($shop_name) ?></h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
      <ul class="nav flex-column">
        <li class="nav-item mb-2"><a href="<?= $basePath ?>dashboard.php" class="nav-link text-white">🏠 Dashboard</a></li>
        <li class="nav-item mb-2 dropdown">
          <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown">📦 Inventory</a>
          <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="<?= $basePath ?>items.php">🧾 Inventory List</a></li>
            <li><a class="dropdown-item" href="<?= $basePath ?>stock_in.php">📥 Receive stock</a></li>
            <li><a class="dropdown-item" href="<?= $basePath ?>stock_out.php">📤 Sales</a></li>
          </ul>
        </li>
        <li class="nav-item mb-2"><a href="<?= $basePath ?>receipt_history.php" class="nav-link text-white">🧾 System Report</a></li>
        <li class="nav-item mb-2"><a href="<?= $basePath ?>profile.php" class="nav-link text-white">⚙️ Settings</a></li>
        <li class="nav-item"><a href="/sms/php/logout.php" class="nav-link text-danger"> Logout</a></li>
      </ul>
    </div>
  </div>

  <div class="flex-grow-1 p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="text-primary">Dashboard - <?= ucfirst($role) ?></h3>
      <span class="badge bg-secondary">👤 <?= htmlspecialchars($full_name) ?> | <?= htmlspecialchars($shop_name) ?></span>
    </div>

    <div class="bg-white shadow p-4 rounded">
      <?php
        if (isset($page) && basename($page) !== 'layout.php') {
            include $page;
        } else {
            echo "<p>No valid page specified.</p>";
        }
      ?>
    </div>
  </div>
</div>
<script>
  // Apply theme on page load
  document.addEventListener('DOMContentLoaded', () => {
    const theme = localStorage.getItem('theme') || 'light';
    const body = document.getElementById('main-body');
    if (theme === 'dark') {
      body.classList.add('dark-mode');
      document.querySelector('#darkModeToggle')?.setAttribute('checked', true);
    }
  });

  // Listen to toggle switch
  function toggleDarkMode(checkbox) {
    const body = document.getElementById('main-body');
    if (checkbox.checked) {
      body.classList.add('dark-mode');
      localStorage.setItem('theme', 'dark');
    } else {
      body.classList.remove('dark-mode');
      localStorage.setItem('theme', 'light');
    }
  }
</script>

<style>
  .dark-mode {
    background-color: #121212 !important;
    color: #ffffff !important;
  }

  .dark-mode .bg-white {
    background-color: #1e1e1e !important;
  }

  .dark-mode .text-dark {
    color: #ffffff !important;
  }

  .dark-mode .table {
    background-color: #1e1e1e !important;
    color: #ffffff;
  }

  .dark-mode .table th {
    background-color: #2e2e2e !important;
  }

  .dark-mode .btn {
    background-color: #333 !important;
    color: #fff !important;
  }
</style>
<script>
  if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark-mode');
    const toggle = document.getElementById('darkModeToggle');
    if (toggle) toggle.checked = true;
  }

  function toggleDarkMode(el) {
    if (el.checked) {
      document.body.classList.add('dark-mode');
      localStorage.setItem('theme', 'dark');
    } else {
      document.body.classList.remove('dark-mode');
      localStorage.setItem('theme', 'light');
    }
  }

  const modeToggle = document.getElementById('darkModeToggle');
  if (modeToggle) {
    modeToggle.addEventListener('change', function () {
      toggleDarkMode(this);
    });
  }
</script>

<footer class="text-center p-3 bg-dark text-white mt-auto">
  &copy; <?= date('Y') ?> Developed by Deric | All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
