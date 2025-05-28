<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Database connection (same as above)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = (int)$_POST['item_id'];
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->execute([$itemId]);
}
header("Location: admin_inventory.php");
