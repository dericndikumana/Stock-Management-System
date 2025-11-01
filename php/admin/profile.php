<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$page = 'admin/profile_content.php'; // Make sure this is relative from layout.php
include '../layout.php';
