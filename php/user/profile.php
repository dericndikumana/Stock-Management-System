<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Tell layout.php which content page to load inside the layout
$page = 'user/profile_content.php';

include '../layout.php';
