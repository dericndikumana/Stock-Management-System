<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$page_title = "Stock Out";
$page = __DIR__ . '/stock_out_content.php';  // IMPORTANT: path to content

include '../../php/db_connect.php';
include '../layout.php';
