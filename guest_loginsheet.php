<?php
session_start();

// Set timezone to ensure consistent time
date_default_timezone_set('Asia/Manila');

// Check if the user is a guest
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guest') {
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

// Retrieve form data and errors from session
$form_data = $_SESSION['form_data'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];

// Clear session data after retrieving
unset($_SESSION['form_data']);
unset($_SESSION['form_errors']);

// Check if an ID was submitted to prefill the form
$existing_record_role = ''; // For role field
if (isset($_POST['check_patient_id'])) {
    $patient_id = trim($_POST['check_patient_id']);
    $naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];
    if (!empty($patient_id) && (preg_match('/^(?:\d{11}|[A-Z]{3}\d{3}[A-Z]|[A-Z]{3}\d{4}[A-Z]|[A-Z]{3}\d{4})$/i', $patient_id) || in_array($patient_id, $naValues))) {
        if (!in_array($patient_id, $naValues)) { // Only query database if not NA
            $stmt = $db->prepare("SELECT * FROM clinic_logs WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing_record) {
                $form_data = array_merge($form_data, $existing_record);
                // $existing_record_role = 'readonly'; // Removed readonly
            }
        }
    }
}

// Prepare birthday values for pre-filling dropdowns
$birthday = isset($form_data['birthday']) ? $form_data['birthday'] : '';
$selected_day = '';
$selected_month = '';
$selected_year = '';
if ($birthday) {
    try {
        $date = new DateTime($birthday);
        $selected_day = $date->format('d');
        $selected_month = $date->format('F'); // Full month name (e.g., January)
        $selected_year = $date->format('Y');
    } catch (Exception $e) {
        $birthday = '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest: Login Sheet</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="images/sti_logo.png" type="image/png">
    <style>
        /* --- Base Form Styles (Light Mode Default) --- */
        .main-content h1,
        .main-content h2 {
            color: #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
            margin-top: 0;
        }

        .form-group label {
            color: #555;
            margin-bottom: 5px;
            display: block;
            font-weight: bold;
            font-size: 2em;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="time"],
        .form-group input[list],
        .form-group select,
        .form-group textarea {
            background-color: #fff;
            color: #333;
            border: 1px solid #ccc;
            padding: 8px 10px;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 5px;
            font-size: 3em;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        /* Style for fields that are programmatically disabled (e.g., course_section) */
        .form-group input[disabled],
        .form-group select[disabled] {
            background-color: #f0f0f0;
            /* Light grey for disabled in light mode */
            color: #888;
            /* Dimmer text for disabled */
            cursor: not-allowed;
        }

        /* Style for readonly fields if any are still used (though user requested no readonly for pre-fills) */
        .form-group input[readonly] {
            background-color: #e9ecef;
            /* Slightly different from disabled */
            color: #495057;
            cursor: default;
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

        .main-content .btn {
            background-color: #007bff;
            color: #ffffff;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 3em;
            font-weight: bold;
            transition: background-color 0.3s ease;
            display: block;
            width: auto;
            margin: 20px auto 0;
        }

        .main-content .btn:hover {
            background-color: #0056b3;
        }

        /* --- Toggle Switch Styles --- */
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

        /* --- Dark Mode Styles --- */
        body.dark-mode {
            background-color: #333;
            color: #e0e0e0;
        }

        body.dark-mode .main-content h1,
        body.dark-mode .main-content h2 {
            color: #e0e0e0;
        }

        body.dark-mode #login-sheet-form {
            color: #e0e0e0;
        }

        body.dark-mode .form-group label {
            color: #d3d3d3;
        }

        body.dark-mode .form-group input[type="text"],
        body.dark-mode .form-group input[type="number"],
        body.dark-mode .form-group input[type="time"],
        body.dark-mode .form-group input[list],
        body.dark-mode .form-group select,
        body.dark-mode .form-group textarea {
            background-color: #282828;
            color: #e0e0e0;
            border: 1px solid #555;
        }

        /* Style for fields that are programmatically disabled (e.g., course_section) in dark mode */
        body.dark-mode .form-group input[disabled],
        body.dark-mode .form-group select[disabled] {
            background-color: #1e1e1e;
            /* Darker for disabled in dark mode */
            color: #a0a0a0;
            /* Dimmer text for disabled */
            border: 1px solid #444;
            cursor: not-allowed;
        }

        /* Style for readonly fields in dark mode if any are still used */
        body.dark-mode .form-group input[readonly] {
            background-color: #2c3034;
            /* Slightly different dark for readonly */
            color: #adb5bd;
            cursor: default;
        }


        body.dark-mode .form-group input::placeholder,
        body.dark-mode .form-group textarea::placeholder {
            color: #888;
        }

        body.dark-mode .form-group .birthday-group select {
            background-color: #282828;
            /* Use standard input dark bg for birthday selects */
            color: #e0e0e0;
            border: 1px solid #555;
        }

        /* Autofill styles for WebKit browsers (Chrome, Safari) */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            transition: background-color 5000s ease-in-out 0s;
            -webkit-text-fill-color: #333 !important;
        }

        body.dark-mode input:-webkit-autofill,
        body.dark-mode input:-webkit-autofill:hover,
        body.dark-mode input:-webkit-autofill:focus,
        body.dark-mode input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #282828 inset !important;
            -webkit-text-fill-color: #e0e0e0 !important;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h2>Clinic Management</h2>
            <ul class="menu">
                <li><a href="guest_loginsheet.php" class="active">Login Sheet</a></li>
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
            <h1>Guest: Login Sheet</h1>
            <h2>Add Patient</h2>
            <form id="login-sheet-form" action="process_loginsheet.php" method="POST">
                <div id="status-message" style="color: red; margin-bottom: 10px;"></div>
                <!-- Row 1: ID (Span 3) -->
                <div class="form-group form-group-span-3">
                    <label for="patient_id">ID</label>
                    <input type="text" id="patient_id" name="patient_id"
                        placeholder="ID (e.g., 02000123456, CLN012A, CLN0123A, CLN0123, NA)"
                        value="<?= htmlspecialchars($form_data['patient_id'] ?? '') ?>">
                    <div id="patient_id-error" class="error">
                        <?php if (isset($errors['patient_id'])): ?>
                            <?= implode('<br>', $errors['patient_id']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Row 2: Name, Gender, Birthday -->
                <div class="form-group">
                    <label for="name">NAME / *REQUIRED</label>
                    <input type="text" id="name" name="name" placeholder="Name" required
                        value="<?= htmlspecialchars($form_data['name'] ?? '') ?>">
                    <div id="name-error" class="error">
                        <?php if (isset($errors['name'])): ?>
                            <?= implode('<br>', $errors['name']) ?>
                        <?php endif; ?>
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
                        <?php if (isset($errors['gender'])): ?>
                            <?= implode('<br>', $errors['gender']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="birthday">BIRTHDAY / *REQUIRED</label>
                    <div class="birthday-group">
                        <select id="birthday_month" name="birthday_month">
                            <option value="">Month</option>
                            <?php
                            $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                            foreach ($months as $month): ?>
                                <option value="<?= $month ?>" <?= $selected_month == $month ? 'selected' : '' ?>>
                                    <?= $month ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="birthday_day" name="birthday_day">
                            <option value="">Day</option>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <option value="<?= sprintf('%02d', $d) ?>" <?= $selected_day == sprintf('%02d', $d) ? 'selected' : '' ?>>
                                    <?= $d ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select id="birthday_year" name="birthday_year">
                            <option value="">Year</option>
                            <?php
                            $currentYear = date('Y');
                            for ($y = $currentYear; $y >= 1900; $y--): ?>
                                <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>>
                                    <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <input type="hidden" id="birthday" name="birthday"
                        value="<?= htmlspecialchars($form_data['birthday'] ?? '') ?>">
                    <div id="birthday-error" class="error">
                        <?php if (isset($errors['birthday'])): ?>
                            <?= implode('<br>', $errors['birthday']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Row 3: Age, Role, Course and Section -->
                <div class="form-group">
                    <label for="age">AGE</label>
                    <input type="number" id="age" name="age" placeholder="Age" required
                        value="<?= htmlspecialchars($form_data['age'] ?? '') ?>">
                    <div id="age-error" class="error">
                        <?php if (isset($errors['age'])): ?>
                            <?= implode('<br>', $errors['age']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="role">ROLE / *REQUIRED</label>
                    <input type="text" id="role" name="role" placeholder="Role (e.g., Student, Faculty, Staff)" required
                        value="<?= htmlspecialchars($form_data['role'] ?? '') ?>"> <!-- Removed readonly from PHP -->
                    <div id="role-error" class="error">
                        <?php if (isset($errors['role'])): ?>
                            <?= implode('<br>', $errors['role']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="course_section">COURSE AND SECTION / *REQUIRED if student</label>
                    <input type="text" id="course_section" name="course_section" placeholder="Course and Section"
                        value="<?= htmlspecialchars($form_data['course_section'] ?? '') ?>">
                    <div id="course_section-error" class="error">
                        <?php if (isset($errors['course_section'])): ?>
                            <?= implode('<br>', $errors['course_section']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Other form groups remain the same -->
                <!-- Row 4: Blood Pressure, Heart Rate, Blood Oxygen -->
                <div class="form-group">
                    <label for="blood_pressure">BLOOD PRESSURE</label>
                    <input type="text" id="blood_pressure" name="blood_pressure" placeholder="e.g., 120/80 or NA"
                        value="<?= htmlspecialchars($form_data['blood_pressure'] ?? '') ?>">
                    <div id="blood_pressure-error" class="error">
                        <?php if (isset($errors['blood_pressure'])): ?>
                            <?= implode('<br>', $errors['blood_pressure']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="heart_rate">HEART RATE (BPM)</label>
                    <input type="text" id="heart_rate" name="heart_rate" placeholder="e.g., 75 or NA"
                        value="<?= htmlspecialchars($form_data['heart_rate'] ?? '') ?>">
                    <div id="heart_rate-error" class="error">
                        <?php if (isset($errors['heart_rate'])): ?>
                            <?= implode('<br>', $errors['heart_rate']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="blood_oxygen">BLOOD OXYGEN (%)</label>
                    <input type="text" id="blood_oxygen" name="blood_oxygen" placeholder="e.g., 98 or NA"
                        value="<?= htmlspecialchars($form_data['blood_oxygen'] ?? '') ?>">
                    <div id="blood_oxygen-error" class="error">
                        <?php if (isset($errors['blood_oxygen'])): ?>
                            <?= implode('<br>', $errors['blood_oxygen']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Row 5: Height (Span 2), Weight -->
                <div class="form-group form-group-span-2">
                    <label for="height">HEIGHT (cm)</label>
                    <input type="text" id="height" name="height" placeholder="e.g., 170 or NA"
                        value="<?= htmlspecialchars($form_data['height'] ?? '') ?>">
                    <div id="height-error" class="error">
                        <?php if (isset($errors['height'])): ?>
                            <?= implode('<br>', $errors['height']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="weight">WEIGHT (kg)</label>
                    <input type="text" id="weight" name="weight" placeholder="e.g., 70 or NA"
                        value="<?= htmlspecialchars($form_data['weight'] ?? '') ?>">
                    <div id="weight-error" class="error">
                        <?php if (isset($errors['weight'])): ?>
                            <?= implode('<br>', $errors['weight']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Row 6: Temperature, Time Out, (Empty) -->
                <div class="form-group">
                    <label for="temperature">TEMPERATURE (°C)</label>
                    <input type="text" id="temperature" name="temperature" placeholder="e.g., 36.5 or NA"
                        value="<?= htmlspecialchars($form_data['temperature'] ?? '') ?>">
                    <div id="temperature-error" class="error">
                        <?php if (isset($errors['temperature'])): ?>
                            <?= implode('<br>', $errors['temperature']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="time_out">TIME OUT</label>
                    <input type="time" id="time_out" name="time_out"
                        value="<?= htmlspecialchars($form_data['time_out'] ?? '') ?>">
                    <div id="time_out-error" class="error">
                        <?php if (isset($errors['time_out'])): ?>
                            <?= implode('<br>', $errors['time_out']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group"></div>
                <div class="form-group"></div>


                <!-- Row 8: Purpose of Visit, Health History, Remarks -->
                <div class="form-group">
                    <label for="purpose_of_visit">PURPOSE OF VISIT / *REQUIRED</label>
                    <textarea id="purpose_of_visit" name="purpose_of_visit" rows="3"
                        placeholder="Enter Purpose of Visit"><?= htmlspecialchars($form_data['purpose_of_visit'] ?? '') ?></textarea>
                    <div id="purpose_of_visit-error" class="error">
                        <?php if (isset($errors['purpose_of_visit'])): ?>
                            <?= implode('<br>', $errors['purpose_of_visit']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="health_history">HEALTH HISTORY / *REQUIRED</label>
                    <textarea id="health_history" name="health_history" rows="3"
                        placeholder="Enter Health History"><?= htmlspecialchars($form_data['health_history'] ?? '') ?></textarea>
                    <div id="health_history-error" class="error">
                        <?php if (isset($errors['health_history'])): ?>
                            <?= implode('<br>', $errors['health_history']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="remarks">REMARKS / *REQUIRED</label>
                    <textarea id="remarks" name="remarks" rows="3"
                        placeholder="Enter Remarks"><?= htmlspecialchars($form_data['remarks'] ?? '') ?></textarea>
                    <div id="remarks-error" class="error">
                        <?php if (isset($errors['remarks'])): ?>
                            <?= implode('<br>', $errors['remarks']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hidden Fields -->
                <input type="hidden" id="medicine" name="medicine" value="">
                <input type="hidden" id="quantity" name="quantity" value="">

                <!-- Row 9: Submit Button -->
                <div class="submit-group">
                    <button type="submit" class="btn">ADD PATIENT ENTRY</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Function to calculate age from birthday and validate date
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
            if (!monthMap[month]) return -1;
            const birthDate = new Date(`${year}-${monthMap[month]}-${day}`);
            const today = new Date();
            if (isNaN(birthDate.getTime()) || birthDate.getDate() != parseInt(day)) {
                return -2;
            }
            if (birthDate > today) {
                return -3;
            }
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            return age;
        }

        function updateBirthdayField() {
            const day = document.getElementById('birthday_day').value;
            const month = document.getElementById('birthday_month').value;
            const year = document.getElementById('birthday_year').value;
            const birthdayField = document.getElementById('birthday');
            const birthdayError = document.getElementById('birthday-error');
            const ageField = document.getElementById('age');
            const isDarkMode = document.body.classList.contains('dark-mode');

            const editableBg = isDarkMode ? '#282828' : '#fff';
            const editableText = isDarkMode ? '#e0e0e0' : '#333';
            // const disabledBg = isDarkMode ? '#1e1e1e' : '#f0f0f0'; // For truly disabled fields
            // const disabledText = isDarkMode ? '#a0a0a0' : '#888';

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
                if (!monthMap[month]) {
                    birthdayError.textContent = 'Invalid month selected.';
                    ageField.value = '';
                    // ageField.removeAttribute('readonly'); // Age is never readonly now
                    ageField.style.backgroundColor = editableBg;
                    ageField.style.color = editableText;
                    birthdayField.value = '';
                    return;
                }
                const formattedDate = `${year}-${monthMap[month]}-${day}`;
                birthdayField.value = formattedDate;

                const age = calculateAge(day, month, year);
                if (age === -2) {
                    birthdayError.textContent = 'Invalid date (e.g., February 30)';
                    ageField.value = '';
                    birthdayField.value = '';
                } else if (age === -3) {
                    birthdayError.textContent = 'Birthday cannot be in the future';
                    ageField.value = '';
                    birthdayField.value = '';
                } else if (age >= 0) {
                    ageField.value = age;
                    birthdayError.textContent = '';
                } else {
                    ageField.value = '';
                }
                // Age field is always editable
                // ageField.removeAttribute('readonly'); // Ensure it's not readonly
                ageField.style.backgroundColor = editableBg;
                ageField.style.color = editableText;

            } else {
                birthdayField.value = '';
                ageField.value = '';
                // ageField.removeAttribute('readonly');
                ageField.style.backgroundColor = editableBg;
                ageField.style.color = editableText;
                if (day || month || year) {
                    birthdayError.textContent = 'Please select full birthday.';
                } else {
                    birthdayError.textContent = '';
                }
            }
        }

        document.getElementById('birthday_day').addEventListener('change', updateBirthdayField);
        document.getElementById('birthday_month').addEventListener('change', updateBirthdayField);
        document.getElementById('birthday_year').addEventListener('change', updateBirthdayField);

        function toggleCourseSection() {
            const roleInput = document.getElementById('role');
            const role = roleInput.value.trim().toLowerCase();
            const courseSection = document.getElementById('course_section');
            const isDarkMode = document.body.classList.contains('dark-mode');

            const editableBg = isDarkMode ? '#282828' : '#fff';
            const editableText = isDarkMode ? '#e0e0e0' : '#333';
            const disabledBg = isDarkMode ? '#1e1e1e' : '#f0f0f0';
            const disabledText = isDarkMode ? '#a0a0a0' : '#888';

            if (role !== 'student') {
                courseSection.disabled = true; // Still disable if not student
                courseSection.style.backgroundColor = disabledBg;
                courseSection.style.color = disabledText;
                courseSection.value = (role && role !== 'student') ? 'N/A for ' + roleInput.value : '';
            } else {
                courseSection.disabled = false;
                courseSection.style.backgroundColor = editableBg;
                courseSection.style.color = editableText;
                if (courseSection.value.startsWith('N/A for')) courseSection.value = '';
            }
        }

        function applyEditableStyles(element, isDarkMode) {
            const editableBg = isDarkMode ? '#282828' : '#fff';
            const editableText = isDarkMode ? '#e0e0e0' : '#333';
            element.style.backgroundColor = editableBg;
            element.style.color = editableText;
            element.removeAttribute('readonly'); // Ensure readonly is removed
            if (element.tagName === 'SELECT') {
                element.removeAttribute('disabled'); // Ensure disabled is removed for selects
            }
        }

        // This function might still be useful for fields that are truly disabled by logic (like course_section)
        function applyDisabledStyles(element, isDarkMode) {
            const disabledBg = isDarkMode ? '#1e1e1e' : '#f0f0f0';
            const disabledText = isDarkMode ? '#a0a0a0' : '#888';
            element.style.backgroundColor = disabledBg;
            element.style.color = disabledText;
        }


        function clearAndUnlockDependentFields(clearIdAndName = true) {
            const isDarkMode = document.body.classList.contains('dark-mode');

            const fieldsToClear = [{
                id: 'gender',
                type: 'select'
            }, {
                id: 'birthday_day',
                type: 'select'
            },
            {
                id: 'birthday_month',
                type: 'select'
            }, {
                id: 'birthday_year',
                type: 'select'
            },
            {
                id: 'age',
                type: 'input'
            }, {
                id: 'role',
                type: 'input'
            },
            {
                id: 'course_section',
                type: 'input'
            }, {
                id: 'blood_pressure',
                type: 'input'
            },
            {
                id: 'heart_rate',
                type: 'input'
            }, {
                id: 'blood_oxygen',
                type: 'input'
            },
            {
                id: 'height',
                type: 'input'
            }, {
                id: 'weight',
                type: 'input'
            },
            {
                id: 'temperature',
                type: 'input'
            }, {
                id: 'time_out',
                type: 'input'
            },
            {
                id: 'purpose_of_visit',
                type: 'textarea'
            }, {
                id: 'health_history',
                type: 'textarea'
            },
            {
                id: 'remarks',
                type: 'textarea'
            }
            ];

            if (clearIdAndName) {
                fieldsToClear.unshift({
                    id: 'name',
                    type: 'input'
                });
                const patientIdField = document.getElementById('patient_id');
                applyEditableStyles(patientIdField, isDarkMode);
            }


            fieldsToClear.forEach(fieldInfo => {
                const element = document.getElementById(fieldInfo.id);
                if (element) {
                    element.value = '';
                    applyEditableStyles(element, isDarkMode); // Make them all editable
                }
            });
            document.getElementById('birthday').value = '';
            toggleCourseSection();
            updateBirthdayField();
        }


        document.getElementById('patient_id').addEventListener('blur', function () {
            const patientId = this.value.trim();
            const naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];
            const patientIdError = document.getElementById('patient_id-error');
            const statusMessage = document.getElementById('status-message');
            patientIdError.textContent = '';
            statusMessage.textContent = '';
            const isDarkMode = document.body.classList.contains('dark-mode');

            if (patientId && !naValues.includes(patientId)) {
                if (!/^(?:\d{11}|[A-Z]{3}\d{3}[A-Z]|[A-Z]{3}\d{4}[A-Z]|[A-Z]{3}\d{4})$/i.test(patientId)) {
                    patientIdError.textContent = 'ID must be 11 digits or formats like CLN012A, etc., or NA';
                    clearAndUnlockDependentFields(false);
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
                        if (data.error) {
                            statusMessage.textContent = 'ID not found. Please enter details manually.';
                            clearAndUnlockDependentFields(false);
                            if (patientId.startsWith('02000') || patientId.startsWith('10000')) {
                                const roleField = document.getElementById('role');
                                roleField.value = 'Student';
                                applyEditableStyles(roleField, isDarkMode);
                            }
                            toggleCourseSection();
                        } else {
                            statusMessage.textContent = 'Patient data loaded. You can edit if needed.';
                            ['name', 'gender', 'role', 'course_section', 'age'].forEach(id => {
                                const el = document.getElementById(id);
                                if (el) {
                                    el.value = data[id] || '';
                                    applyEditableStyles(el, isDarkMode); // Make editable
                                    // el.removeAttribute('readonly'); // Redundant due to applyEditableStyles
                                }
                            });
                            if (data.birthday) {
                                const date = new Date(data.birthday + "T00:00:00");
                                const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                document.getElementById('birthday_day').value = date.getDate().toString().padStart(2, '0');
                                document.getElementById('birthday_month').value = months[date.getMonth()];
                                document.getElementById('birthday_year').value = date.getFullYear();
                                document.getElementById('birthday').value = data.birthday;
                                ['birthday_day', 'birthday_month', 'birthday_year'].forEach(id => {
                                    const el = document.getElementById(id);
                                    applyEditableStyles(el, isDarkMode); // Make editable
                                    // el.removeAttribute('disabled'); // Redundant
                                });
                            }
                            applyEditableStyles(this, isDarkMode); // Make ID field itself editable

                            ['blood_pressure', 'heart_rate', 'blood_oxygen', 'height', 'weight', 'temperature', 'time_out', 'purpose_of_visit', 'health_history', 'remarks'].forEach(id => {
                                const el = document.getElementById(id);
                                if (el) {
                                    el.value = '';
                                    applyEditableStyles(el, isDarkMode);
                                }
                            });
                            toggleCourseSection();
                            updateBirthdayField();
                        }
                    })
                    .catch(error => {
                        console.error('Error checking Patient ID:', error);
                        statusMessage.textContent = 'Error checking ID. Please try again.';
                        clearAndUnlockDependentFields(false);
                    });
            } else if (naValues.includes(patientId) || !patientId) {
                clearAndUnlockDependentFields(true);
                if (!patientId) patientIdError.textContent = '';
            }
        });

        document.getElementById('name').addEventListener('blur', function () {
            const name = this.value.trim();
            const patientIdField = document.getElementById('patient_id');
            // const isNameLocked = this.hasAttribute('readonly'); // Not relevant anymore
            const statusMessage = document.getElementById('status-message');
            const isDarkMode = document.body.classList.contains('dark-mode');

            if (name && (!patientIdField.value.trim() || ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'].includes(patientIdField.value.trim()))) {
                statusMessage.textContent = '';
                fetch('check_name.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'check_name=' + encodeURIComponent(name)
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.error && data.patient_id) {
                            statusMessage.textContent = 'Patient data loaded by name. You can edit if needed.';
                            if (!patientIdField.value || ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'].includes(patientIdField.value.trim())) {
                                patientIdField.value = data.patient_id || 'NA';
                                applyEditableStyles(patientIdField, isDarkMode);
                            }
                            applyEditableStyles(this, isDarkMode); // Name field itself

                            ['gender', 'role', 'course_section', 'age'].forEach(id => {
                                const el = document.getElementById(id);
                                if (el) {
                                    el.value = data[id] || '';
                                    applyEditableStyles(el, isDarkMode);
                                }
                            });
                            if (data.birthday) {
                                const date = new Date(data.birthday + "T00:00:00");
                                const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                document.getElementById('birthday_day').value = date.getDate().toString().padStart(2, '0');
                                document.getElementById('birthday_month').value = months[date.getMonth()];
                                document.getElementById('birthday_year').value = date.getFullYear();
                                document.getElementById('birthday').value = data.birthday;
                                ['birthday_day', 'birthday_month', 'birthday_year'].forEach(id => {
                                    const el = document.getElementById(id);
                                    applyEditableStyles(el, isDarkMode);
                                });
                            }
                            ['blood_pressure', 'heart_rate', 'blood_oxygen', 'height', 'weight', 'temperature', 'time_out', 'purpose_of_visit', 'health_history', 'remarks'].forEach(id => {
                                const el = document.getElementById(id);
                                if (el) {
                                    el.value = '';
                                    applyEditableStyles(el, isDarkMode);
                                }
                            });
                            toggleCourseSection();
                            updateBirthdayField();
                        } else {
                            statusMessage.textContent = 'Name not found. Please enter details manually.';
                        }
                    })
                    .catch(error => {
                        console.error('Error checking Name:', error);
                        statusMessage.textContent = 'Error checking name. Please try again.';
                    });
            } else if (!name) {
                if (!patientIdField.value.trim() || ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'].includes(patientIdField.value.trim())) {
                    clearAndUnlockDependentFields(true);
                }
            }
        });

        function refreshFieldStyles() {
            const isDarkMode = document.body.classList.contains('dark-mode');
            document.querySelectorAll('#login-sheet-form input, #login-sheet-form select, #login-sheet-form textarea').forEach(el => {
                if (el.hasAttribute('disabled')) { // Only apply disabled styles if explicitly disabled
                    applyDisabledStyles(el, isDarkMode);
                } else {
                    applyEditableStyles(el, isDarkMode); // Default to editable styles
                }
            });
            toggleCourseSection();
            updateBirthdayField(); // Recalculate age and ensure its style is editable
        }


        window.onload = function () {
            // toggleCourseSection(); // Called by refreshFieldStyles
            // updateBirthdayField(); // Called by refreshFieldStyles
            document.getElementById('role').addEventListener('input', toggleCourseSection);
            refreshFieldStyles();
        };

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.error').forEach(error => {
                if (error.textContent.trim() !== '') {
                    error.style.display = 'block';
                }
            });

            const themeToggle = document.getElementById('checkbox');
            const currentTheme = localStorage.getItem('theme');
            if (currentTheme === 'dark') {
                document.body.classList.add('dark-mode');
                themeToggle.checked = true;
            }

            themeToggle.addEventListener('change', function () {
                if (this.checked) {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('theme', 'light');
                }
                refreshFieldStyles();
            });
            refreshFieldStyles();
        });

        document.querySelector('.logout').addEventListener('click', function (event) {
            event.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = this.href;
            }
        });

        document.getElementById('login-sheet-form').addEventListener('submit', function (event) {
            let isValid = true;
            document.querySelectorAll('.error').forEach(error => error.textContent = '');
            const naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];

            const patientIdField = document.getElementById('patient_id');
            const patientId = patientIdField.value.trim();
            // No readonly check needed here as it's always editable
            if (patientId && !/^(?:\d{11}|[A-Z]{3}\d{3}[A-Z]|[A-Z]{3}\d{4}[A-Z]|[A-Z]{3}\d{4})$/i.test(patientId) && !naValues.includes(patientId)) {
                document.getElementById('patient_id-error').textContent = 'ID format invalid or NA';
                isValid = false;
            } else if (!patientId && !document.getElementById('name').value.trim()) {
                document.getElementById('patient_id-error').textContent = 'ID or Name is required';
                isValid = false;
            }

            const nameField = document.getElementById('name');
            const name = nameField.value.trim();
            if (!name) {
                document.getElementById('name-error').textContent = 'Name is required';
                isValid = false;
            } else if (!/^[A-Za-z\s.'-]{2,50}$/.test(name)) {
                document.getElementById('name-error').textContent = 'Name: 2-50 letters, spaces, ., \', -';
                isValid = false;
            }

            const genderSelect = document.getElementById('gender');
            if (!genderSelect.value) { // No disabled check needed as it's always editable
                document.getElementById('gender-error').textContent = 'Gender is required';
                isValid = false;
            }

            const birthdayDayField = document.getElementById('birthday_day');
            // No disabled check needed
            const day = birthdayDayField.value;
            const month = document.getElementById('birthday_month').value;
            const year = document.getElementById('birthday_year').value;
            if (!day || !month || !year) {
                document.getElementById('birthday-error').textContent = 'Full Birthday is required';
                isValid = false;
            } else {
                const ageValidation = calculateAge(day, month, year);
                if (ageValidation === -2) {
                    document.getElementById('birthday-error').textContent = 'Invalid date (e.g., Feb 30)';
                    isValid = false;
                } else if (ageValidation === -3) {
                    document.getElementById('birthday-error').textContent = 'Birthday cannot be future';
                    isValid = false;
                }
            }

            const ageField = document.getElementById('age');
            const age = ageField.value;
            if (!age || age < 0 || age > 120) { // Age is always editable
                document.getElementById('age-error').textContent = 'Valid age is required (0-120)';
                isValid = false;
            }

            const roleField = document.getElementById('role');
            if (!roleField.value.trim()) { // Always editable
                document.getElementById('role-error').textContent = 'Role is required';
                isValid = false;
            }

            const courseSectionField = document.getElementById('course_section');
            // Check if disabled by logic, not if it has 'readonly'
            if (document.getElementById('role').value.trim().toLowerCase() === 'student' && !courseSectionField.disabled) {
                if (!courseSectionField.value.trim()) {
                    document.getElementById('course_section-error').textContent = 'Course & Section required for students';
                    isValid = false;
                } else if (!/^[A-Za-z0-9\s\-\/]{2,30}$/.test(courseSectionField.value.trim())) {
                    document.getElementById('course_section-error').textContent = 'Course & Section: 2-30 chars';
                    isValid = false;
                }
            }

            function validateVital(id, regex, errorMsg, min, max, naAllowed = true) {
                const field = document.getElementById(id);
                const value = field.value.trim();
                const errorEl = document.getElementById(id + '-error');
                if (value && !(naAllowed && naValues.includes(value))) {
                    if (!regex.test(value)) {
                        errorEl.textContent = errorMsg;
                        isValid = false;
                    } else if ((min !== undefined && max !== undefined)) {
                        if (id === 'blood_pressure') {
                            const parts = value.split('/');
                            if (parts.length === 2) {
                                if (parseFloat(parts[0]) < 50 || parseFloat(parts[0]) > 300 || parseFloat(parts[1]) < 30 || parseFloat(parts[1]) > 200) {
                                    errorEl.textContent = `Systolic (50-300) / Diastolic (30-200), or NA.`;
                                    isValid = false;
                                }
                            } else { // Should not happen if regex passes, but as a fallback
                                errorEl.textContent = errorMsg; // Invalid format for BP
                                isValid = false;
                            }
                        } else if (parseFloat(value) < min || parseFloat(value) > max) {
                            errorEl.textContent = `Value must be between ${min} and ${max}, or NA.`;
                            isValid = false;
                        }
                    }
                }
            }

            validateVital('blood_pressure', /^\d{2,3}\/\d{2,3}$/, 'Format: e.g., 120/80 or NA');
            validateVital('heart_rate', /^\d+$/, 'Must be a number or NA', 1, 220);
            validateVital('blood_oxygen', /^\d+$/, 'Must be a number or NA', 1, 100);
            validateVital('height', /^\d+(\.\d+)?$/, 'Must be a number or NA', 1, 250);
            validateVital('weight', /^\d+(\.\d+)?$/, 'Must be a number or NA', 1, 500);
            validateVital('temperature', /^\d+(\.\d+)?$/, 'Must be a number or NA', 1, 100);


            const timeOut = document.getElementById('time_out').value.trim();
            if (timeOut && !/^\d{2}:\d{2}$/.test(timeOut)) {
                document.getElementById('time_out-error').textContent = 'Time Out format HH:MM';
                isValid = false;
            }

            function validateTextarea(id, minLen, maxLen) {
                const field = document.getElementById(id);
                const value = field.value.trim();
                const errorEl = document.getElementById(id + '-error');
                if (!value) {
                    errorEl.textContent = `${field.previousElementSibling.textContent.split('/')[0].trim()} is required.`;
                    isValid = false;
                } else if (value.length < minLen || value.length > maxLen) {
                    errorEl.textContent = `Must be ${minLen}-${maxLen} characters.`;
                    isValid = false;
                }
            }
            validateTextarea('purpose_of_visit', 2, 200);
            validateTextarea('health_history', 2, 500);
            validateTextarea('remarks', 2, 500);


            if (!isValid) {
                event.preventDefault();
            }
        });
    </script>
</body>

</html>