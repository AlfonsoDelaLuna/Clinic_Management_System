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
function validateInput($data, $field)
{
    $errors = [];
    if ($field === 'patient_id') {
        $data = trim($data);
        if (empty($data) || !preg_match('/^(?:\d{11}|[A-Z]{2,4}\d{3,4}[A-Z]?|NA|na|N\/a|n\/a)$/i', $data)) {
            $errors[] = "Patient ID must be 11 digits (e.g., 10000123456, 02000123456), or formats like CLN012A, CLN0123A, CLN0123, or NA";
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
        if (!empty($medicine) && $data < 1) {
            $errors[] = "Quantity must be at least 1.";
        }
        if (!empty($medicine) && $data > 0) {
            $stmt = $db->prepare("SELECT remaining_items FROM inventory WHERE name = ?");
            $stmt->execute([$medicine]);
            $inventory = $stmt->fetch();
            if ($inventory && $data > $inventory['remaining_items']) {
                $errors[] = "Quantity cannot exceed available stock (" . $inventory['remaining_items'] . ").";
            }
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
    return $errors;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $date = $_POST['date'] ?? '';
    $time_in = $_POST['time_in'] ? trim(substr($_POST['time_in'], 0, 5)) : null;
    $time_out = $_POST['time_out'] ? trim($_POST['time_out']) : null;
    $medicine = $_POST['medicine'] ? trim($_POST['medicine']) : null;
    $quantity = $_POST['quantity'] ? (int) $_POST['quantity'] : null;
    $health_history = $_POST['health_history'] ? trim($_POST['health_history']) : null;
    $purpose_of_visit = $_POST['purpose_of_visit'] ? trim($_POST['purpose_of_visit']) : null;
    $remarks = $_POST['remarks'] ? trim($_POST['remarks']) : null;

    $fields = ['patient_id', 'name', 'date', 'time_in', 'time_out', 'medicine', 'quantity', 'health_history', 'purpose_of_visit', 'remarks'];
    $errors = [];
    foreach ($fields as $field) {
        $value = $$field ?? '';
        $fieldErrors = validateInput($value, $field);
        if (!empty($fieldErrors)) {
            $errors[$field] = $fieldErrors;
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Fetch the original record from admin_history
            $stmt = $db->prepare("SELECT patient_id, name, date, time_in, purpose_of_visit, medicine, quantity FROM admin_history WHERE id = ?");
            $stmt->execute([$id]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($original) {
                $original_patient_id = $original['patient_id'];
                $original_name = $original['name'];
                $original_date = $original['date'];
                $original_time_in = $original['time_in'];
                $original_purpose_of_visit = $original['purpose_of_visit'];
                $original_medicine = $original['medicine'];
                $original_quantity = (int) $original['quantity'];

                // Update admin_history table
                $updateStmt = $db->prepare("
                    UPDATE admin_history
                    SET patient_id = ?, name = ?, date = ?, time_in = ?, time_out = ?,
                        medicine = ?, quantity = ?, health_history = ?,
                        purpose_of_visit = ?, remarks = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $patient_id,
                    $name,
                    $date,
                    $time_in,
                    $time_out,
                    $medicine,
                    $quantity,
                    $health_history,
                    $purpose_of_visit,
                    $remarks,
                    $id
                ]);

                // Update clinic_logs table
                // Use patient_id, name, date, and time_in as primary keys, include purpose_of_visit only if both are non-null
                $whereClause = "WHERE patient_id = ? AND name = ? AND date = ? AND time_in = ?";
                $params = [$patient_id, $name, $date, $time_in];
                if ($original_purpose_of_visit !== null && $purpose_of_visit !== null) {
                    $whereClause .= " AND purpose_of_visit = ?";
                    $params[] = $original_purpose_of_visit;
                }
                $updateClinicLogsStmt = $db->prepare("
                    UPDATE clinic_logs
                    SET patient_id = ?, name = ?, date = ?, time_in = ?, time_out = ?,
                        medicine = ?, quantity = ?, health_history = ?,
                        purpose_of_visit = ?, remarks = ?
                    $whereClause
                ");
                $params = array_merge(
                    [$patient_id, $name, $date, $time_in, $time_out, $medicine, $quantity, $health_history, $purpose_of_visit, $remarks],
                    [$original_patient_id, $original_name, $original_date, $original_time_in]
                );
                if ($original_purpose_of_visit !== null && $purpose_of_visit !== null) {
                    $params[] = $original_purpose_of_visit;
                }
                $updateClinicLogsStmt->execute($params);

                // Check if clinic_logs update affected any rows
                if ($updateClinicLogsStmt->rowCount() === 0) {
                    error_log("No clinic_logs record updated for admin_history ID: $id. Mismatched fields: patient_id=$original_patient_id, name=$original_name, date=$original_date, time_in=$original_time_in, purpose_of_visit=$original_purpose_of_visit");
                }

                // Adjust inventory for medicine
                if ($original_medicine && $original_quantity > 0) {
                    $inventoryStmt = $db->prepare("UPDATE inventory SET remaining_items = remaining_items + ?, quantity = quantity - ? WHERE name = ?");
                    $inventoryStmt->execute([$original_quantity, $original_quantity, $original_medicine]);
                }
                if ($medicine && $quantity > 0) {
                    $inventoryStmt = $db->prepare("UPDATE inventory SET remaining_items = remaining_items - ?, quantity = quantity + ? WHERE name = ?");
                    $inventoryStmt->execute([$quantity, $quantity, $medicine]);
                }

                $db->commit();

                // Store updated data for redirect
                $_SESSION['updated_data'] = [
                    'patient_id' => $patient_id,
                    'name' => $name,
                    'date' => $date,
                    'time_in' => $time_in,
                    'time_out' => $time_out,
                    'medicine' => $medicine,
                    'quantity' => $quantity,
                    'health_history' => $health_history,
                    'purpose_of_visit' => $purpose_of_visit,
                    'remarks' => $remarks
                ];
                header("Location: admin_history.php#from-update");
                exit();
            } else {
                throw new Exception("Record not found.");
            }
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['form_errors'] = ['database' => [$e->getMessage()]];
            $_SESSION['form_data'] = $_POST;
        }
    } else {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
    }
}

$stmt = $db->prepare("SELECT * FROM admin_history WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$record) {
    header("Location: admin_history.php");
    exit();
}

$medicineStmt = $db->prepare("SELECT name FROM inventory WHERE remaining_items > 0");
$medicineStmt->execute();
$medicines = $medicineStmt->fetchAll(PDO::FETCH_ASSOC);

$form_data = $_SESSION['form_data'] ?? $record;
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
            font-size: 12px;
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
                <em>Toggle Mode</em>
            </div>
        </div>
        <div class="main-content">
            <div class="form-container">
                <h1>Edit History Record</h1>
                <form method="post">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($record['id']) ?>">
                    <input type="hidden" name="theme" id="theme-input" value="light">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="patient_id">Patient ID:</label>
                            <input type="text" id="patient_id" name="patient_id"
                                value="<?= htmlspecialchars($form_data['patient_id'] ?? $record['patient_id']) ?>" required>
                            <div id="patient_id-error" class="error">
                                <?php if (isset($errors['patient_id'])): ?>
                                    <?= implode('<br>', $errors['patient_id']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="name">Name:</label>
                            <input type="text" id="name" name="name"
                                value="<?= htmlspecialchars($form_data['name'] ?? $record['name']) ?>" readonly required>
                            <div id="name-error" class="error">
                                <?php if (isset($errors['name'])): ?>
                                    <?= implode('<br>', $errors['name']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="date">Date:</label>
                            <input type="date" id="date" name="date"
                                value="<?= htmlspecialchars($form_data['date'] ?? $record['date']) ?>" readonly required>
                            <div id="date-error" class="error">
                                <?php if (isset($errors['date'])): ?>
                                    <?= implode('<br>', $errors['date']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="time_in">Log In:</label>
                            <input type="time" id="time_in" name="time_in"
                                value="<?= htmlspecialchars($form_data['time_in'] ?? ($record['time_in'] ? substr($record['time_in'], 0, 5) : '')) ?>" readonly>
                            <div id="time_in-error" class="error">
                                <?php if (isset($errors['time_in'])): ?>
                                    <?= implode('<br>', $errors['time_in']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="time_out">Log Out:</label>
                            <input type="time" id="time_out" name="time_out"
                                value="<?= htmlspecialchars($form_data['time_out'] ?? ($record['time_out'] ? substr($record['time_out'], 0, 5) : '')) ?>">
                            <div id="time_out-error" class="error">
                                <?php if (isset($errors['time_out'])): ?>
                                    <?= implode('<br>', $errors['time_out']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="medicine">Release Medicine:</label>
                            <select id="medicine" name="medicine">
                                <option value="">-- Select Medicine --</option>
                                <?php foreach ($medicines as $med): ?>
                                    <option value="<?= htmlspecialchars($med['name']) ?>"
                                        <?= ($form_data['medicine'] ?? $record['medicine'] ?? '') === $med['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($med['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="medicine-error" class="error">
                                <?php if (isset($errors['medicine'])): ?>
                                    <?= implode('<br>', $errors['medicine']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity Used:</label>
                            <input type="number" id="quantity" name="quantity"
                                value="<?= htmlspecialchars($form_data['quantity'] ?? $record['quantity'] ?? '') ?>" min="0">
                            <div id="quantity-error" class="error">
                                <?php if (isset($errors['quantity'])): ?>
                                    <?= implode('<br>', $errors['quantity']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="health_history">Health History:</label>
                            <textarea id="health_history" name="health_history"><?= htmlspecialchars($form_data['health_history'] ?? $record['health_history'] ?? '') ?></textarea>
                            <div id="health_history-error" class="error">
                                <?php if (isset($errors['health_history'])): ?>
                                    <?= implode('<br>', $errors['health_history']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="purpose_of_visit">Purpose of Visit:</label>
                            <textarea id="purpose_of_visit" name="purpose_of_visit"><?= htmlspecialchars($form_data['purpose_of_visit'] ?? $record['purpose_of_visit'] ?? '') ?></textarea>
                            <div id="purpose_of_visit-error" class="error">
                                <?php if (isset($errors['purpose_of_visit'])): ?>
                                    <?= implode('<br>', $errors['purpose_of_visit']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="remarks">Remarks:</label>
                            <textarea id="remarks" name="remarks"><?= htmlspecialchars($form_data['remarks'] ?? $record['remarks'] ?? '') ?></textarea>
                            <div id="remarks-error" class="error">
                                <?php if (isset($errors['remarks'])): ?>
                                    <?= implode('<br>', $errors['remarks']) ?>
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
        const themeInput = document.getElementById('theme-input');
        const currentTheme = localStorage.getItem('theme') || 'light';

        // Apply theme and set toggle state
        if (currentTheme === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggle.checked = true;
            themeInput.value = 'dark';
        } else {
            document.body.classList.remove('dark-mode');
            localStorage.setItem('theme', 'light');
            themeInput.value = 'light';
        }

        // Theme toggle event listener
        themeToggle.addEventListener('change', function() {
            if (this.checked) {
                document.body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
                themeInput.value = 'dark';
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
                themeInput.value = 'light';
            }
        });

        // Logout confirmation
        document.querySelector('.logout').addEventListener('click', function(event) {
            event.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = this.href;
            }
        });
    });
</script>

</html>