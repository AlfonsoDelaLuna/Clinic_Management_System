<?php
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Include the database connection
include 'db_connect.php';

// Retrieve and sanitize form data
$student_number = htmlspecialchars($_POST['student_number']);
$name = htmlspecialchars($_POST['name']);
$gender = htmlspecialchars($_POST['gender']);
$age = htmlspecialchars($_POST['age']);
$role = htmlspecialchars($_POST['role']);
$course_section = htmlspecialchars($_POST['course_section']);
$blood_pressure = htmlspecialchars($_POST['blood_pressure']);
$heart_rate = htmlspecialchars($_POST['heart_rate']);
$blood_oxygen = htmlspecialchars($_POST['blood_oxygen']);
$height = htmlspecialchars($_POST['height']);
$weight = htmlspecialchars($_POST['weight']);
$temperature = htmlspecialchars($_POST['temperature']);
$time_out = htmlspecialchars($_POST['time_out']);
$sickness = htmlspecialchars($_POST['sickness']);
$purpose_of_visit = htmlspecialchars($_POST['purpose_of_visit']);
$health_history = htmlspecialchars($_POST['health_history']);
$medicine = htmlspecialchars($_POST['medicine']);
$quantity = htmlspecialchars($_POST['quantity']);
$remarks = htmlspecialchars($_POST['remarks']);

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
