<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

try {
    $db = new PDO("mysql:host=localhost;dbname=clinic_db", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$errors = [];
$form_data = $_POST;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request method.";
    exit();
}

$id = $_POST['id'] ?? '';
if (!is_numeric($id)) {
    $errors['id'][] = 'Invalid ID.';
}

$stmt = $db->prepare("SELECT * FROM clinic_logs WHERE id = ?");
$stmt->execute([$id]);
$existing_record = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$existing_record) {
    $errors['id'][] = 'Record not found.';
}

// Sanitize and validate fields
$student_number = htmlspecialchars(trim($_POST['student_number'] ?? ''));
if (!preg_match('/^\d{11}$/', $student_number)) {
    $errors['student-number'][] = 'Student Number must be 11 digits.';
} elseif ($student_number !== $existing_record['student_number']) {
    $errors['student-number'][] = 'Student Number cannot be changed.';
}

$name = htmlspecialchars(trim($_POST['name'] ?? ''));
if (!preg_match('/^[A-Za-z\s,\.]{2,50}$/', $name)) {
    $errors['name'][] = 'Name must be 2-50 characters, letters, spaces, commas, or dots only.';
}

$gender = htmlspecialchars($_POST['gender'] ?? '');
if (!in_array($gender, ['Male', 'Female', 'Other'])) {
    $errors['gender'][] = 'Gender must be Male, Female, or Other.';
}

$age = htmlspecialchars($_POST['age'] ?? '');
if (!is_numeric($age) || $age < 1 || $age > 100) {
    $errors['age'][] = 'Age must be between 1 and 100.';
}

$role = htmlspecialchars($_POST['role'] ?? '');
if (!in_array($role, ['Student', 'Faculty', 'Staff'])) {
    $errors['role'][] = 'Role must be Student, Faculty, or Staff.';
}

$course_section = htmlspecialchars(trim($_POST['course_section'] ?? ''));
if ($role === 'Student' && !preg_match('/^[A-Za-z0-9\-]{2,10}$/', $course_section)) {
    $errors['course-section'][] = 'Course and Section must be 2-10 characters (e.g., BT-705).';
}

$blood_pressure = htmlspecialchars(trim($_POST['blood_pressure'] ?? ''));
if ($blood_pressure && !preg_match('/^\d{2,3}\/\d{2,3}$/', $blood_pressure)) {
    $errors['blood-pressure'][] = 'Blood Pressure must be in 120/80 format.';
}

$heart_rate = htmlspecialchars($_POST['heart_rate'] ?? '');
if ($heart_rate && (!is_numeric($heart_rate) || $heart_rate < 1 || $heart_rate > 200)) {
    $errors['heart-rate'][] = 'Heart Rate must be between 1 and 200 bpm.';
}

$blood_oxygen = htmlspecialchars($_POST['blood_oxygen'] ?? '');
if ($blood_oxygen && (!is_numeric($blood_oxygen) || $blood_oxygen < 1 || $blood_oxygen > 100)) {
    $errors['blood-oxygen'][] = 'Blood Oxygen must be between 1 and 100%.';
}

$height = htmlspecialchars($_POST['height'] ?? '');
if ($height && (!is_numeric($height) || $height < 1 || $height > 251)) {
    $errors['height'][] = 'Height must be between 1 and 251 cm.';
}

$weight = htmlspecialchars($_POST['weight'] ?? '');
if ($weight && (!is_numeric($weight) || $weight < 1)) {
    $errors['weight'][] = 'Weight must be a positive number.';
}

$temperature = htmlspecialchars($_POST['temperature'] ?? '');
if ($temperature && (!is_numeric($temperature) || $temperature < 1 || $temperature > 100)) {
    $errors['temperature'][] = 'Temperature must be between 1 and 100°C.';
}

$time_out = htmlspecialchars($_POST['time_out'] ?? '');
if ($time_out && !preg_match('/^\d{2}:\d{2}$/', $time_out)) {
    $errors['time-out'][] = 'Time Out must be in 24-hour format (e.g., 14:30).';
}

$sickness = htmlspecialchars(trim($_POST['sickness'] ?? ''));
if ($sickness && !preg_match('/^[A-Za-z0-9\s]{2,50}$/', $sickness)) {
    $errors['sickness'][] = 'Sickness must be 2-50 characters, letters, numbers, spaces.';
}

