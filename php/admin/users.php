<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit();
}

include '../db_connect.php';

$page = 'users_content.php'; // This file will have the main HTML for user management
include '../layout.php';
