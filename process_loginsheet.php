<?php
session_start();

// Set timezone to ensure consistent time
date_default_timezone_set('Asia/Manila');

// Kick out non-admins or non-guests
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'guest'])) {
    header("Location: login.php");
    exit();
}

// Where we bounce back or forward
$error_redirect = $_SESSION['role'] === 'admin' ? 'admin_loginsheet.php' : 'guest_loginsheet.php';
$success_redirect = $_SESSION['role'] === 'admin' ? 'admin_history.php' : 'guest_confirmation.php';

// Hook up to the DB
try {
    $db = new PDO("mysql:host=localhost;dbname=clinic_db", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB’s down: " . $e->getMessage());
}

// Get patient_id from POST
$patient_id = trim($_POST['patient_id'] ?? '');

// Check if patient exists
$patient_exists = false;
if ($patient_id && !in_array(strtolower($patient_id), ['na', 'n/a'])) {
    $stmt = $db->prepare("SELECT id FROM clinic_logs WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $patient_exists = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

// Validation function
function validateInput($data, $field, $patient_exists = false)
{
    $errors = [];
    $naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];

    if ($field === 'patient_id') {
        $data = trim($data);
        // Patient ID is optional, but if provided, it must match the format or be NA
        if (!empty($data) && !preg_match('/^(?:\d{11}|[A-Z]{3}\d{3}[A-Z]|[A-Z]{3}\d{4}[A-Z]|[A-Z]{3}\d{4})$/i', $data) && !in_array($data, $naValues)) {
            $errors[] = "Patient ID must be 11 digits (e.g., 02000123456), or formats like CLN012A, CLN0123A, CLN0123, or NA.";
        }
    }
    if ($field === 'name') {
        $data = trim($data);
        if (empty($data) || !preg_match('/^[A-Za-z\s]{2,50}$/', $data)) {
            $errors[] = "Name must contain only letters and spaces, 2-50 chars.";
        }
    }
    if ($field === 'birthday') {
        $data = trim($data);
        if (empty($data)) {
            $errors[] = "Birthday is required.";
        } else {
            $birthDate = new DateTime($data);
            $today = new DateTime();
            if ($birthDate > $today) {
                $errors[] = "Birthday cannot be in the future.";
            }
        }
    }
    if ($field === 'age') {
        $data = (int) $data;
        if (empty($data) || $data < 1 || $data > 100) {
            $errors[] = "Age must be between 1 and 100.";
        }
    }
    if ($field === 'gender') {
        $data = trim($data);
        if (!$patient_exists && (empty($data) || !in_array($data, ['Male', 'Female']))) {
            $errors[] = "Gender must be Male or Female.";
        }
    }
    if ($field === 'role') {
        $data = trim($data);
        if (empty($data)) {
            $errors[] = "Role is required.";
        }
    }
    if ($field === 'course_section') {
        $data = trim($data);
        $role = trim($_POST['role'] ?? '');
        if (strtolower($role) === 'student' && (empty($data) || !preg_match('/^[A-Za-z0-9\s\-\/]{2,20}$/', $data))) {
            $errors[] = "Course and Section must be 2-20 chars, letters, numbers, spaces, dashes, or slashes.";
        }
    }
    if ($field === 'blood_pressure') {
        $data = trim($data);
        if (!empty($data) && !preg_match('/^\d{2,3}\/\d{2,3}$/', $data) && !in_array($data, $naValues)) {
            $errors[] = "Blood Pressure must be in format like 120/80 or NA.";
        }
    }
    if ($field === 'heart_rate') {
        $data = trim($data);
        if (!empty($data) && !in_array($data, $naValues)) {
            if (!preg_match('/^\d+$/', $data)) {
                $errors[] = "Heart Rate must be a number or NA.";
            } elseif ($data < 1 || $data > 200) {
                $errors[] = "Heart Rate must be between 1 and 200.";
            }
        }
    }
    if ($field === 'blood_oxygen') {
        $data = trim($data);
        if (!empty($data) && !in_array($data, $naValues)) {
            if (!preg_match('/^\d+$/', $data)) {
                $errors[] = "Blood Oxygen must be a number or NA.";
            } elseif ($data < 1 || $data > 100) {
                $errors[] = "Blood Oxygen must be between 1 and 100.";
            }
        }
    }
    if ($field === 'height') {
        $data = trim($data);
        if (!empty($data) && !in_array($data, $naValues)) {
            if (!preg_match('/^\d+(\.\d+)?$/', $data)) {
                $errors[] = "Height must be a number or NA.";
            } elseif ($data < 1 || $data > 251) {
                $errors[] = "Height must be between 1 and 251 cm.";
            }
        }
    }
    if ($field === 'weight') {
        $data = trim($data);
        if (!empty($data) && !in_array($data, $naValues)) {
            if (!preg_match('/^\d+(\.\d+)?$/', $data)) {
                $errors[] = "Weight must be a number or NA.";
            } elseif ($data < 1 || $data > 500) {
                $errors[] = "Weight must be between 1 and 500 kg.";
            }
        }
    }
    if ($field === 'temperature') {
        $data = trim($data);
        if (!empty($data) && !in_array($data, $naValues)) {
            if (!preg_match('/^\d+(\.\d+)?$/', $data)) {
                $errors[] = "Temperature must be a number or NA.";
            } elseif ($data < 1 || $data > 100) {
                $errors[] = "Temperature must be between 1 and 100°C.";
            }
        }
    }
    if ($field === 'time_out') {
        $data = trim($data);
        if (!empty($data) && !preg_match('/^\d{2}:\d{2}$/', $data)) {
            $errors[] = "Time Out must be in HH:MM format (e.g., 14:30).";
        }
    }
    if ($field === 'purpose_of_visit') {
        $data = trim($data);
        if (empty($data) || !preg_match('/^[A-Za-z0-9\s,\-]{2,100}$/', $data)) {
            $errors[] = "Purpose of Visit must be 2-100 chars, letters, numbers, spaces, commas, or dashes.";
        }
    }
    if ($field === 'health_history') {
        $data = trim($data);
        if (empty($data) || !preg_match('/^[A-Za-z0-9\s,\-]{2,100}$/', $data)) {
            $errors[] = "Health History must be 2-100 chars, letters, numbers, spaces, commas, or dashes.";
        }
    }
    if ($field === 'medicine') {
        global $db;
        $data = trim($data);
        if (!empty($data)) {
            $stmt = $db->prepare("SELECT name FROM inventory WHERE name = ? AND remaining_items > 0");
            $stmt->execute([$data]);
            if (!$stmt->fetch()) {
                $errors[] = "Selected medicine isn’t available or out of stock.";
            }
        }
    }
    if ($field === 'quantity') {
        global $db;
        $data = (int) $data;
        $medicine = trim($_POST['medicine'] ?? '');
        if (!empty($medicine)) {
            if ($data < 1) {
                $errors[] = "Quantity must be at least 1.";
            } else {
                $stmt = $db->prepare("SELECT remaining_items FROM inventory WHERE name = ?");
                $stmt->execute([$medicine]);
                $inventory = $stmt->fetch();
                if ($inventory && $data > $inventory['remaining_items']) {
                    $errors[] = "Quantity can’t exceed stock (" . $inventory['remaining_items'] . ").";
                }
            }
        }
    }
    if ($field === 'remarks') {
        $data = trim($data);
        if (empty($data) || !preg_match('/^[A-Za-z0-9\s,\-\.]{2,200}$/', $data)) {
            $errors[] = "Remarks must be 2-200 chars, letters, numbers, spaces, commas, dashes, or periods.";
        }
    }
    return $errors;
}

// Fields to validate
$fields = [
    'patient_id',
    'name',
    'birthday',
    'gender',
    'age',
    'role',
    'course_section',
    'blood_pressure',
    'heart_rate',
    'blood_oxygen',
    'height',
    'weight',
    'temperature',
    'time_out',
    'purpose_of_visit',
    'health_history',
    'medicine',
    'quantity',
    'remarks'
];

// Validate inputs
$errors = [];
foreach ($fields as $field) {
    $value = $_POST[$field] ?? '';
    $fieldErrors = validateInput($value, $field, $patient_exists);
    if (!empty($fieldErrors)) {
        $errors[$field] = $fieldErrors;
    }
}

// Additional validation for patient_id or name requirement
if (empty(trim($_POST['patient_id'] ?? '')) && empty(trim($_POST['name'] ?? ''))) {
    $errors['patient_id'] = $errors['patient_id'] ?? [];
    $errors['patient_id'][] = "Either Patient ID or Name is required.";
}

if (!empty($errors)) {
    error_log("Validation errors: " . json_encode($errors));
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header("Location: $error_redirect");
    exit();
}

// Prepare data for insertion
$naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];
$data = [
    'patient_id' => in_array(trim($_POST['patient_id'] ?? ''), $naValues) ? 'NA' : (trim($_POST['patient_id'] ?? '') ?: null),
    'name' => trim($_POST['name']),
    'birthday' => $_POST['birthday'] ? trim($_POST['birthday']) : null,
    'gender' => trim($_POST['gender']),
    'age' => (int) $_POST['age'],
    'role' => trim($_POST['role']),
    'course_section' => strtolower(trim($_POST['role'] ?? '')) === 'student' ? trim($_POST['course_section']) : trim($_POST['role']),
    'blood_pressure' => in_array(trim($_POST['blood_pressure'] ?? ''), $naValues) ? 'NA' : ($_POST['blood_pressure'] && preg_match('/^\d{2,3}\/\d{2,3}$/', trim($_POST['blood_pressure'])) ? trim($_POST['blood_pressure']) : null),
    'heart_rate' => in_array(trim($_POST['heart_rate'] ?? ''), $naValues) ? 'NA' : ($_POST['heart_rate'] && is_numeric($_POST['heart_rate']) && $_POST['heart_rate'] >= 1 && $_POST['heart_rate'] <= 200 ? (int) $_POST['heart_rate'] : null),
    'blood_oxygen' => in_array(trim($_POST['blood_oxygen'] ?? ''), $naValues) ? 'NA' : ($_POST['blood_oxygen'] && is_numeric($_POST['blood_oxygen']) && $_POST['blood_oxygen'] >= 1 && $_POST['blood_oxygen'] <= 100 ? (int) $_POST['blood_oxygen'] : null),
    'height' => in_array(trim($_POST['height'] ?? ''), $naValues) ? 'NA' : ($_POST['height'] && is_numeric($_POST['height']) && $_POST['height'] >= 1 && $_POST['height'] <= 251 ? (float) $_POST['height'] : null),
    'weight' => in_array(trim($_POST['weight'] ?? ''), $naValues) ? 'NA' : ($_POST['weight'] && is_numeric($_POST['weight']) && $_POST['weight'] >= 1 && $_POST['weight'] <= 500 ? (float) $_POST['weight'] : null),
    'temperature' => in_array(trim($_POST['temperature'] ?? ''), $naValues) ? 'NA' : ($_POST['temperature'] && is_numeric($_POST['temperature']) && $_POST['temperature'] >= 1 && $_POST['temperature'] <= 100 ? (float) $_POST['temperature'] : null),
    'time_out' => $_POST['time_out'] ? trim($_POST['time_out']) : null,
    'purpose_of_visit' => $_POST['purpose_of_visit'] ? trim($_POST['purpose_of_visit']) : null,
    'health_history' => $_POST['health_history'] ? trim($_POST['health_history']) : null,
    'medicine' => $_SESSION['role'] === 'admin' && $_POST['medicine'] ? trim($_POST['medicine']) : null,
    'quantity' => $_SESSION['role'] === 'admin' && $_POST['quantity'] ? (int) $_POST['quantity'] : null,
    'remarks' => $_POST['remarks'] ? trim($_POST['remarks']) : null,
    'date' => date('Y-m-d'),
    'time_in' => date('H:i')
];

// Log the data being processed
error_log("Processing data: " . json_encode($data));

// Start transaction
try {
    $db->beginTransaction();

    // Check if patient exists
    $stmt = $db->prepare("SELECT id FROM clinic_logs WHERE patient_id = ?");
    $stmt->execute([$data['patient_id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // Inventory update for admins
    if ($_SESSION['role'] === 'admin' && $data['medicine'] && $data['quantity']) {
        $stmt = $db->prepare("UPDATE inventory SET remaining_items = remaining_items - ?, quantity = quantity + ? WHERE name = ? AND remaining_items >= ?");
        $stmt->execute([$data['quantity'], $data['quantity'], $data['medicine'], $data['quantity']]);
        if ($stmt->rowCount() == 0) {
            throw new Exception("Not enough stock for {$data['medicine']}.");
        }
    }

    if ($existing) {
        // Update existing record
        $stmt = $db->prepare("
            UPDATE clinic_logs
            SET
                blood_pressure = ?,
                heart_rate = ?,
                blood_oxygen = ?,
                height = ?,
                weight = ?,
                temperature = ?,
                time_out = ?,
                purpose_of_visit = ?,
                health_history = ?,
                medicine = ?,
                quantity = ?,
                remarks = ?,
                date = ?,
                time_in = ?
            WHERE patient_id = ?
        ");
        $stmt->execute([
            $data['blood_pressure'],
            $data['heart_rate'],
            $data['blood_oxygen'],
            $data['height'],
            $data['weight'],
            $data['temperature'],
            $data['time_out'],
            $data['purpose_of_visit'],
            $data['health_history'],
            $data['medicine'],
            $data['quantity'],
            $data['remarks'],
            $data['date'],
            $data['time_in'],
            $data['patient_id']
        ]);
        error_log("Updated clinic_logs for patient_id: " . ($data['patient_id'] ?? 'NULL'));
    } else {
        // Insert new record
        $stmt = $db->prepare("
            INSERT INTO clinic_logs (patient_id, name, birthday, gender, age, role, course_section, blood_pressure, heart_rate, blood_oxygen, height, weight, temperature, time_out, purpose_of_visit, health_history, medicine, quantity, remarks, date, time_in)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['patient_id'],
            $data['name'],
            $data['birthday'],
            $data['gender'],
            $data['age'],
            $data['role'],
            $data['course_section'],
            $data['blood_pressure'],
            $data['heart_rate'],
            $data['blood_oxygen'],
            $data['height'],
            $data['weight'],
            $data['temperature'],
            $data['time_out'],
            $data['purpose_of_visit'],
            $data['health_history'],
            $data['medicine'],
            $data['quantity'],
            $data['remarks'],
            $data['date'],
            $data['time_in']
        ]);
        error_log("Inserted into clinic_logs for patient_id: " . ($data['patient_id'] ?? 'NULL'));
    }

    // Insert into admin_history
    $stmt = $db->prepare("
        INSERT INTO admin_history (patient_id, name, date, time_in, time_out, medicine, quantity, health_history, purpose_of_visit, remarks, blood_pressure, heart_rate, blood_oxygen, height, weight, temperature)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['patient_id'],
        $data['name'],
        $data['date'],
        $data['time_in'],
        $data['time_out'],
        $data['medicine'],
        $data['quantity'],
        $data['health_history'],
        $data['purpose_of_visit'],
        $data['remarks'],
        $data['blood_pressure'],
        $data['heart_rate'],
        $data['blood_oxygen'],
        $data['height'],
        $data['weight'],
        $data['temperature']
    ]);
    error_log("Inserted into admin_history for patient_id: " . ($data['patient_id'] ?? 'NULL'));

    $db->commit();
    error_log("Transaction committed for patient_id: " . ($data['patient_id'] ?? 'NULL'));
    if ($_SESSION['role'] === 'guest') {
        $_SESSION['form_submitted'] = true;
        header("Location: guest_confirmation.php");
    } else {
        header("Location: $success_redirect");
    }
    exit();
} catch (Exception $e) {
    $db->rollBack();
    error_log("Transaction failed: " . $e->getMessage());
    $_SESSION['form_errors'] = ['database' => [$e->getMessage()]];
    $_SESSION['form_data'] = $_POST;
    header("Location: $error_redirect");
    exit();
}
