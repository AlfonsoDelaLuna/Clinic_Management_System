<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

try {
    $db = new PDO("mysql:host=localhost;dbname=clinic_db", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch medicine and quantity from clinic_logs before clearing
    $stmt = $db->query("SELECT medicine, quantity FROM clinic_logs WHERE medicine IS NOT NULL AND quantity > 0");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Delete all records from clinic_logs
    $stmt = $db->prepare("DELETE FROM clinic_logs");
    $stmt->execute();

    // Process each log entry to update inventory
    foreach ($logs as $log) {
        $medicine = $log['medicine'];
        $quantity = (int)$log['quantity'];

        // Check if medicine exists in inventory
        $stmt = $db->prepare("SELECT quantity, remaining_items FROM inventory WHERE name = ?");
        $stmt->execute([$medicine]);
        $inventoryItem = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    echo "success";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
