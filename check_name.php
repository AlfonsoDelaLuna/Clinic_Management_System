<?php
header('Content-Type: application/json');

// Database connection (make sure this matches your database connection)
try {
    $db = new PDO("mysql:host=localhost;dbname=clinic_db", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
    exit();
}

if (isset($_POST['check_name'])) {
    $name = trim($_POST['check_name']);

    if (!empty($name)) {
        $stmt = $db->prepare("SELECT * FROM clinic_logs WHERE name LIKE ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute(["%" . $name . "%"]);
        $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_record) {
            // When role is Others, set the custom_role from specify_role
            if ($existing_record['role'] === 'Others') {
                $existing_record['custom_role'] = $existing_record['specify_role'];
                // Update course_section to be empty since we're using specify_role
                $existing_record['course_section'] = '';
            }
            echo json_encode($existing_record);
        } else {
            echo json_encode(['error' => 'Name not found']);
        }
    } else {
        echo json_encode(['error' => 'Name is empty']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
