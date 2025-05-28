<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

header('Content-Type: application/json');
require 'db_connect.php';

try {
    $query = "SELECT 
        cl.id,
        cl.patient_id,
        cl.name,
        cl.gender,
        cl.age,
        cl.role,
        CASE 
            WHEN cl.role = 'Others' THEN cl.specify_role 
            ELSE cl.course_section 
        END as course_section,
        cl.blood_pressure,
        cl.heart_rate,
        cl.blood_oxygen,
        cl.height,
        cl.weight,
        cl.temperature,
        cl.time_in,
        cl.time_out,
        cl.purpose_of_visit,
        cl.health_history,
        cl.medicine,
        cl.quantity,
        cl.remarks,
        DATE_FORMAT(cl.date, '%Y-%m-%d') as date,
        DATE_FORMAT(cl.birthday, '%Y-%m-%d') as birthday,
        cl.created_at
    FROM clinic_logs cl";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['data' => $results]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
