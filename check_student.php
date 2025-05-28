<?php
session_start();
header('Content-Type: application/json');

try {
    $db = new PDO("mysql:host=localhost;dbname=clinic_db", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
    exit();
}

if (isset($_POST['check_patient_id'])) {
    $patient_id = trim($_POST['check_patient_id']);

    if (!empty($patient_id)) {
        $stmt = $db->prepare("SELECT * FROM clinic_logs WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$patient_id]);
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
            echo json_encode(['error' => 'ID not found']);
        }
    } else {
        echo json_encode(['error' => 'ID is empty']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
