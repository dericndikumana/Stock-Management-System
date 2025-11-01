<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../../login.php');
    exit();
}

$page = "dashboard_content.php";  // content file inside the same folder
include "../layout.php";