$purpose_of_visit = htmlspecialchars(trim($_POST['purpose_of_visit'] ?? ''));
if ($purpose_of_visit && !preg_match('/^[A-Za-z0-9\s]{2,100}$/', $purpose_of_visit)) {
    $errors['purpose_of_visit'][] = 'Purpose of Visit must be 2-100 characters, letters, numbers, spaces.';
}

$health_history = htmlspecialchars(trim($_POST['health_history'] ?? ''));
if ($health_history && !preg_match('/^[A-Za-z0-9\s]{2,100}$/', $health_history)) {
    $errors['health_history'][] = 'Health History must be 2-100 characters, letters, numbers, spaces.';
}

$medicine = htmlspecialchars(trim($_POST['medicine'] ?? ''));
if ($medicine && !preg_match('/^[A-Za-z0-9\s]{2,50}$/', $medicine)) {
    $errors['medicine'][] = 'Medicine must be 2-50 characters, letters, numbers, spaces.';
}

$quantity = htmlspecialchars($_POST['quantity'] ?? '');
if ($quantity && (!is_numeric($quantity) || $quantity < 1)) {
    $errors['quantity'][] = 'Quantity must be a positive number.';
}

$remarks = htmlspecialchars(trim($_POST['remarks'] ?? ''));
if ($remarks && !preg_match('/^[A-Za-z0-9\s]{2,200}$/', $remarks)) {
    $errors['remarks'][] = 'Remarks must be 2-200 characters, letters, numbers, spaces.';
}

if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'errors' => $errors]);
    exit();
}

// Define original and new medicine and quantity for inventory adjustment
$original_medicine = $existing_record['medicine'] ?? null;
$original_quantity = $existing_record['quantity'] ? (int) $existing_record['quantity'] : 0;
$new_medicine = trim($_POST['medicine'] ?? '');
$new_quantity = $_POST['quantity'] ? (int) $_POST['quantity'] : 0;

// Define field groups
$print_preview_fields = ['blood_pressure', 'heart_rate', 'blood_oxygen', 'height', 'weight', 'temperature', 'time_out', 'sickness', 'purpose_of_visit', 'health_history', 'medicine', 'quantity', 'remarks'];
$dashboard_fields = ['time_out', 'sickness', 'health_history', 'purpose_of_visit', 'remarks'];
$history_fields = ['medicine', 'quantity', 'sickness', 'health_history', 'purpose_of_visit', 'remarks'];

// Detect changed fields
$changed_fields = [];
foreach ($_POST as $key => $value) {
    $existing_value = $existing_record[$key] ?? '';
    $new_value = $value === '' ? null : $value;
    $old_value = $existing_value === '' ? null : $existing_value;
    if ($new_value !== $old_value) {
        $changed_fields[] = $key;
        error_log("Field changed: $key, Old: '$old_value', New: '$new_value'");
    }
}

// Determine update scenarios
$update_print_preview = !empty(array_intersect($changed_fields, $print_preview_fields));
$update_dashboard = !empty(array_intersect($changed_fields, $dashboard_fields));
$update_history = !empty(array_intersect($changed_fields, $history_fields));
error_log("Update scenarios - Print Preview: " . ($update_print_preview ? 'Yes' : 'No') . ", Dashboard: " . ($update_dashboard ? 'Yes' : 'No') . ", History: " . ($update_history ? 'Yes' : 'No'));

