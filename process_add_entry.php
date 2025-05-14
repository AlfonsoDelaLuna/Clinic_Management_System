<?php
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Include the database connection
include 'db_connection.php';

// Retrieve form data
$student_number = $_POST['student_number'];
$name = $_POST['name'];
$gender = $_POST['gender'];
$age = $_POST['age'];
$role = $_POST['role'];
$course_section = $_POST['course_section'];
$blood_pressure = $_POST['blood_pressure'];
$heart_rate = $_POST['heart_rate'];
$blood_oxygen = $_POST['blood_oxygen'];
$height = $_POST['height'];
$weight = $_POST['weight'];
$temperature = $_POST['temperature'];
$time_out = $_POST['time_out'];
$sickness = $_POST['sickness'];
$purpose_of_visit = $_POST['purpose_of_visit'];
$health_history = $_POST['health_history'];
$medicine = $_POST['medicine'];
$quantity = $_POST['quantity'];
$remarks = $_POST['remarks'];

// Prepare and execute the SQL statement
$sql = "INSERT INTO loginsheet (student_number, name, gender, age, role, course_section, blood_pressure, heart_rate, blood_oxygen, height, weight, temperature, time_out, sickness, purpose_of_visit, health_history, medicine, quantity, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("sssisssssiiissssssi", $student_number, $name, $gender, $age, $role, $course_section, $blood_pressure, $heart_rate, $blood_oxygen, $height, $weight, $temperature, $time_out, $sickness, $purpose_of_visit, $health_history, $medicine, $quantity, $remarks);

if ($stmt->execute()) {
    echo "New entry added successfully.";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

header("Location: admin_dashboard.php");
exit();
