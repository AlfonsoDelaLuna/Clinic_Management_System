<?php
session_start();

// Restrict to admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Database connection
try {
    $db = new PDO("mysql:host=localhost;dbname=clinic_db", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get record ID
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: admin_history.php");
    exit();
}

// Validation function
function validateInput($data, $field, $original_med_for_validation = null, $original_qty_for_validation = 0)
{
    global $db;
    $errors = [];
    if ($field === 'patient_id') {
        $data = trim($data);
        if (empty($data) || !preg_match('/^(?:\d{11}|[A-Z]{2,4}\d{3,4}[A-Z]?|NA|na|N\/a|n\/a)$/i', $data)) {
            $errors[] = "Patient ID must be 11 digits (e.g., 10000123456, 02000123456), or formats like CLN012A, CLN0123A, CLN0123, or NA.";
        }
    }
    if ($field === 'name') {
        $data = trim($data);
        if (empty($data) || !preg_match('/^[A-Za-z\s]{2,50}$/', $data)) {
            $errors[] = "Name must contain only letters and spaces, 2-50 characters.";
        }
    }
    if ($field === 'date') {
        $data = trim($data);
        if (empty($data)) {
            $errors[] = "Date is required.";
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $data);
            if (!$date || $date->format('Y-m-d') !== $data) {
                $errors[] = "Invalid date format.";
            }
        }
    }
    if ($field === 'time_in' || $field === 'time_out') {
        $data = trim($data);
        if (!empty($data) && !preg_match('/^\d{2}:\d{2}$/', $data)) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must be in HH:MM format (e.g., 14:30).";
        }
    }
    if ($field === 'medicine') {
        $data = trim($data);
        if (!empty($data)) {
            $stmt = $db->prepare("SELECT name FROM inventory WHERE name = ? AND remaining_items > 0");
            $stmt->execute([$data]);
            if (!$stmt->fetch()) {
                // Check if this medicine was the original one for the record
                if ($original_med_for_validation !== $data) {
                    $errors[] = "Selected medicine isn’t available or out of stock.";
                } else {
                    // If it's the original medicine, it might be okay if quantity is also original or less
                    // This specific check is complex and might be better handled by quantity validation
                    // For now, if it's the original and out of stock, quantity validation will catch it if > 0
                }
            }
        }
    }
    if ($field === 'quantity') {
        $current_form_quantity = is_numeric($data) ? (int) $data : null; // Handle non-numeric input like ""
        $current_form_medicine = trim($_POST['medicine'] ?? '');

        if (!empty($current_form_medicine)) {
            if ($current_form_quantity === null || $current_form_quantity < 1) { // Check for null explicitly
                $errors[] = "Quantity must be at least 1 when a medicine is selected.";
            } elseif ($current_form_quantity > 0) { // Only proceed if quantity is valid number > 0
                $stmt = $db->prepare("SELECT remaining_items FROM inventory WHERE name = ?");
                $stmt->execute([$current_form_medicine]);
                $inventory_item = $stmt->fetch();

                if ($inventory_item) {
                    $stock_in_db = (int)$inventory_item['remaining_items'];
                    $effective_available_stock = $stock_in_db;

                    if ($original_med_for_validation !== null && $original_med_for_validation === $current_form_medicine) {
                        $effective_available_stock += (int)$original_qty_for_validation;
                    }

                    if ($current_form_quantity > $effective_available_stock) {
                        $errors[] = "Quantity (" . $current_form_quantity . ") cannot exceed available stock (" . $effective_available_stock . " for " . htmlspecialchars($current_form_medicine) . ").";
                    }
                } else if (!empty($current_form_medicine)) { // Medicine selected but not found in inventory (should be caught by medicine validation)
                    // This case implies the medicine itself is invalid, which medicine validation should catch.
                    // However, if medicine validation allows an original out-of-stock item, this might still be an issue.
                    // For now, we assume medicine validation handles non-existent/unavailable medicines.
                }
            }
        } elseif ($current_form_quantity !== null && $current_form_quantity > 0 && empty($current_form_medicine)) {
            $errors[] = "Cannot set quantity without selecting a medicine.";
        }
    }
    if ($field === 'health_history') {
        $data = trim($data);
        if (!empty($data) && !preg_match('/^[A-Za-z0-9\s,\-]{2,100}$/', $data)) {
            $errors[] = "Health History must be 2-100 characters, letters, numbers, spaces, commas, or dashes.";
        }
    }
    if ($field === 'purpose_of_visit') {
        $data = trim($data);
        if (!empty($data) && !preg_match('/^[A-Za-z0-9\s,\-]{2,100}$/', $data)) {
            $errors[] = "Purpose of Visit must be 2-100 characters, letters, numbers, spaces, commas, or dashes.";
        }
    }
    if ($field === 'remarks') {
        $data = trim($data);
        if (!empty($data) && !preg_match('/^[A-Za-z0-9\s,\-\.]{2,200}$/', $data)) {
            $errors[] = "Remarks must be 2-200 characters, letters, numbers, spaces, commas, dashes, or periods.";
        }
    }
    // Updated validation for vital signs
    if ($field === 'blood_pressure') {
        $data = trim($data);
        if (!empty($data) && !preg_match('/^(NA|N\/A|\d{1,3}\s*\/\s*\d{1,3})$/i', $data)) {
            $errors[] = "Blood Pressure must be in format like '120/80' or 'NA'.";
        }
    }
    if ($field === 'heart_rate') {
        $data = trim($data);
        if (!empty($data) && !in_array($data, ['NA', 'N/A', 'N/A'])) {
            if (!preg_match('/^\d+$/', $data)) {
                $errors[] = "Heart Rate must be a number or NA.";
            } elseif ($data < 1 || $data > 200) {
                $errors[] = "Heart Rate must be between 1 and 200.";
            }
        }
    }
    if ($field === 'blood_oxygen') {
        $data = trim($data);
        if (!empty($data) && !in_array($data, ['NA', 'N/A', 'N/A'])) {
            if (!preg_match('/^\d+$/', $data)) {
                $errors[] = "Blood Oxygen must be a number or NA.";
            } elseif ($data < 1 || $data > 100) {
                $errors[] = "Blood Oxygen must be between 1 and 100.";
            }
        }
    }
    if ($field === 'height') {
        $data = trim($data);
        if (!empty($data) && !in_array($data, ['NA', 'N/A', 'N/A'])) {
            if (!preg_match('/^\d+(\.\d+)?$/', $data)) {
                $errors[] = "Height must be a number or NA.";
            } elseif ($data < 1 || $data > 251) {
                $errors[] = "Height must be between 1 and 251 cm.";
            }
        }
    }
    if ($field === 'weight') {
        $data = trim($data);
        if (!empty($data) && !in_array($data, ['NA', 'N/A', 'N/A'])) {
            if (!preg_match('/^\d+(\.\d+)?$/', $data)) {
                $errors[] = "Weight must be a number or NA.";
            } elseif ($data < 1 || $data > 500) {
                $errors[] = "Weight must be between 1 and 500 kg.";
            }
        }
    }
    if ($field === 'temperature') {
        $data = trim($data);
        if (!empty($data) && !in_array($data, ['NA', 'N/A', 'N/A'])) {
            if (!preg_match('/^\d+(\.\d+)?$/', $data)) {
                $errors[] = "Temperature must be a number or NA.";
            } elseif ($data < 1 || $data > 100) {
                $errors[] = "Temperature must be between 1 and 100°C.";
            }
        }
    }

    return $errors;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = trim($_POST['patient_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $time_in = !empty($_POST['time_in']) ? trim(substr($_POST['time_in'], 0, 5)) : null;
    $time_out = !empty($_POST['time_out']) ? trim(substr($_POST['time_out'], 0, 5)) : null;
    $medicine_post = !empty($_POST['medicine']) ? trim($_POST['medicine']) : null;
    // Ensure quantity is integer or null. If medicine is not set, quantity should be null.
    $quantity_post = null;
    if (!empty($medicine_post)) {
        $quantity_post = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (int)$_POST['quantity'] : null;
    }


    $health_history = !empty($_POST['health_history']) ? trim($_POST['health_history']) : null;
    $purpose_of_visit = !empty($_POST['purpose_of_visit']) ? trim($_POST['purpose_of_visit']) : null;
    $remarks = !empty($_POST['remarks']) ? trim($_POST['remarks']) : null;

    // For vital signs, trim and allow empty string to become NULL if that's desired, or keep as empty string.
    // If "NA" is submitted, it will be "NA". If empty, it will be an empty string.
    // The database columns for these should be VARCHAR.
    $blood_pressure = trim($_POST['blood_pressure'] ?? '');
    $heart_rate = trim($_POST['heart_rate'] ?? '');
    $blood_oxygen = trim($_POST['blood_oxygen'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $weight = trim($_POST['weight'] ?? '');
    $temperature = trim($_POST['temperature'] ?? '');


    $original_medicine_for_validation = null;
    $original_quantity_for_validation = 0;
    $stmt_val = $db->prepare("SELECT medicine, quantity FROM admin_history WHERE id = ?");
    $stmt_val->execute([$id]);
    $temp_orig_for_val = $stmt_val->fetch(PDO::FETCH_ASSOC);
    if ($temp_orig_for_val) {
        $original_medicine_for_validation = $temp_orig_for_val['medicine'];
        $original_quantity_for_validation = (int)$temp_orig_for_val['quantity'];
    }


    $fields = ['patient_id', 'name', 'date', 'time_in', 'time_out', 'medicine', 'quantity', 'health_history', 'purpose_of_visit', 'remarks', 'blood_pressure', 'heart_rate', 'blood_oxygen', 'height', 'weight', 'temperature'];
    $errors = [];
    foreach ($fields as $field) {
        // Use the specifically prepared POST variables for medicine and quantity
        if ($field === 'medicine') {
            $value_to_validate = $medicine_post;
        } elseif ($field === 'quantity') {
            $value_to_validate = $quantity_post;
        } else {
            $value_to_validate = $$field ?? '';
        }

        $fieldErrors = [];
        if ($field === 'quantity') {
            $fieldErrors = validateInput($value_to_validate, $field, $original_medicine_for_validation, $original_quantity_for_validation);
        } elseif ($field === 'medicine') {
            $fieldErrors = validateInput($value_to_validate, $field, $original_medicine_for_validation);
        } else {
            $fieldErrors = validateInput($value_to_validate, $field);
        }

        if (!empty($fieldErrors)) {
            $errors[$field] = $fieldErrors;
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM admin_history WHERE id = ?");
            $stmt->execute([$id]);
            $original_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($original_record) {
                $original_db_medicine = $original_record['medicine'];
                $original_db_quantity = (int) $original_record['quantity'];

                $updateStmt = $db->prepare("
                    UPDATE admin_history
                    SET patient_id = ?, name = ?, date = ?, time_in = ?, time_out = ?,
                        medicine = ?, quantity = ?, health_history = ?,
                        purpose_of_visit = ?, remarks = ?, blood_pressure = ?, heart_rate = ?,
                        blood_oxygen = ?, height = ?, weight = ?, temperature = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $patient_id,
                    $name,
                    $date,
                    $time_in,
                    $time_out,
                    $medicine_post,
                    $quantity_post, // Use the processed variables
                    $health_history,
                    $purpose_of_visit,
                    $remarks,
                    $blood_pressure,
                    $heart_rate,
                    $blood_oxygen,
                    $height,
                    $weight,
                    $temperature,
                    $id
                ]);

                $updateClinicLogsStmt = $db->prepare("
                    UPDATE clinic_logs
                    SET patient_id = ?, name = ?, date = ?, time_in = ?, time_out = ?,
                        medicine = ?, quantity = ?, health_history = ?,
                        purpose_of_visit = ?, remarks = ?, blood_pressure = ?, heart_rate = ?,
                        blood_oxygen = ?, height = ?, weight = ?, temperature = ?
                    WHERE patient_id = ? AND date = ? AND time_in <=> ? 
                ");
                $updateClinicLogsStmt->execute([
                    $patient_id,
                    $name,
                    $date,
                    $time_in,
                    $time_out,
                    $medicine_post,
                    $quantity_post, // Use the processed variables
                    $health_history,
                    $purpose_of_visit,
                    $remarks,
                    $blood_pressure,
                    $heart_rate,
                    $blood_oxygen,
                    $height,
                    $weight,
                    $temperature,
                    $original_record['patient_id'],
                    $original_record['date'],
                    $original_record['time_in']
                ]);
                $clinicLogsRowsAffected = $updateClinicLogsStmt->rowCount();
                error_log("clinic_logs rows affected: " . $clinicLogsRowsAffected . " for admin_history ID: $id, original time_in: " . ($original_record['time_in'] ?? 'NULL'));

                if ($original_db_medicine && $original_db_quantity > 0) {
                    $inventoryStmt = $db->prepare("UPDATE inventory SET remaining_items = remaining_items + ? WHERE name = ?");
                    $inventoryStmt->execute([$original_db_quantity, $original_db_medicine]);
                }
                if ($medicine_post && $quantity_post > 0) {
                    $inventoryStmt = $db->prepare("UPDATE inventory SET remaining_items = remaining_items - ? WHERE name = ?");
                    $inventoryStmt->execute([$quantity_post, $medicine_post]);
                }

                $db->commit();
                error_log("Transaction committed for admin_history ID: $id");

                $_SESSION['updated_data'] = [
                    'patient_id' => $patient_id,
                    'name' => $name,
                    'date' => $date,
                    'time_in' => $time_in,
                    'time_out' => $time_out,
                    'medicine' => $medicine_post,
                    'quantity' => $quantity_post,
                    'health_history' => $health_history,
                    'purpose_of_visit' => $purpose_of_visit,
                    'remarks' => $remarks,
                    'blood_pressure' => $blood_pressure,
                    'heart_rate' => $heart_rate,
                    'blood_oxygen' => $blood_oxygen,
                    'height' => $height,
                    'weight' => $weight,
                    'temperature' => $temperature
                ];
                header("Location: admin_history.php#from-update");
                exit();
            } else {
                throw new Exception("Original record not found with ID: $id.");
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Transaction failed for admin_history ID $id: " . $e->getMessage());
            $_SESSION['form_errors'] = ['database' => ["Failed to save changes: " . $e->getMessage()]];
            $_SESSION['form_data'] = $_POST; // Save raw POST data for repopulation
        }
    } else {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST; // Save raw POST data for repopulation
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $id);
    exit();
}


$stmt = $db->prepare("SELECT * FROM admin_history WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$record) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Record not found.'];
    header("Location: admin_history.php");
    exit();
}

// Fetch medicines for dropdown
// Include current medicine even if out of stock, and any other medicine with stock
$current_record_medicine = $record['medicine'] ?? '';
$medicineStmt = $db->prepare("
    SELECT name FROM inventory WHERE remaining_items > 0 OR name = :current_medicine
    ORDER BY name ASC
");
$medicineStmt->bindParam(':current_medicine', $current_record_medicine);
$medicineStmt->execute();
$all_medicines_fetch = $medicineStmt->fetchAll(PDO::FETCH_ASSOC);

// Ensure unique medicines in dropdown
$medicines_for_dropdown = [];
$seen_med_names = [];
foreach ($all_medicines_fetch as $med) {
    if (!in_array($med['name'], $seen_med_names)) {
        $medicines_for_dropdown[] = $med;
        $seen_med_names[] = $med['name'];
    }
}


$form_data = $_SESSION['form_data'] ?? $record; // If session form_data exists (from failed POST), use it, else use $record
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data']);
unset($_SESSION['form_errors']);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit History Record</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --bg-color: #f4f7fa;
            --text-color: #121212;
            --card-bg: #ffffff;
            --border-color: #d0d4d8;
            --primary-color: #1a3c6d;
            --hover-color: #2a5298;
            --error-color: #d32f2f;
            --sidebar-bg: #1a3c6d;
            --sidebar-text: #ffffff;
        }

        .dark-mode {
            --bg-color: #121212;
            --text-color: #e0e0e0;
            --card-bg: #1e1e1e;
            --border-color: #444;
            --primary-color: #3a6ab7;
            --hover-color: #4a7ac7;
            --error-color: #ff5252;
            --sidebar-bg: #0d1a2d;
            --sidebar-text: #e0e0e0;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }

        .form-container {
            width: min(100%, 1500px);
            margin: 0 auto;
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: background-color 0.3s;
        }

        .form-container h1 {
            font-size: 1.8em;
            color: var(--text-color);
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1em;
            background-color: var(--card-bg);
            color: var(--text-color);
            box-sizing: border-box;
            transition: all 0.3s;
        }

        .form-group input[readonly] {
            background-color: var(--border-color);
            cursor: not-allowed;
        }

        .form-group select {
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="6"><path fill="%23333" d="M0 0h12L6 6z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px top 50%;
            background-size: 12px;
        }

        .form-group textarea {
            height: 60px;
            resize: none;
        }

        .error {
            color: var(--error-color);
            font-size: 0.85em;
            margin-top: 5px;
        }

        .button-group {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 1em;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: #fff;
        }

        .btn-primary:hover {
            background-color: var(--hover-color);
        }

        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }

        /* Toggle Switch Styles */
        .theme-switch-wrapper {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            margin-top: auto;
            border-top: 1px solid #34495e;
            gap: 12px;
        }

        .theme-switch-wrapper em {
            font-size: 0.9rem;
            color: #ecf0f1;
            white-space: nowrap;
            font-style: normal;
        }

        .theme-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            flex-shrink: 0;
        }

        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #767f8c;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: '☀️';
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: #333;
        }

        input:checked+.slider {
            background-color: #0056b3;
        }

        input:focus+.slider {
            box-shadow: 0 0 1px #0056b3;
        }

        input:checked+.slider:before {
            transform: translateX(26px);
            content: '🌙';
            background-color: #333;
            color: #eee;
        }

        .slider.round {
            border-radius: 24px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        .main-content {
            background-color: #f4f7fa;
            color: #000000;
        }

        /* Dark mode styles */
        body.dark-mode .main-content {
            background-color: #1e1e1e;
        }

        body.dark-mode .dashboard-container {
            background-color: #1e1e1e;
        }

        body.dark-mode .form-container {
            background-color: #000000;
        }

        body.dark-mode .form-group input,
        body.dark-mode .form-group select,
        body.dark-mode .form-group textarea {
            background-color: #1e1e1e;
            color: #e0e0e0;
            border: 1px solid #555;
        }

        body.dark-mode .form-group input[readonly] {
            background-color: #444;
        }

        body.dark-mode .btn-primary {
            background-color: #3a6ab7;
        }

        body.dark-mode .btn-primary:hover {
            background-color: #4a7ac7;
        }
    </style>
    <link rel="icon" href="images/sti_logo.png" type="image/png">
    <script>
        // Apply theme immediately to prevent FOUC
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
</head>

<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h2>Clinic Management</h2>
            <ul class="menu">
                <li><a href="admin_history.php" class="active">History</a></li>
                <li><a href="admin_loginsheet.php">Login Sheet</a></li>
                <li><a href="admin_inventory.php">Inventory</a></li>
                <li><a href="logout.php" class="logout">Logout</a></li>
            </ul>
            <div class="theme-switch-wrapper">
                <label class="theme-switch" for="checkbox">
                    <input type="checkbox" id="checkbox" />
                    <div class="slider round"></div>
                </label>
                <em>Switch Mode</em>
            </div>
        </div>
        <div class="main-content">
            <div class="form-container">
                <h1>Edit History Record</h1>

                <?php if (isset($errors['database'])): ?>
                    <div class="error-db">
                        <?= implode('<br>', array_map('htmlspecialchars', $errors['database'])) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?id=<?= htmlspecialchars($id) ?>">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($record['id']) ?>">
                    <input type="hidden" name="theme" id="theme-input" value="light"> <!-- JS will update this -->

                    <div class="form-row">
                        <div class="form-group">
                            <label for="patient_id">Patient ID:</label>
                            <input type="text" id="patient_id" name="patient_id"
                                value="<?= htmlspecialchars($form_data['patient_id'] ?? '') ?>" required>
                            <div id="patient_id-error" class="error">
                                <?php if (isset($errors['patient_id'])): ?>
                                    <?= implode('<br>', array_map('htmlspecialchars', $errors['patient_id'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="name">Name:</label>
                            <input type="text" id="name" name="name"
                                value="<?= htmlspecialchars($form_data['name'] ?? '') ?>" required readonly>
                            <div id="name-error" class="error">
                                <?php if (isset($errors['name'])): ?>
                                    <?= implode('<br>', array_map('htmlspecialchars', $errors['name'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="date">Date:</label>
                            <input type="date" id="date" name="date"
                                value="<?= htmlspecialchars($form_data['date'] ?? '') ?>" required readonly>
                            <div id="date-error" class="error">
                                <?php if (isset($errors['date'])): ?>
                                    <?= implode('<br>', array_map('htmlspecialchars', $errors['date'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="time_in">Log In:</label>
                            <input type="time" id="time_in" name="time_in"
                                value="<?= htmlspecialchars(isset($form_data['time_in']) && $form_data['time_in'] ? substr($form_data['time_in'], 0, 5) : '') ?>" readonly>
                            <div id="time_in-error" class="error">
                                <?php if (isset($errors['time_in'])): ?>
                                    <?= implode('<br>', array_map('htmlspecialchars', $errors['time_in'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="time_out">Log Out:</label>
                            <input type="time" id="time_out" name="time_out"
                                value="<?= htmlspecialchars(isset($form_data['time_out']) && $form_data['time_out'] ? substr($form_data['time_out'], 0, 5) : '') ?>">
                            <div id="time_out-error" class="error">
                                <?php if (isset($errors['time_out'])): ?>
                                    <?= implode('<br>', array_map('htmlspecialchars', $errors['time_out'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="blood_pressure">Blood Pressure:</label>
                            <input type="text" id="blood_pressure" name="blood_pressure" value="<?= htmlspecialchars($form_data['blood_pressure'] ?? '') ?>">
                            <div class="error"><?php if (isset($errors['blood_pressure'])) echo implode('<br>', array_map('htmlspecialchars', $errors['blood_pressure'])); ?></div>
                        </div>
                        <div class="form-group">
                            <label for="heart_rate">Heart Rate:</label>
                            <input type="text" id="heart_rate" name="heart_rate" value="<?= htmlspecialchars($form_data['heart_rate'] ?? '') ?>">
                            <div class="error"><?php if (isset($errors['heart_rate'])) echo implode('<br>', array_map('htmlspecialchars', $errors['heart_rate'])); ?></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="blood_oxygen">Blood Oxygen:</label>
                            <input type="text" id="blood_oxygen" name="blood_oxygen" value="<?= htmlspecialchars($form_data['blood_oxygen'] ?? '') ?>">
                            <div class="error"><?php if (isset($errors['blood_oxygen'])) echo implode('<br>', array_map('htmlspecialchars', $errors['blood_oxygen'])); ?></div>
                        </div>
                        <div class="form-group">
                            <label for="height">Height:</label>
                            <input type="text" id="height" name="height" value="<?= htmlspecialchars($form_data['height'] ?? '') ?>">
                            <div class="error"><?php if (isset($errors['height'])) echo implode('<br>', array_map('htmlspecialchars', $errors['height'])); ?></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="weight">Weight:</label>
                            <input type="text" id="weight" name="weight" value="<?= htmlspecialchars($form_data['weight'] ?? '') ?>">
                            <div class="error"><?php if (isset($errors['weight'])) echo implode('<br>', array_map('htmlspecialchars', $errors['weight'])); ?></div>
                        </div>
                        <div class="form-group">
                            <label for="temperature">Temperature:</label>
                            <input type="text" id="temperature" name="temperature" value="<?= htmlspecialchars($form_data['temperature'] ?? '') ?>">
                            <div class="error"><?php if (isset($errors['temperature'])) echo implode('<br>', array_map('htmlspecialchars', $errors['temperature'])); ?></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="medicine">Release Medicine:</label>
                            <select id="medicine" name="medicine">
                                <option value="">-- Select Medicine --</option>
                                <?php foreach ($medicines_for_dropdown as $med): ?>
                                    <option value="<?= htmlspecialchars($med['name']) ?>"
                                        <?= (isset($form_data['medicine']) && $form_data['medicine'] === $med['name']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($med['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="medicine-error" class="error">
                                <?php if (isset($errors['medicine'])): ?>
                                    <?= implode('<br>', array_map('htmlspecialchars', $errors['medicine'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity Used:</label>
                            <input type="number" id="quantity" name="quantity"
                                value="<?= htmlspecialchars($form_data['quantity'] ?? '') ?>" min="0"> <!-- Consider removing min="0" if null is preferred for no quantity -->
                            <div id="quantity-error" class="error">
                                <?php if (isset($errors['quantity'])): ?>
                                    <?= implode('<br>', array_map('htmlspecialchars', $errors['quantity'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="health_history">Health History:</label>
                            <textarea id="health_history" name="health_history"><?= htmlspecialchars($form_data['health_history'] ?? '') ?></textarea>
                            <div id="health_history-error" class="error">
                                <?php if (isset($errors['health_history'])): ?>
                                    <?= implode('<br>', array_map('htmlspecialchars', $errors['health_history'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="purpose_of_visit">Purpose of Visit:</label>
                            <textarea id="purpose_of_visit" name="purpose_of_visit"><?= htmlspecialchars($form_data['purpose_of_visit'] ?? '') ?></textarea>
                            <div id="purpose_of_visit-error" class="error">
                                <?php if (isset($errors['purpose_of_visit'])): ?>
                                    <?= implode('<br>', array_map('htmlspecialchars', $errors['purpose_of_visit'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="remarks">Remarks:</label>
                            <textarea id="remarks" name="remarks"><?= htmlspecialchars($form_data['remarks'] ?? '') ?></textarea>
                            <div id="remarks-error" class="error">
                                <?php if (isset($errors['remarks'])): ?>
                                    <?= implode('<br>', array_map('htmlspecialchars', $errors['remarks'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="admin_history.php" class="btn btn-primary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const themeToggle = document.getElementById('checkbox');
        const themeInput = document.getElementById('theme-input'); // For submitting theme preference if needed by backend on POST
        const body = document.body;
        const htmlElement = document.documentElement;

        function applyTheme(theme) {
            if (theme === 'dark') {
                htmlElement.classList.add('dark-mode');
                body.classList.add('dark-mode'); // Apply to body as well if specific body styles exist
                if (themeToggle) themeToggle.checked = true;
                if (themeInput) themeInput.value = 'dark';
            } else {
                htmlElement.classList.remove('dark-mode');
                body.classList.remove('dark-mode');
                if (themeToggle) themeToggle.checked = false;
                if (themeInput) themeInput.value = 'light';
            }
        }

        // Apply stored theme on initial load
        const storedTheme = localStorage.getItem('theme') || 'light'; // Default to light
        applyTheme(storedTheme);

        // Listener for theme toggle
        if (themeToggle) {
            themeToggle.addEventListener('change', function() {
                const newTheme = this.checked ? 'dark' : 'light';
                localStorage.setItem('theme', newTheme);
                applyTheme(newTheme);
            });
        }

        // Logout confirmation
        const logoutButton = document.querySelector('.logout');
        if (logoutButton) {
            logoutButton.addEventListener('click', function(event) {
                event.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = this.href;
                }
            });
        }

        // Logic for medicine and quantity fields
        const medicineSelect = document.getElementById('medicine');
        const quantityInput = document.getElementById('quantity');

        function toggleQuantityState() {
            if (medicineSelect.value === "") { // No medicine selected
                quantityInput.value = ""; // Clear quantity
                quantityInput.disabled = true; // Disable quantity
            } else {
                quantityInput.disabled = false; // Enable quantity
            }
        }

        if (medicineSelect && quantityInput) {
            medicineSelect.addEventListener('change', toggleQuantityState);
            // Initial state check on page load
            toggleQuantityState();
        }

    });
</script>

</html>