// Update clinic_logs and adjust inventory in a transaction
try {
    $db->beginTransaction();

    // Step 2: Deduct new quantity from remaining_items and increment quantity column (cumulative dispensing)
    if ($new_medicine && $new_quantity > 0) {
        $stmt = $db->prepare("UPDATE inventory SET remaining_items = remaining_items - ?, quantity = quantity + ? WHERE name = ? AND remaining_items >= ?");
        $stmt->execute([$new_quantity, $new_quantity, $new_medicine, $new_quantity]);
        if ($stmt->rowCount() == 0) {
            throw new Exception("Not enough stock for $new_medicine (required: $new_quantity).");
        }
    }

    // Step 3: Update clinic_logs
    $stmt = $db->prepare("
        UPDATE clinic_logs SET
            student_number = ?, name = ?, age = ?, gender = ?, role = ?, course_section = ?,
            blood_pressure = ?, heart_rate = ?, blood_oxygen = ?, height = ?, weight = ?, temperature = ?,
            time_out = ?, sickness = ?, health_history = ?, purpose_of_visit = ?, medicine = ?, quantity = ?, remarks = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $student_number,
        $name,
        $age,
        $gender,
        $role,
        $course_section,
        $blood_pressure,
        $heart_rate,
        $blood_oxygen,
        $height,
        $weight,
        $temperature,
        $time_out,
        $sickness,
        $health_history,
        $purpose_of_visit,
        $new_medicine ?: null,  // Use null if empty
        $new_quantity > 0 ? $new_quantity : null,  // Use null if 0
        $remarks,
        $id
    ]);
    error_log("clinic_logs updated for ID: $id");

    // Step 4: Insert or update admin_history if history fields changed
    if ($update_history) {
        $date = $existing_record['date'] ?? date('Y-m-d');

        // Look for an existing history row for this student and date
        $stmt = $db->prepare("
            SELECT id, medicine, quantity 
            FROM admin_history 
            WHERE student_number = ? AND date = ? 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$student_number, $date]);
        $history_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $history_id = $history_row['id'] ?? null;

        error_log("Checking admin_history for student_number=$student_number, date=$date, found ID: " . ($history_id ?: 'none'));

        if ($history_id) {
            // If a history row exists, update it
            $stmt = $db->prepare("
                UPDATE admin_history SET
                    medicine = ?, quantity = ?, sickness = ?, health_history = ?, purpose_of_visit = ?, remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $medicine ?: null,
                $quantity ?: null,
                $sickness ?: null,
                $health_history ?: null,
                $purpose_of_visit ?: null,
                $remarks ?: null,
                $history_id
            ]);
            error_log("Updated admin_history ID: $history_id with medicine=$medicine, quantity=$quantity");
        } else {
            // No history row exists, insert a new one
            try {
                $stmt = $db->prepare("
                    INSERT INTO admin_history (
                        student_number, name, date, medicine, quantity, sickness, health_history, purpose_of_visit, remarks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $student_number,
                    $name,
                    $date,
                    $medicine ?: null,
                    $quantity ?: null,
                    $sickness ?: null,
                    $health_history ?: null,
                    $purpose_of_visit ?: null,
                    $remarks ?: null
                ]);
                error_log("Inserted new admin_history: student_number=$student_number, date=$date, medicine=$medicine, quantity=$quantity");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Duplicate entry error
                    error_log("Duplicate entry detected for student_number=$student_number, date=$date, attempting to update");
                    $stmt = $db->prepare("
                        SELECT id FROM admin_history 
                        WHERE student_number = ? AND date = ? 
                        ORDER BY created_at DESC LIMIT 1
                    ");
                    $stmt->execute([$student_number, $date]);
                    $history_id = $stmt->fetchColumn();

                    if ($history_id) {
                        $stmt = $db->prepare("
                            UPDATE admin_history SET
                                medicine = ?, quantity = ?, sickness = ?, health_history = ?, purpose_of_visit = ?, remarks = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $medicine ?: null,
                            $quantity ?: null,
                            $sickness ?: null,
                            $health_history ?: null,
                            $purpose_of_visit ?: null,
                            $remarks ?: null,
                            $history_id
                        ]);
                        error_log("Updated admin_history ID: $history_id after duplicate entry attempt");
                    }
                } else {
                    error_log("Error inserting into admin_history: " . $e->getMessage());
                    throw $e; // Re-throw other errors
                }
            }
        }
    }
    // Commit all changes
    $db->commit();

    // Store updated data in session for dashboard refresh
    $_SESSION['updated_data'] = [
        'id' => $id,
        'student_number' => $student_number,
        'name' => $name,
        'age' => $age,
        'gender' => $gender,
        'role' => $role,
        'course_section' => $course_section,
        'blood_pressure' => $blood_pressure,
        'heart_rate' => $heart_rate,
        'blood_oxygen' => $blood_oxygen,
        'height' => $height,
        'weight' => $weight,
        'temperature' => $temperature,
        'time_out' => $time_out,
        'sickness' => $sickness,
        'health_history' => $health_history,
        'purpose_of_visit' => $purpose_of_visit,
        'medicine' => $medicine,
        'quantity' => $quantity,
        'remarks' => $remarks
    ];

    // Send success response for AJAX
    echo ''; // Empty string indicates success
    exit();
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Failed to update: ' . $e->getMessage()]);
    exit();
}
?>