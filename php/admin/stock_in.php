<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$title = "📥 Stock In";

// Use absolute path to content file
$page = __DIR__ . '/stock_in_content.php';

// Include the main layout which will include $page inside the content area
include '../layout.php';

