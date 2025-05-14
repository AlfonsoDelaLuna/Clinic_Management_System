<?php
session_start();
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Fetch user from database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
    $stmt->execute([$username, $role]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        if ($role === 'admin') {
            header('Location: admin_history.php');
        } else {
            header('Location: guest_loginsheet.php');
        }
        exit;
    } else {
        // Set error message in session and redirect back to index.php
        $_SESSION['error'] = "Invalid credentials!";
        header('Location: index.php');
        exit;
    }
}
