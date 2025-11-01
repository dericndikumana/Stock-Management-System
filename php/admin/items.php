<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
$page = 'items_content.php';
include '../layout.php';
?>
