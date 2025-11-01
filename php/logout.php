<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Clear all session data
session_unset();
// Destroy the session
session_destroy();
// Redirect to login page
header("Location: ../login.php");
exit();
