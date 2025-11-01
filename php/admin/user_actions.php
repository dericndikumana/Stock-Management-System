<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit();
}

include '../db_connect.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'add') {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    // Simple duplicate username check
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo "<script>alert('Username already exists!'); window.history.back();</script>";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO users (username, full_name, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $full_name, $password, $role);
    $stmt->execute();

    header("Location: users.php");
    exit();

} elseif ($action === 'edit') {
    $id = intval($_POST['id']);
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    // Check duplicate username except current user
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check->bind_param("si", $username, $id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo "<script>alert('Username already exists!'); window.history.back();</script>";
        exit();
    }

    if ($password === '') {
        // Update without changing password
        $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, role=? WHERE id=?");
        $stmt->bind_param("sssi", $username, $full_name, $role, $id);
    } else {
        // Update with new password
        $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, password=?, role=? WHERE id=?");
        $stmt->bind_param("ssssi", $username, $full_name, $password, $role, $id);
    }
    $stmt->execute();

    header("Location: users.php");
    exit();

} elseif ($action === 'delete') {
    $id = intval($_GET['id']);

    // Prevent deleting own admin account
    if ($id == $_SESSION['user_id']) {
        echo "<script>alert('You cannot delete your own account!'); window.location.href='users.php';</script>";
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: users.php");
    exit();

} else {
    header("Location: users.php");
    exit();
}
