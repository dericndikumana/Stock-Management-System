<?php
session_start();

if ($_SESSION['role'] === 'admin') {
    if (isset($_POST['view_user_id']) && is_numeric($_POST['view_user_id'])) {
        $_SESSION['view_user_id'] = intval($_POST['view_user_id']);
    } else {
        unset($_SESSION['view_user_id']); // Reset
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
