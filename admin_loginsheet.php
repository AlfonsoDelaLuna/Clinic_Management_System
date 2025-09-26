<?php
session_start();

// Set timezone to ensure consistent time across scripts
date_default_timezone_set('Asia/Manila');

// Check if the user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
try {
    $db = new PDO("mysql:host=localhost;dbname=clinic_db", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch available medicines from inventory
$stmt = $db->query("SELECT name FROM inventory WHERE remaining_items > 0");
$medicines = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Retrieve form data and errors from session
$form_data = $_SESSION['form_data'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];

// Clear session data after retrieving
unset($_SESSION['form_data']);
unset($_SESSION['form_errors']);

// Check if an ID was submitted via AJAX to prefill the form
$existing_record = null;
if (isset($_POST['check_patient_id'])) {
    $patient_id = trim($_POST['check_patient_id']);
    $naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];
    if (!empty($patient_id) && (preg_match('/^(?:\d{11}|[A-Z]{3}\d{3}[A-Z]|[A-Z]{3}\d{4}[A-Z]|[A-Z]{3}\d{4})$/i', $patient_id) || in_array($patient_id, $naValues))) {
        if (!in_array($patient_id, $naValues)) {
            $stmt = $db->prepare("SELECT * FROM clinic_logs WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing_record) {
                $form_data = array_merge($form_data, $existing_record);
            }
        }
    }
}

// Prepare birthday values for pre-filling the dropdowns
$birthday = isset($form_data['birthday']) ? $form_data['birthday'] : '';
$selected_day = $birthday ? (new DateTime($birthday))->format('d') : '';
$selected_month = $birthday ? (new DateTime($birthday))->format('F') : '';
$selected_year = $birthday ? (new DateTime($birthday))->format('Y') : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Login Sheet</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="images/sti_logo.png" type="image/png">
    <style>
        .main-content h1,
        .main-content h2 {
            color: #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
            margin-top: 0;
        }


        .form-group input[readonly],
        .form-group select[disabled],
        .form-group input[disabled] {
            background-color: #f0f0f0;
            color: #888;
            cursor: not-allowed;
        }

        .form-group .birthday-group {
            display: flex;
            gap: 10px;
        }

        .form-group .birthday-group select {
            padding: 8px 5px;
            width: auto;
            flex-grow: 1;
        }

        .form-group .error {
            color: #dc3545;
            font-size: 0.85em;
            min-height: 1.2em;
            display: block;
            margin-top: 2px;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #aaa;
        }


        .theme-switch-wrapper {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            margin-top: auto;
            border-top: 1px solid #34495e;
            gap: 12px;
        }

        .theme-switch-wrapper em {
            font-size: 1rem;
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

        body.dark-mode {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }

        body.dark-mode .main-content h1,
        body.dark-mode .main-content h2 {
            color: #e0e0e0;
        }

        body.dark-mode .form-group label {
            color: #cccccc;
        }

        body.dark-mode .form-group input[type="text"],
        body.dark-mode .form-group input[type="number"],
        body.dark-mode .form-group input[type="time"],
        body.dark-mode .form-group input[list],
        body.dark-mode .form-group select,
        body.dark-mode .form-group textarea {
            background-color: #2a2a2a;
            color: #e0e0e0;
            border: 1px solid #555;
        }

        body.dark-mode .form-group input[readonly],
        body.dark-mode .form-group select[disabled],
        body.dark-mode .form-group input[disabled] {
            background-color: #333;
            color: #888;
            border: 1px solid #555;
            opacity: 0.7;
        }

        body.dark-mode .form-group input::placeholder,
        body.dark-mode .form-group textarea::placeholder {
            color: #999;
        }

        body.dark-mode .form-group .birthday-group select {
            background-color: #2a2a2a;
            color: #e0e0e0;
            border: 1px solid #555;
        }

        body.dark-mode .main-content .btn {
            background-color: #0056b3;
        }

        body.dark-mode .main-content .btn:hover {
            background-color: #003d82;
        }


        body.dark-mode .sidebar h2,
        body.dark-mode .sidebar .menu li a {
            color: #e0e0e0;
        }

        body.dark-mode .sidebar .menu li a:hover,
        body.dark-mode .sidebar .menu li a.active {
            background-color: #c9a227;
            color: #ffffff;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h2>Clinic Management</h2>
            <ul class="menu">
                <li><a href="admin_history.php">History</a></li>
                <li><a href="admin_loginsheet.php" class="active">Login Sheet</a></li>
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
            <h1>Admin: Login Sheet</h1>
            <h2>Add Patient</h2>
            <form id="login-sheet-form" action="process_loginsheet.php" method="post">
                <div id="status-message" style="color: #dc3545; margin-bottom: 10px;"></div>
                <div class="form-group form-group-span-3">
                    <label for="patient_id">ID</label>
                    <input type="text" id="patient_id" name="patient_id"
                        placeholder="ID (e.g., 02000123456, CLN012A, or NA)"
                        value="<?= htmlspecialchars($form_data['patient_id'] ?? '') ?>">
                    <div id="patient_id-error" class="error">
                        <?= isset($errors['patient_id']) ? implode('<br>', $errors['patient_id']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="name">NAME / *REQUIRED</label>
                    <input type="text" id="name" name="name" placeholder="Name" required
                        value="<?= htmlspecialchars($form_data['name'] ?? '') ?>">
                    <div id="name-error" class="error">
                        <?= isset($errors['name']) ? implode('<br>', $errors['name']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="gender">GENDER / *REQUIRED</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= isset($form_data['gender']) && $form_data['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= isset($form_data['gender']) && $form_data['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                    <div id="gender-error" class="error">
                        <?= isset($errors['gender']) ? implode('<br>', $errors['gender']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="birthday">BIRTHDAY / *REQUIRED</label>
                    <div class="birthday-group">
                        <select id="birthday_month" name="birthday_month">
                            <option value="">Month</option>
                            <?php $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                            foreach ($months as $month): ?>
                                <option value="<?= $month ?>" <?= $selected_month === $month ? 'selected' : '' ?>><?= $month ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                        <select id="birthday_day" name="birthday_day">
                            <option value="">Day</option>
                            <?php for ($day = 1; $day <= 31; $day++): ?>
                                <option value="<?= sprintf('%02d', $day) ?>" <?= $selected_day === sprintf('%02d', $day) ? 'selected' : '' ?>><?= $day ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="birthday_year" name="birthday_year">
                            <option value="">Year</option>
                            <?php $current_year = date('Y');
                            for ($year = $current_year; $year >= 1900; $year--): ?>
                                <option value="<?= $year ?>" <?= $selected_year == $year ? 'selected' : '' ?>><?= $year ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <input type="hidden" id="birthday" name="birthday"
                        value="<?= htmlspecialchars($form_data['birthday'] ?? '') ?>">
                    <div id="birthday-error" class="error">
                        <?= isset($errors['birthday']) ? implode('<br>', $errors['birthday']) : '' ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="age">AGE</label>
                    <input type="number" id="age" name="age" placeholder="Age" required
                        value="<?= htmlspecialchars($form_data['age'] ?? '') ?>">
                    <div id="age-error" class="error">
                        <?= isset($errors['age']) ? implode('<br>', $errors['age']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="role">ROLE / *REQUIRED</label>
                    <input type="text" id="role" name="role" placeholder="Enter Role" required
                        value="<?= htmlspecialchars($form_data['role'] ?? '') ?>">
                    <div id="role-error" class="error">
                        <?= isset($errors['role']) ? implode('<br>', $errors['role']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="course_section">COURSE AND SECTION / *REQUIRED if student</label>
                    <input type="text" id="course_section" name="course_section" placeholder="Course and Section"
                        value="<?= htmlspecialchars($form_data['course_section'] ?? '') ?>">
                    <div id="course_section-error" class="error">
                        <?= isset($errors['course_section']) ? implode('<br>', $errors['course_section']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="blood_pressure">BLOOD PRESSURE</label>
                    <input type="text" id="blood_pressure" name="blood_pressure" placeholder="e.g., 120/80 or NA"
                        value="<?= htmlspecialchars($form_data['blood_pressure'] ?? '') ?>">
                    <div id="blood_pressure-error" class="error">
                        <?= isset($errors['blood_pressure']) ? implode('<br>', $errors['blood_pressure']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="heart_rate">HEART RATE (BPM)</label>
                    <input type="text" id="heart_rate" name="heart_rate" placeholder="Heart Rate or NA"
                        value="<?= htmlspecialchars($form_data['heart_rate'] ?? '') ?>">
                    <div id="heart_rate-error" class="error">
                        <?= isset($errors['heart_rate']) ? implode('<br>', $errors['heart_rate']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="blood_oxygen">BLOOD OXYGEN (%)</label>
                    <input type="text" id="blood_oxygen" name="blood_oxygen" placeholder="Oxygen Level or NA"
                        value="<?= htmlspecialchars($form_data['blood_oxygen'] ?? '') ?>">
                    <div id="blood_oxygen-error" class="error">
                        <?= isset($errors['blood_oxygen']) ? implode('<br>', $errors['blood_oxygen']) : '' ?>
                    </div>
                </div>
                <div class="form-group form-group-span-2">
                    <label for="height">HEIGHT (cm)</label>
                    <input type="text" id="height" name="height" placeholder="Height (cm) or NA"
                        value="<?= htmlspecialchars($form_data['height'] ?? '') ?>">
                    <div id="height-error" class="error">
                        <?= isset($errors['height']) ? implode('<br>', $errors['height']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="weight">WEIGHT (kg)</label>
                    <input type="text" id="weight" name="weight" placeholder="Weight (kg) or NA"
                        value="<?= htmlspecialchars($form_data['weight'] ?? '') ?>">
                    <div id="weight-error" class="error">
                        <?= isset($errors['weight']) ? implode('<br>', $errors['weight']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="temperature">TEMPERATURE (°C)</label>
                    <input type="text" id="temperature" name="temperature" placeholder="Temperature (Celsius) or NA"
                        value="<?= htmlspecialchars($form_data['temperature'] ?? '') ?>">
                    <div id="temperature-error" class="error">
                        <?= isset($errors['temperature']) ? implode('<br>', $errors['temperature']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="time_out">TIME OUT</label>
                    <input type="time" id="time_out" name="time_out"
                        value="<?= htmlspecialchars($form_data['time_out'] ?? '') ?>">
                    <div id="time_out-error" class="error">
                        <?= isset($errors['time_out']) ? implode('<br>', $errors['time_out']) : '' ?>
                    </div>
                </div>
                <div class="form-group"></div>
                <div class="form-group"></div>
                <div class="form-group">
                    <label for="medicine">MEDICINE</label>
                    <select id="medicine" name="medicine">
                        <option value="">-- Select Medicine --</option>
                        <?php foreach ($medicines as $medicine): ?>
                            <option value="<?= htmlspecialchars($medicine) ?>" <?= isset($form_data['medicine']) && $form_data['medicine'] === $medicine ? 'selected' : '' ?>><?= htmlspecialchars($medicine) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="medicine-error" class="error">
                        <?= isset($errors['medicine']) ? implode('<br>', $errors['medicine']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="quantity">QUANTITY</label>
                    <input type="number" id="quantity" name="quantity" placeholder="Quantity"
                        value="<?= htmlspecialchars($form_data['quantity'] ?? '') ?>">
                    <div id="quantity-error" class="error">
                        <?= isset($errors['quantity']) ? implode('<br>', $errors['quantity']) : '' ?>
                    </div>
                </div>
                <div class="form-group"></div>
                <div class="form-group">
                    <label for="purpose_of_visit">PURPOSE OF VISIT / *REQUIRED</label>
                    <textarea id="purpose_of_visit" name="purpose_of_visit" rows="3"
                        placeholder="Enter Purpose of Visit"><?= htmlspecialchars($form_data['purpose_of_visit'] ?? '') ?></textarea>
                    <div id="purpose_of_visit-error" class="error">
                        <?= isset($errors['purpose_of_visit']) ? implode('<br>', $errors['purpose_of_visit']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="health_history">HEALTH HISTORY / *REQUIRED</label>
                    <textarea id="health_history" name="health_history" rows="3"
                        placeholder="Enter Health History"><?= htmlspecialchars($form_data['health_history'] ?? '') ?></textarea>
                    <div id="health_history-error" class="error">
                        <?= isset($errors['health_history']) ? implode('<br>', $errors['health_history']) : '' ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="remarks">REMARKS / *REQUIRED</label>
                    <textarea id="remarks" name="remarks" rows="3"
                        placeholder="Enter Remarks"><?= htmlspecialchars($form_data['remarks'] ?? '') ?></textarea>
                    <div id="remarks-error" class="error">
                        <?= isset($errors['remarks']) ? implode('<br>', $errors['remarks']) : '' ?>
                    </div>
                </div>
                <div class="submit-group">
                    <button type="submit" class="btn">ADD PATIENT ENTRY</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function calculateAge(day, month, year) {
            if (!day || !month || !year) return -1;
            const monthMap = {
                'January': '01',
                'February': '02',
                'March': '03',
                'April': '04',
                'May': '05',
                'June': '06',
                'July': '07',
                'August': '08',
                'September': '09',
                'October': '10',
                'November': '11',
                'December': '12'
            };
            const birthDate = new Date(`${year}-${monthMap[month]}-${day}`);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) age--;
            return age;
        }

        function updateBirthdayField() {
            const day = document.getElementById('birthday_day').value;
            const month = document.getElementById('birthday_month').value;
            const year = document.getElementById('birthday_year').value;
            const birthdayField = document.getElementById('birthday');
            const birthdayError = document.getElementById('birthday-error');
            if (day && month && year) {
                const monthMap = {
                    'January': '01',
                    'February': '02',
                    'March': '03',
                    'April': '04',
                    'May': '05',
                    'June': '06',
                    'July': '07',
                    'August': '08',
                    'September': '09',
                    'October': '10',
                    'November': '11',
                    'December': '12'
                };
                const formattedDate = `${year}-${monthMap[month]}-${day}`;
                birthdayField.value = formattedDate;
                const age = calculateAge(day, month, year);
                if (age >= 0) {
                    document.getElementById('age').value = age;
                    birthdayError.textContent = '';
                } else {
                    document.getElementById('age').value = '';
                    birthdayError.textContent = 'Birthday cannot be in the future';
                }
            } else {
                birthdayField.value = '';
                document.getElementById('age').value = '';
            }
        }

        document.getElementById('birthday_day').addEventListener('change', updateBirthdayField);
        document.getElementById('birthday_month').addEventListener('change', updateBirthdayField);
        document.getElementById('birthday_year').addEventListener('change', updateBirthdayField);

        function toggleCourseSection() {
            const role = document.getElementById('role').value.trim().toLowerCase();
            const courseSection = document.getElementById('course_section');
            const isDarkMode = document.body.classList.contains('dark-mode');
            const enabledBg = isDarkMode ? '#2a2a2a' : '#fff';
            const enabledText = isDarkMode ? '#e0e0e0' : '#333';
            const disabledBg = isDarkMode ? '#333' : '#f0f0f0';
            const disabledText = isDarkMode ? '#888' : '#888';

            if (role === 'faculty' || role === 'staff' || role !== 'student') {
                courseSection.disabled = true;
                courseSection.value = role === 'student' ? '' : role;
                courseSection.style.backgroundColor = disabledBg;
                courseSection.style.color = disabledText;
            } else {
                courseSection.disabled = false;
                if (!courseSection.hasAttribute('readonly')) {
                    courseSection.style.backgroundColor = enabledBg;
                    courseSection.style.color = enabledText;
                }
            }
        }

        function clearAndUnlockDependentFields() {
            const isDarkMode = document.body.classList.contains('dark-mode');
            const enabledBg = isDarkMode ? '#2a2a2a' : '#fff';
            const enabledText = isDarkMode ? '#e0e0e0' : '#333';
            const patientIdField = document.getElementById('patient_id');
            const nameField = document.getElementById('name');
            if (patientIdField.hasAttribute('readonly')) {
                patientIdField.removeAttribute('readonly');
                patientIdField.style.backgroundColor = enabledBg;
                patientIdField.style.color = enabledText;
            }
            if (nameField.hasAttribute('readonly')) {
                nameField.removeAttribute('readonly');
                nameField.style.backgroundColor = enabledBg;
                nameField.style.color = enabledText;
            }
            const genderField = document.getElementById('gender');
            genderField.value = '';
            genderField.removeAttribute('disabled');
            genderField.style.backgroundColor = enabledBg;
            genderField.style.color = enabledText;
            const birthdayDayField = document.getElementById('birthday_day');
            birthdayDayField.value = '';
            birthdayDayField.removeAttribute('disabled');
            birthdayDayField.style.backgroundColor = enabledBg;
            birthdayDayField.style.color = enabledText;
            const birthdayMonthField = document.getElementById('birthday_month');
            birthdayMonthField.value = '';
            birthdayMonthField.removeAttribute('disabled');
            birthdayMonthField.style.backgroundColor = enabledBg;
            birthdayMonthField.style.color = enabledText;
            const birthdayYearField = document.getElementById('birthday_year');
            birthdayYearField.value = '';
            birthdayYearField.removeAttribute('disabled');
            birthdayYearField.style.backgroundColor = enabledBg;
            birthdayYearField.style.color = enabledText;
            document.getElementById('birthday').value = '';
            const ageField = document.getElementById('age');
            ageField.value = '';
            ageField.removeAttribute('readonly');
            ageField.style.backgroundColor = enabledBg;
            ageField.style.color = enabledText;
            const roleField = document.getElementById('role');
            roleField.value = '';
            roleField.removeAttribute('readonly');
            roleField.style.backgroundColor = enabledBg;
            roleField.style.color = enabledText;
            const courseSectionField = document.getElementById('course_section');
            courseSectionField.value = '';
            courseSectionField.removeAttribute('readonly');
            courseSectionField.style.backgroundColor = enabledBg;
            courseSectionField.style.color = enabledText;
            document.getElementById('blood_pressure').value = '';
            document.getElementById('heart_rate').value = '';
            document.getElementById('blood_oxygen').value = '';
            document.getElementById('height').value = '';
            document.getElementById('weight').value = '';
            document.getElementById('temperature').value = '';
            document.getElementById('time_out').value = '';
            document.getElementById('purpose_of_visit').value = '';
            document.getElementById('health_history').value = '';
            document.getElementById('remarks').value = '';
            toggleCourseSection();
        }

        document.getElementById('patient_id').addEventListener('blur', function () {
            const patientId = this.value.trim();
            const naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];
            const patientIdError = document.getElementById('patient_id-error');
            const statusMessage = document.getElementById('status-message');
            patientIdError.textContent = '';
            statusMessage.textContent = '';
            if (patientId && !naValues.includes(patientId)) {
                if (!/^(?:\d{11}|[A-Z]{3}\d{3}[A-Z]|[A-Z]{3}\d{4}[A-Z]|[A-Z]{3}\d{4})$/i.test(patientId)) {
                    patientIdError.textContent = 'ID must be 11 digits (e.g., 10000123456), 3 letters (e.g., CLN), or formats like CLN012A, CLN0123A, CLN0123, or NA';
                    clearAndUnlockDependentFields();
                    return;
                }
                fetch('check_student.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'check_patient_id=' + encodeURIComponent(patientId)
                })
                    .then(response => response.json())
                    .then(data => {
                        const isDarkMode = document.body.classList.contains('dark-mode');
                        const readonlyBg = isDarkMode ? '#333' : '#f0f0f0';
                        const readonlyText = isDarkMode ? '#888' : '#888';
                        if (data.error) {
                            console.log(data.error);
                            statusMessage.textContent = 'ID not found, please enter details manually.';
                            clearAndUnlockDependentFields();
                            if (patientId.startsWith('02000') || patientId.startsWith('10000')) {
                                document.getElementById('role').value = 'Student';
                                toggleCourseSection();
                            }
                        } else {
                            statusMessage.textContent = '';
                            document.getElementById('name').value = data.name || '';
                            document.getElementById('name').value = data.name || '';
                            document.getElementById('gender').value = data.gender || '';
                            if (data.birthday) {
                                const date = new Date(data.birthday);
                                document.getElementById('birthday_day').value = date.getDate().toString().padStart(2, '0');
                                const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                document.getElementById('birthday_month').value = months[date.getMonth()];
                                document.getElementById('birthday_year').value = date.getFullYear();
                                document.getElementById('birthday').value = data.birthday;
                            }
                            document.getElementById('age').value = data.age || '';
                            document.getElementById('role').value = data.role || '';
                            document.getElementById('course_section').value = data.course_section || '';
                            document.getElementById('blood_pressure').value = '';
                            document.getElementById('heart_rate').value = '';
                            document.getElementById('blood_oxygen').value = '';
                            document.getElementById('height').value = '';
                            document.getElementById('weight').value = '';
                            document.getElementById('temperature').value = '';
                            document.getElementById('time_out').value = '';
                            document.getElementById('purpose_of_visit').value = '';
                            document.getElementById('health_history').value = '';
                            document.getElementById('remarks').value = '';
                            toggleCourseSection();
                        }
                    })
                    .catch(error => {
                        console.error('Error checking Patient ID:', error);
                        statusMessage.textContent = 'Error checking ID, please try again.';
                        clearAndUnlockDependentFields();
                    });
            } else {
                clearAndUnlockDependentFields();
                patientIdError.textContent = '';
            }
        });

        document.getElementById('name').addEventListener('blur', function () {
            const name = this.value.trim();
            const patientIdField = document.getElementById('patient_id');
            const isNameLocked = this.hasAttribute('readonly');
            const statusMessage = document.getElementById('status-message');
            statusMessage.textContent = '';
            if (name && !isNameLocked && (!patientIdField.value.trim() || ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'].includes(patientIdField.value.trim())) && !patientIdField.hasAttribute('readonly')) {
                fetch('check_name.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'check_name=' + encodeURIComponent(name)
                })
                    .then(response => response.json())
                    .then(data => {
                        const isDarkMode = document.body.classList.contains('dark-mode');
                        const readonlyBg = isDarkMode ? '#333' : '#f0f0f0';
                        const readonlyText = isDarkMode ? '#888' : '#888';
                        if (!data.error && data.patient_id) {
                            statusMessage.textContent = '';
                            if (!patientIdField.value || ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'].includes(patientIdField.value.trim())) {
                                patientIdField.value = data.patient_id || 'NA';
                            }
                            document.getElementById('gender').value = data.gender || '';
                            if (data.birthday) {
                                const date = new Date(data.birthday);
                                document.getElementById('birthday_day').value = date.getDate().toString().padStart(2, '0');
                                const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                document.getElementById('birthday_month').value = months[date.getMonth()];
                                document.getElementById('birthday_year').value = date.getFullYear();
                                document.getElementById('birthday').value = data.birthday;
                            }
                            document.getElementById('age').value = data.age || '';
                            document.getElementById('role').value = data.role || '';
                            document.getElementById('course_section').value = data.course_section || '';
                            document.getElementById('blood_pressure').value = '';
                            document.getElementById('heart_rate').value = '';
                            document.getElementById('blood_oxygen').value = '';
                            document.getElementById('height').value = '';
                            document.getElementById('weight').value = '';
                            document.getElementById('temperature').value = '';
                            document.getElementById('time_out').value = '';
                            document.getElementById('purpose_of_visit').value = '';
                            document.getElementById('health_history').value = '';
                            document.getElementById('remarks').value = '';
                            toggleCourseSection();
                        } else {
                            statusMessage.textContent = 'Name not found, please enter details manually.';
                            clearAndUnlockDependentFields();
                        }
                    })
                    .catch(error => {
                        console.error('Error checking Name:', error);
                        statusMessage.textContent = 'Error checking name, please try again.';
                        clearAndUnlockDependentFields();
                    });
            } else if (!name && !isNameLocked) {
                clearAndUnlockDependentFields();
            }
        });

        window.onload = function () {
            toggleCourseSection();
            document.getElementById('role').addEventListener('change', toggleCourseSection);
        };

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.error').forEach(error => {
                if (error.textContent.trim() !== '') error.style.display = 'block';
            });
            const themeToggle = document.getElementById('checkbox');
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark-mode');
                themeToggle.checked = true;
                toggleCourseSection(); // Ensure initial styles are correct
            }
            themeToggle.addEventListener('change', function () {
                document.body.classList.toggle('dark-mode', this.checked);
                localStorage.setItem('theme', this.checked ? 'dark' : 'light');
                toggleCourseSection(); // Update styles on toggle
            });
        });

        document.querySelector('.logout').addEventListener('click', function (event) {
            event.preventDefault();
            if (confirm('Are you sure you want to logout?')) window.location.href = this.href;
        });

        document.getElementById('login-sheet-form').addEventListener('submit', function (event) {
            let isValid = true;
            document.querySelectorAll('.error').forEach(error => error.textContent = '');
            const naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];
            const patientId = document.getElementById('patient_id').value.trim();
            const patientIdField = document.getElementById('patient_id');
            if (!patientIdField.hasAttribute('readonly') && patientId && !/^(?:\d{11}|[A-Z]{3}\d{3}[A-Z]|[A-Z]{3}\d{4}[A-Z]|[A-Z]{3}\d{4})$/i.test(patientId) && !naValues.includes(patientId)) {
                document.getElementById('patient_id-error').textContent = 'ID must be 11 digits, 3 letters, or formats like CLN012A, CLN0123A, CLN0123, or NA';
                isValid = false;
            } else if (!patientIdField.hasAttribute('readonly') && !patientId) {
                const nameField = document.getElementById('name');
                if (!nameField.hasAttribute('readonly') && !nameField.value.trim()) {
                    document.getElementById('patient_id-error').textContent = 'ID or Name is required';
                    isValid = false;
                }
            }
            const name = document.getElementById('name').value.trim();
            const nameField = document.getElementById('name');
            if (!nameField.hasAttribute('readonly')) {
                if (!name || !/^[A-Za-z\s]{2,50}$/.test(name)) {
                    document.getElementById('name-error').textContent = 'Name must contain only letters and spaces, 2-50 characters';
                    isValid = false;
                }
            } else if (!name) {
                document.getElementById('name-error').textContent = 'Name is required';
                isValid = false;
            }
            const gender = document.getElementById('gender');
            if (!gender.hasAttribute('disabled') && !gender.value) {
                document.getElementById('gender-error').textContent = 'Gender is required';
                isValid = false;
            }
            const birthdayDay = document.getElementById('birthday_day').value;
            const birthdayMonth = document.getElementById('birthday_month').value;
            const birthdayYear = document.getElementById('birthday_year').value;
            if (birthdayDay && birthdayMonth && birthdayYear) {
                const monthMap = {
                    'January': '01',
                    'February': '02',
                    'March': '03',
                    'April': '04',
                    'May': '05',
                    'June': '06',
                    'July': '07',
                    'August': '08',
                    'September': '09',
                    'October': '10',
                    'November': '11',
                    'December': '12'
                };
                const birthDate = new Date(`${birthdayYear}-${monthMap[birthdayMonth]}-${birthdayDay}`);
                if (birthDate > new Date()) {
                    document.getElementById('birthday-error').textContent = 'Birthday cannot be in the future';
                    isValid = false;
                }
            }
            const age = document.getElementById('age').value;
            if (!age || age < 1 || age > 100) {
                document.getElementById('age-error').textContent = 'Age must be between 1 and 100';
                isValid = false;
            }
            const role = document.getElementById('role').value.trim();
            if (!role) {
                document.getElementById('role-error').textContent = 'Role is required';
                isValid = false;
            }
            if (role.toLowerCase() === 'student') {
                const courseSection = document.getElementById('course_section');
                if (!courseSection.hasAttribute('readonly') && (!courseSection.value.trim() || !/^[A-Za-z0-9\s\-\/]{2,20}$/.test(courseSection.value.trim()))) {
                    document.getElementById('course_section-error').textContent = 'Course and Section must be 2-20 characters';
                    isValid = false;
                }
            }
            const bloodPressure = document.getElementById('blood_pressure').value.trim();
            if (bloodPressure && !/^\d{2,3}\/\d{2,3}$/.test(bloodPressure) && !naValues.includes(bloodPressure)) {
                document.getElementById('blood_pressure-error').textContent = 'Format: e.g., 120/80 or NA';
                isValid = false;
            }
            const heartRate = document.getElementById('heart_rate').value.trim();
            if (heartRate && !/^\d+$/.test(heartRate) && !naValues.includes(heartRate)) {
                document.getElementById('heart_rate-error').textContent = 'Heart Rate must be a number or NA';
                isValid = false;
            }
            if (heartRate && !naValues.includes(heartRate) && (heartRate < 1 || heartRate > 200)) {
                document.getElementById('heart_rate-error').textContent = 'Heart Rate must be between 1 and 200';
                isValid = false;
            }
            const bloodOxygen = document.getElementById('blood_oxygen').value.trim();
            if (bloodOxygen && !/^\d+$/.test(bloodOxygen) && !naValues.includes(bloodOxygen)) {
                document.getElementById('blood_oxygen-error').textContent = 'Blood Oxygen must be a number or NA';
                isValid = false;
            }
            if (bloodOxygen && !naValues.includes(bloodOxygen) && (bloodOxygen < 1 || bloodOxygen > 100)) {
                document.getElementById('blood_oxygen-error').textContent = 'Blood Oxygen must be between 1 and 100';
                isValid = false;
            }
            const height = document.getElementById('height').value.trim();
            if (height && !/^\d+(\.\d+)?$/.test(height) && !naValues.includes(height)) {
                document.getElementById('height-error').textContent = 'Height must be a number or NA';
                isValid = false;
            }
            if (height && !naValues.includes(height) && (height < 1 || height > 251)) {
                document.getElementById('height-error').textContent = 'Height must be between 1 and 251 cm';
                isValid = false;
            }
            const weight = document.getElementById('weight').value.trim();
            if (weight && !/^\d+(\.\d+)?$/.test(weight) && !naValues.includes(weight)) {
                document.getElementById('weight-error').textContent = 'Weight must be a number or NA';
                isValid = false;
            }
            if (weight && !naValues.includes(weight) && (weight < 1 || weight > 500)) {
                document.getElementById('weight-error').textContent = 'Weight must be between 1 and 500 kg';
                isValid = false;
            }
            const temperature = document.getElementById('temperature').value.trim();
            if (temperature && !/^\d+(\.\d+)?$/.test(temperature) && !naValues.includes(temperature)) {
                document.getElementById('temperature-error').textContent = 'Temperature must be a number or NA';
                isValid = false;
            }
            if (temperature && !naValues.includes(temperature) && (temperature < 1 || temperature > 100)) {
                document.getElementById('temperature-error').textContent = 'Temperature must be between 1 and 100°C';
                isValid = false;
            }
            if (!isValid) event.preventDefault();
        });
    </script>
</body>

</html>