<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$page = 'receipt_history_content.php'; // ✅ This sets the variable expected by layout.php
include '../../php/layout.php'; // ✅ Adjusted to point correctly to layout file
?>