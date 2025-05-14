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
$existing_record = null;
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
    $date = new DateTime($birthday);
    $selected_day = $date->format('d');
    $selected_month = $date->format('F'); // Full month name (e.g., January)
    $selected_year = $date->format('Y');
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
            font-size: 2em;
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

        body.dark-mode #login-sheet-form {
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
            background-color: #1e1e1e;
            color: #e0e0e0;
            border: 1px solid #555;
        }

        body.dark-mode .form-group input[readonly],
        body.dark-mode .form-group select[disabled],
        body.dark-mode .form-group input[disabled] {
            background-color: #1e1e1e;
            color: #e0e0e0;
            border: 1px solid #555;
            cursor: not-allowed;
            opacity: 0.9;
        }

        body.dark-mode .form-group input::placeholder,
        body.dark-mode .form-group textarea::placeholder {
            color: #ffffff;
        }

        body.dark-mode .form-group .birthday-group select {
            background-color: #1e1e1e;
            color: #e0e0e0;
            border: 1px solid #555;
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
                <em>Toggle Mode</em>
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
                        value="<?= htmlspecialchars($form_data['name'] ?? '') ?>" <?= $existing_record ? 'readonly' : '' ?>>
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
                        value="<?= htmlspecialchars($form_data['age'] ?? '') ?>" <?= $existing_record ? 'readonly' : '' ?>>
                    <div id="age-error" class="error">
                        <?php if (isset($errors['age'])): ?>
                            <?= implode('<br>', $errors['age']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="role">ROLE / *REQUIRED</label>
                    <input type="text" id="role" name="role" placeholder="Role (e.g., Student, Faculty, Staff)" required
                        value="<?= htmlspecialchars($form_data['role'] ?? '') ?>" <?= $existing_record ? 'readonly' : '' ?>>
                    <div id="role-error" class="error">
                        <?php if (isset($errors['role'])): ?>
                            <?= implode('<br>', $errors['role']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="course_section">COURSE AND SECTION / *REQUIRED if student</label>
                    <input type="text" id="course_section" name="course_section" placeholder="Course and Section"
                        value="<?= htmlspecialchars($form_data['course_section'] ?? '') ?>" <?= $existing_record ? 'readonly' : '' ?>>
                    <div id="course_section-error" class="error">
                        <?php if (isset($errors['course_section'])): ?>
                            <?= implode('<br>', $errors['course_section']) ?>
                        <?php endif; ?>
                    </div>
                </div>

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
            const birthDate = new Date(`${year}-${monthMap[month]}-${day}`);
            const today = new Date();
            if (isNaN(birthDate.getTime()) || birthDate.getDate() != parseInt(day)) {
                return -2; // Invalid date
            }
            if (birthDate > today) {
                return -3; // Future date
            }
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            return age;
        }

        // Function to update the hidden birthday field
        function updateBirthdayField() {
            const day = document.getElementById('birthday_day').value;
            const month = document.getElementById('birthday_month').value;
            const year = document.getElementById('birthday_year').value;
            const birthdayField = document.getElementById('birthday');
            const birthdayError = document.getElementById('birthday-error');
            const ageField = document.getElementById('age');
            const isDarkMode = document.body.classList.contains('dark-mode');
            const enabledBg = isDarkMode ? '#1e1e1e' : '#fff';
            const enabledText = isDarkMode ? '#e0e0e0' : '#333';
            const readonlyBg = isDarkMode ? '#1e1e1e' : '#f0f0f0';
            const readonlyText = isDarkMode ? '#e0e0e0' : '#888';

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

                // Calculate age and update the age field
                const age = calculateAge(day, month, year);
                if (age === -2) {
                    birthdayError.textContent = 'Invalid date (e.g., February 30)';
                    ageField.value = '';
                    ageField.removeAttribute('readonly');
                    ageField.style.backgroundColor = enabledBg;
                    ageField.style.color = enabledText;
                    birthdayField.value = '';
                } else if (age === -3) {
                    birthdayError.textContent = 'Birthday cannot be in the future';
                    ageField.value = '';
                    ageField.removeAttribute('readonly');
                    ageField.style.backgroundColor = enabledBg;
                    ageField.style.color = enabledText;
                    birthdayField.value = '';
                } else if (age >= 0) {
                    ageField.value = age;
                    ageField.setAttribute('readonly', true);
                    ageField.style.backgroundColor = readonlyBg;
                    ageField.style.color = readonlyText;
                    birthdayError.textContent = '';
                }
            } else {
                birthdayField.value = '';
                ageField.value = '';
                ageField.removeAttribute('readonly');
                ageField.style.backgroundColor = enabledBg;
                ageField.style.color = enabledText;
            }
        }

        // Add event listeners to all birthday dropdowns
        document.getElementById('birthday_day').addEventListener('change', updateBirthdayField);
        document.getElementById('birthday_month').addEventListener('change', updateBirthdayField);
        document.getElementById('birthday_year').addEventListener('change', updateBirthdayField);

        // Toggle course_section based on role
        function toggleCourseSection() {
            const role = document.getElementById('role').value.trim().toLowerCase();
            const courseSection = document.getElementById('course_section');
            const isDarkMode = document.body.classList.contains('dark-mode');
            const enabledBg = isDarkMode ? '#1e1e1e' : '#fff';
            const enabledText = isDarkMode ? '#e0e0e0' : '#333';
            const disabledBg = isDarkMode ? '#1e1e1e' : '#f0f0f0';
            const disabledText = isDarkMode ? '#e0e0e0' : '#888';

            if (role !== 'student') {
                courseSection.disabled = true;
                courseSection.style.backgroundColor = disabledBg;
                courseSection.style.color = disabledText;
                courseSection.value = role || '';
            } else {
                courseSection.disabled = false;
                if (!courseSection.hasAttribute('readonly')) {
                    courseSection.style.backgroundColor = enabledBg;
                    courseSection.style.color = enabledText;
                    courseSection.value = '';
                }
            }
        }

        // Function to clear and unlock dependent fields
        function clearAndUnlockDependentFields() {
            const isDarkMode = document.body.classList.contains('dark-mode');
            const enabledBg = isDarkMode ? '#1e1e1e' : '#fff';
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

        // Check patient ID and prefill form via AJAX
        document.getElementById('patient_id').addEventListener('blur', function() {
            const patientId = this.value.trim();
            const naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];
            const patientIdError = document.getElementById('patient_id-error');
            const statusMessage = document.getElementById('status-message');
            patientIdError.textContent = '';
            statusMessage.textContent = '';
            if (patientId && !naValues.includes(patientId)) {
                if (!/^(?:\d{11}|[A-Z]{3}\d{3}[A-Z]|[A-Z]{3}\d{4}[A-Z]|[A-Z]{3}\d{4})$/i.test(patientId)) {
                    patientIdError.textContent = 'ID must be 11 digits (e.g., 02000123456), or formats like CLN012A, CLN0123A, CLN0123, or NA';
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
                        const readonlyBg = isDarkMode ? '#1e1e1e' : '#f0f0f0';
                        const readonlyText = isDarkMode ? '#e0e0e0' : '#888';
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
                            document.getElementById('name').setAttribute('readonly', true);
                            document.getElementById('name').style.backgroundColor = readonlyBg;
                            document.getElementById('name').style.color = readonlyText;
                            document.getElementById('gender').value = data.gender || '';
                            document.getElementById('gender').setAttribute('disabled', true);
                            document.getElementById('gender').style.backgroundColor = readonlyBg;
                            document.getElementById('gender').style.color = readonlyText;
                            if (data.birthday) {
                                const date = new Date(data.birthday);
                                document.getElementById('birthday_day').value = date.getDate().toString().padStart(2, '0');
                                const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                document.getElementById('birthday_month').value = months[date.getMonth()];
                                document.getElementById('birthday_year').value = date.getFullYear();
                                document.getElementById('birthday').value = data.birthday;
                                document.getElementById('birthday_day').setAttribute('disabled', true);
                                document.getElementById('birthday_month').setAttribute('disabled', true);
                                document.getElementById('birthday_year').setAttribute('disabled', true);
                                document.getElementById('birthday_day').style.backgroundColor = readonlyBg;
                                document.getElementById('birthday_month').style.backgroundColor = readonlyBg;
                                document.getElementById('birthday_year').style.backgroundColor = readonlyBg;
                                document.getElementById('birthday_day').style.color = readonlyText;
                                document.getElementById('birthday_month').style.color = readonlyText;
                                document.getElementById('birthday_year').style.color = readonlyText;
                            }
                            document.getElementById('age').value = data.age || '';
                            document.getElementById('age').setAttribute('readonly', true);
                            document.getElementById('age').style.backgroundColor = readonlyBg;
                            document.getElementById('age').style.color = readonlyText;
                            document.getElementById('role').value = data.role || '';
                            document.getElementById('role').setAttribute('readonly', true);
                            document.getElementById('role').style.backgroundColor = readonlyBg;
                            document.getElementById('role').style.color = readonlyText;
                            document.getElementById('course_section').value = data.course_section || '';
                            document.getElementById('course_section').setAttribute('readonly', true);
                            document.getElementById('course_section').style.backgroundColor = readonlyBg;
                            document.getElementById('course_section').style.color = readonlyText;
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

        // Check name and prefill form via AJAX
        document.getElementById('name').addEventListener('blur', function() {
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
                        const readonlyBg = isDarkMode ? '#1e1e1e' : '#f0f0f0';
                        const readonlyText = isDarkMode ? '#e0e0e0' : '#888';
                        if (!data.error && data.patient_id) {
                            statusMessage.textContent = '';
                            if (!patientIdField.value || ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'].includes(patientIdField.value.trim())) {
                                patientIdField.value = data.patient_id || 'NA';
                                patientIdField.setAttribute('readonly', true);
                                patientIdField.style.backgroundColor = readonlyBg;
                                patientIdField.style.color = readonlyText;
                            }
                            document.getElementById('gender').value = data.gender || '';
                            document.getElementById('gender').setAttribute('disabled', true);
                            document.getElementById('gender').style.backgroundColor = readonlyBg;
                            document.getElementById('gender').style.color = readonlyText;
                            if (data.birthday) {
                                const date = new Date(data.birthday);
                                document.getElementById('birthday_day').value = date.getDate().toString().padStart(2, '0');
                                const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                document.getElementById('birthday_month').value = months[date.getMonth()];
                                document.getElementById('birthday_year').value = date.getFullYear();
                                document.getElementById('birthday').value = data.birthday;
                                document.getElementById('birthday_day').setAttribute('disabled', true);
                                document.getElementById('birthday_month').setAttribute('disabled', true);
                                document.getElementById('birthday_year').setAttribute('disabled', true);
                                document.getElementById('birthday_day').style.backgroundColor = readonlyBg;
                                document.getElementById('birthday_month').style.backgroundColor = readonlyBg;
                                document.getElementById('birthday_year').style.backgroundColor = readonlyBg;
                                document.getElementById('birthday_day').style.color = readonlyText;
                                document.getElementById('birthday_month').style.color = readonlyText;
                                document.getElementById('birthday_year').style.color = readonlyText;
                            }
                            document.getElementById('age').value = data.age || '';
                            document.getElementById('age').setAttribute('readonly', true);
                            document.getElementById('age').style.backgroundColor = readonlyBg;
                            document.getElementById('age').style.color = readonlyText;
                            document.getElementById('role').value = data.role || '';
                            document.getElementById('role').setAttribute('readonly', true);
                            document.getElementById('role').style.backgroundColor = readonlyBg;
                            document.getElementById('role').style.color = readonlyText;
                            document.getElementById('course_section').value = data.course_section || '';
                            document.getElementById('course_section').setAttribute('readonly', true);
                            document.getElementById('course_section').style.backgroundColor = readonlyBg;
                            document.getElementById('course_section').style.color = readonlyText;
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

        // Run on page load and on role change
        window.onload = function() {
            toggleCourseSection();
            document.getElementById('role').addEventListener('input', toggleCourseSection);
        };

        // Display server-side errors on page load
        document.addEventListener('DOMContentLoaded', function() {
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

            themeToggle.addEventListener('change', function() {
                if (this.checked) {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('theme', 'light');
                }
                toggleCourseSection();
                updateBirthdayField();
            });
        });

        // Logout Confirmation
        document.querySelector('.logout').addEventListener('click', function(event) {
            event.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = this.href;
            }
        });

        // Client-side form validation
        document.getElementById('login-sheet-form').addEventListener('submit', function(event) {
            let isValid = true;
            document.querySelectorAll('.error').forEach(error => error.textContent = '');
            const naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];

            // Patient ID (optional, but validate if provided)
            const patientId = document.getElementById('patient_id').value.trim();
            const patientIdField = document.getElementById('patient_id');
            if (!patientIdField.hasAttribute('readonly') && patientId && !/^(?:\d{11}|[A-Z]{3}\d{3}[A-Z]|[A-Z]{3}\d{4}[A-Z]|[A-Z]{3}\d{4})$/i.test(patientId) && !naValues.includes(patientId)) {
                document.getElementById('patient_id-error').textContent = 'ID must be 11 digits (e.g., 02000123456), or formats like CLN012A, CLN0123A, CLN0123, or NA';
                isValid = false;
            } else if (!patientIdField.hasAttribute('readonly') && !patientId) {
                const nameField = document.getElementById('name');
                if (!nameField.hasAttribute('readonly') && !nameField.value.trim()) {
                    document.getElementById('patient_id-error').textContent = 'ID or Name is required';
                    isValid = false;
                }
            }

            // Name (required)
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

            // Gender (required, but skip if disabled)
            const gender = document.getElementById('gender');
            const isGenderLocked = gender.hasAttribute('disabled');
            if (!isGenderLocked && !gender.value) {
                document.getElementById('gender-error').textContent = 'Gender is required';
                isValid = false;
            }

            // Birthday (required)
            const birthdayDay = document.getElementById('birthday_day').value;
            const birthdayMonth = document.getElementById('birthday_month').value;
            const birthdayYear = document.getElementById('birthday_year').value;
            const birthdayDayField = document.getElementById('birthday_day');
            const isBirthdayLocked = birthdayDayField.hasAttribute('disabled');
            if (!isBirthdayLocked) {
                if (!birthdayDay || !birthdayMonth || !birthdayYear) {
                    document.getElementById('birthday-error').textContent = 'Birthday is required';
                    isValid = false;
                } else {
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
                    if (isNaN(birthDate.getTime()) || birthDate.getDate() != parseInt(birthdayDay)) {
                        document.getElementById('birthday-error').textContent = 'Invalid date (e.g., February 30)';
                        isValid = false;
                    } else if (birthDate > new Date()) {
                        document.getElementById('birthday-error').textContent = 'Birthday cannot be in the future';
                        isValid = false;
                    }
                }
            }

            // Age (required)
            const age = document.getElementById('age').value;
            if (!age || age < 1 || age > 100) {
                document.getElementById('age-error').textContent = 'Age must be between 1 and 100';
                isValid = false;
            }

            // Role (required)
            const role = document.getElementById('role');
            const isRoleLocked = role.hasAttribute('readonly');
            if (!isRoleLocked && !role.value.trim()) {
                document.getElementById('role-error').textContent = 'Role is required';
                isValid = false;
            }

            // Course and Section (required if Role is Student)
            const roleValue = role.value.trim().toLowerCase();
            if (roleValue === 'student') {
                const courseSection = document.getElementById('course_section');
                const isCourseSectionLocked = courseSection.hasAttribute('readonly');
                if (!isCourseSectionLocked && (!courseSection.value.trim() || !/^[A-Za-z0-9\s\-\/]{2,20}$/.test(courseSection.value.trim()))) {
                    document.getElementById('course_section-error').textContent = 'Course and Section must be 2-20 characters';
                    isValid = false;
                }
            }

            // Blood Pressure (optional, but validate if provided)
            const bloodPressure = document.getElementById('blood_pressure').value.trim();
            if (bloodPressure && !/^\d{2,3}\/\d{2,3}$/.test(bloodPressure) && !naValues.includes(bloodPressure)) {
                document.getElementById('blood_pressure-error').textContent = 'Format: e.g., 120/80 or NA';
                isValid = false;
            }

            // Heart Rate (optional, but validate if provided)
            const heartRate = document.getElementById('heart_rate').value.trim();
            if (heartRate && !/^\d+$/.test(heartRate) && !naValues.includes(heartRate)) {
                document.getElementById('heart_rate-error').textContent = 'Heart Rate must be a number or NA';
                isValid = false;
            }
            if (heartRate && !naValues.includes(heartRate) && (heartRate < 1 || heartRate > 200)) {
                document.getElementById('heart_rate-error').textContent = 'Heart Rate must be between 1 and 200';
                isValid = false;
            }

            // Blood Oxygen (optional, but validate if provided)
            const bloodOxygen = document.getElementById('blood_oxygen').value.trim();
            if (bloodOxygen && !/^\d+$/.test(bloodOxygen) && !naValues.includes(bloodOxygen)) {
                document.getElementById('blood_oxygen-error').textContent = 'Blood Oxygen must be a number or NA';
                isValid = false;
            }
            if (bloodOxygen && !naValues.includes(bloodOxygen) && (bloodOxygen < 1 || bloodOxygen > 100)) {
                document.getElementById('blood_oxygen-error').textContent = 'Blood Oxygen must be between 1 and 100';
                isValid = false;
            }

            // Height (optional, but validate if provided)
            const height = document.getElementById('height').value.trim();
            if (height && !/^\d+(\.\d+)?$/.test(height) && !naValues.includes(height)) {
                document.getElementById('height-error').textContent = 'Height must be a number or NA';
                isValid = false;
            }
            if (height && !naValues.includes(height) && (height < 1 || height > 251)) {
                document.getElementById('height-error').textContent = 'Height must be between 1 and 251 cm';
                isValid = false;
            }

            // Weight (optional, but validate if provided)
            const weight = document.getElementById('weight').value.trim();
            if (weight && !/^\d+(\.\d+)?$/.test(weight) && !naValues.includes(weight)) {
                document.getElementById('weight-error').textContent = 'Weight must be a number or NA';
                isValid = false;
            }
            if (weight && !naValues.includes(weight) && (weight < 1 || weight > 500)) {
                document.getElementById('weight-error').textContent = 'Weight must be between 1 and 500 kg';
                isValid = false;
            }

            // Temperature (optional, but validate if provided)
            const temperature = document.getElementById('temperature').value.trim();
            if (temperature && !/^\d+(\.\d+)?$/.test(temperature) && !naValues.includes(temperature)) {
                document.getElementById('temperature-error').textContent = 'Temperature must be a number or NA';
                isValid = false;
            }
            if (temperature && !naValues.includes(temperature) && (temperature < 1 || temperature > 100)) {
                document.getElementById('temperature-error').textContent = 'Temperature must be between 1 and 100°C';
                isValid = false;
            }

            // Time Out (optional, but validate if provided)
            const timeOut = document.getElementById('time_out').value.trim();
            if (timeOut && !/^\d{2}:\d{2}$/.test(timeOut)) {
                document.getElementById('time_out-error').textContent = 'Time Out must be in HH:MM format (e.g., 14:30)';
                isValid = false;
            }

            // Purpose of Visit (required)
            const purposeOfVisit = document.getElementById('purpose_of_visit').value.trim();
            if (!purposeOfVisit) {
                document.getElementById('purpose_of_visit-error').textContent = 'Purpose of Visit is required';
                isValid = false;
            } else if (!/^[A-Za-z0-9\s,\-]{2,100}$/.test(purposeOfVisit)) {
                document.getElementById('purpose_of_visit-error').textContent = 'Purpose of Visit must be 2-100 characters';
                isValid = false;
            }

            // Health History (required)
            const healthHistory = document.getElementById('health_history').value.trim();
            if (!healthHistory) {
                document.getElementById('health_history-error').textContent = 'Health History is required';
                isValid = false;
            } else if (!/^[A-Za-z0-9\s,\-]{2,100}$/.test(healthHistory)) {
                document.getElementById('health_history-error').textContent = 'Health History must be 2-100 characters';
                isValid = false;
            }

            // Remarks (required)
            const remarks = document.getElementById('remarks').value.trim();
            if (!remarks) {
                document.getElementById('remarks-error').textContent = 'Remarks is required';
                isValid = false;
            } else if (!/^[A-Za-z0-9\s,\-\.]{2,200}$/.test(remarks)) {
                document.getElementById('remarks-error').textContent = 'Remarks must be 2-200 characters';
                isValid = false;
            }

            // Prevent form submission if validation fails
            if (!isValid) {
                event.preventDefault();
            }
        });
    </script>
</body>

</html>