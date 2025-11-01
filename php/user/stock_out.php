<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$page = 'stock_out_content.php'; // used in layout to include content

include '../layout.php';  // Adjust path to your layout.php accordingly
