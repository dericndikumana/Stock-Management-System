<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../../login.php');
    exit();
}

include '../db_connect.php';

$user_id = $_SESSION['user_id'];

$page = 'items_content.php'; // now use this relative path
include '../layout.php';
