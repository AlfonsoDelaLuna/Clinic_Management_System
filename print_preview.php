<?php
session_start();

// Validate the request method and data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['data'])) {
    die("Error: No data received!");
}

$data = json_decode($_POST['data'], true);
if (!$data) {
    die("Error: Invalid data received!");
}

// Validate studentNumber (patient_id)
$patientId = isset($data['patientId']) ? trim($data['patientId']) : '';
if (empty($patientId)) {
    die("Error: Invalid Patient ID!");
}

// Database connection
try {
    $db = new PDO("mysql:host=localhost;dbname=clinic_db", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error: Database connection failed - " . $e->getMessage());
}

// Fetch student details from clinic_logs
try {
    $stmt = $db->prepare("SELECT name, age, gender, role, blood_pressure, heart_rate, blood_oxygen, temperature, time_in, time_out, height, weight FROM clinic_logs WHERE patient_id = ? LIMIT 1");
    $stmt->execute([$patientId]);
    $clinicLog = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$clinicLog) {
        die("Error: No record found in clinic_logs for patient_id: " . htmlspecialchars($patientId));
    }
    error_log("Raw clinicLog data: " . json_encode($clinicLog));
} catch (PDOException $e) {
    die("Error: Failed to fetch clinic_logs record - " . $e->getMessage());
}

// Fetch the most recent history record from admin_history for visit details
try {
    $stmt = $db->prepare("SELECT purpose_of_visit, health_history, medicine, quantity, remarks, created_at FROM admin_history WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$patientId]);
    $historyRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$historyRecord) {
        $historyRecord = [
            'purpose_of_visit' => 'N/A',
            'health_history' => 'N/A',
            'medicine' => 'N/A',
            'quantity' => 'N/A',
            'remarks' => 'N/A',
            'created_at' => null
        ];
    }
} catch (PDOException $e) {
    die("Error: Failed to fetch admin_history record - " . $e->getMessage());
}

// Check if there’s updated data in the session from edit_history.php
$updatedData = isset($_SESSION['updated_data']) && $_SESSION['updated_data']['patient_id'] === $patientId ? $_SESSION['updated_data'] : null;
if ($updatedData) {
    // Override the most recent history record with updated data
    $historyRecord = [
        'purpose_of_visit' => $updatedData['purpose_of_visit'] ?? $historyRecord['purpose_of_visit'],
        'health_history' => $updatedData['health_history'] ?? $historyRecord['health_history'],
        'medicine' => $updatedData['medicine'] ?? $historyRecord['medicine'],
        'quantity' => $updatedData['quantity'] ?? $historyRecord['quantity'],
        'remarks' => $updatedData['remarks'] ?? $historyRecord['remarks']
    ];
    // Update clinicLog time_out if edited
    $clinicLog['time_out'] = $updatedData['time_out'] ?? $clinicLog['time_out'];
}

// Fetch all history records for the Visit History section
try {
    $stmt = $db->prepare("SELECT date, time_in, time_out, medicine, quantity, health_history, purpose_of_visit, remarks, blood_pressure, heart_rate, blood_oxygen, temperature, height, weight FROM admin_history WHERE patient_id = ? ORDER BY created_at DESC");
    $stmt->execute([$patientId]);
    $history_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: Failed to fetch history records - " . $e->getMessage());
}

// Update the most recent history row with session data if available
if ($updatedData && !empty($history_rows)) {
    $history_rows[0] = [
        'date' => $updatedData['date'] ?? $history_rows[0]['date'],
        'time_in' => $updatedData['time_in'] ?? $history_rows[0]['time_in'],
        'time_out' => $updatedData['time_out'] ?? $history_rows[0]['time_out'],
        'medicine' => $updatedData['medicine'] ?? $history_rows[0]['medicine'],
        'quantity' => $updatedData['quantity'] ?? $history_rows[0]['quantity'],
        'health_history' => $updatedData['health_history'] ?? $history_rows[0]['health_history'],
        'purpose_of_visit' => $updatedData['purpose_of_visit'] ?? $history_rows[0]['purpose_of_visit'],
        'remarks' => $updatedData['remarks'] ?? $history_rows[0]['remarks'],
        'blood_pressure' => $updatedData['blood_pressure'] ?? $history_rows[0]['blood_pressure'],
        'heart_rate' => $updatedData['heart_rate'] ?? $history_rows[0]['heart_rate'],
        'blood_oxygen' => $updatedData['blood_oxygen'] ?? $history_rows[0]['blood_oxygen'],
        'temperature' => $updatedData['temperature'] ?? $history_rows[0]['temperature'],
        'height' => $updatedData['height'] ?? $history_rows[0]['height'],
        'weight' => $updatedData['weight'] ?? $history_rows[0]['weight']
    ];
}

// Calculate BMI
$height = isset($clinicLog['height']) ? (float) $clinicLog['height'] : 0;
$weight = isset($clinicLog['weight']) ? (float) $clinicLog['weight'] : 0;
$bmi = 'N/A';

if ($height > 0 && $weight > 0) {
    $heightInMeters = $height / 100;
    $bmiValue = $weight / ($heightInMeters * $heightInMeters);
    $bmi = number_format($bmiValue, 1) . ' (' . getBmiClassification($bmiValue) . ')';
}

// Function to convert 24-hour time to 12-hour format
function convertTo12HourFormat($time)
{
    if (!$time || $time === 'N/A' || !preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time)) {
        return 'N/A';
    }
    $timeParts = explode(':', $time);
    $hours = (int) $timeParts[0];
    $minutes = $timeParts[1];
    $period = $hours >= 12 ? 'PM' : 'AM';
    $hours = $hours % 12 ?: 12;
    return "$hours:$minutes $period";
}

// Function to convert date format from YYYY-MM-DD to MM/DD/YYYY
function convertToMMDDYYYY($date)
{
    if (!$date || $date === 'N/A') {
        return 'N/A';
    }
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format('m/d/Y');
    } catch (Exception $e) {
        error_log("Date conversion error: " . $e->getMessage());
        return 'N/A';
    }
}

function getBmiClassification($bmi)
{
    if ($bmi < 18.5)
        return 'Underweight';
    if ($bmi < 25)
        return 'Normal weight';
    if ($bmi < 30)
        return 'Overweight';
    return 'Obese';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Preview</title>
    <link rel="icon" href="images/sti_logo.png" type="image/png">
    <style>
        @page {
            size: Letter portrait;
        }

        body {
            font-family: 'Source Sans Pro', sans-serif;
            font-size: 12px;
        }

        .clinic-header {
            text-align: center;
        }

        .clinic-header h1 {
            font-size: 20px;
            color: #012465;
        }

        .clinic-header p {
            font-size: 10px;
            margin: 5px 0;
        }

        .patient-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10mm;
        }

        .info-group {
            margin-bottom: 5px;
            padding: 2px 0;
            border-bottom: 1px solid #eee;
        }

        .label {
            font-weight: 600;
            display: inline-block;
            width: 140px;
            font-size: 12px;
        }

        .history-table {
            width: 100%;
            min-height: 50px;
            margin-top: 10px;
        }

        .history-table th,
        .history-table td {
            padding: 6px;
            border: 1px solid #ddd;
            height: 20px;
            font-size: 10px;
            text-align: center;
        }

        .history-table tbody tr {
            page-break-inside: avoid;
        }

        .history-section {
            margin-top: 15px;
        }

        .history-table th:nth-child(1) {
            width: 15%;
        }

        /* Date */
        .history-table th:nth-child(2) {
            width: 15%;
        }

        /* Time In */
        .history-table th:nth-child(3) {
            width: 15%;
        }

        /* Time Out */
        .history-table th:nth-child(4) {
            width: 15%;
        }

        /* Medicine */
        .history-table th:nth-child(5) {
            width: 5%;
        }

        /* Quantity */
        .history-table th:nth-child(6) {
            width: 15%;
        }

        /* Health History */
        .history-table th:nth-child(8) {
            width: 15%;
        }

        /* Purpose */
        .history-table th:nth-child(9) {
            width: 15%;
        }

        /* Remarks */

        .details h3 {
            margin-bottom: 10px;
            font-size: 14px;
        }

        @media print {
            .clinic-header h1 {
                font-size: 24px;
            }

            .clinic-header p {
                font-size: 12px;
            }

            .label {
                font-size: 14px;
            }

            .history-table th,
            .history-table td {
                font-size: 12px;
                text-align: center;
            }

            .details h3 {
                font-size: 16px;
            }

            /* Hide browser-added footer */
            @page {
                margin: 10mm 10mm 0 10mm;
                /* Set bottom margin to 0 to avoid footer space */
            }

            body {
                margin-bottom: 0;
            }

            footer,
            #footer,
            .footer {
                display: none !important;
            }

            /* Ensure no browser default headers/footers */
            @page {
                margin-bottom: 0;
            }
        }
    </style>
</head>

<body>
    <div class="clinic-header">
        <h1>CLINIC MANAGEMENT SYSTEM</h1>
        <p>STI Academic Center, Samson Road corner Caimito Road, Caloocan City</p>
        <p>Tel: Local (116) 8294-4001/8294-4002</p>
    </div>

    <?php if ($clinicLog['time_in']): ?>
        <p style="text-align: center;">
            As of:
            <?php echo date("F j, Y", strtotime($clinicLog['time_in'])); ?>
            at
            <?php echo date("g:i A", strtotime($clinicLog['time_in'])); ?>
        </p>
    <?php endif; ?>

    <div class="patient-info">
        <div>
            <div class="info-group">
                <span class="label"><strong>Patient ID:</strong></span>
                <?php echo htmlspecialchars($patientId); ?>
            </div>
            <div class="info-group">
                <span class="label">Name:</span>
                <?php echo htmlspecialchars($clinicLog['name'] ?? 'N/A'); ?>
            </div>
            <div class="info-group">
                <span class="label">Age:</span>
                <?php echo htmlspecialchars($clinicLog['age'] ?? 'N/A'); ?>
            </div>
            <div class="info-group">
                <span class="label">Gender:</span>
                <?php echo htmlspecialchars($clinicLog['gender'] ?? 'N/A'); ?>
            </div>
            <div class="info-group">
                <span class="label">Role:</span>
                <?php echo htmlspecialchars($clinicLog['role'] ?? 'N/A'); ?>
            </div>
            <div class="info-group">
                <span class="label">Blood Pressure:</span>
                <?php echo htmlspecialchars($clinicLog['blood_pressure'] ?? 'N/A'); ?>
            </div>
            <div class="info-group">
                <span class="label">Heart Rate:</span>
                <?php echo htmlspecialchars($clinicLog['heart_rate'] ?? 'N/A'); ?>
            </div>
            <div class="info-group">
                <span class="label">Blood Oxygen (%):</span>
                <?php echo htmlspecialchars($clinicLog['blood_oxygen'] ?? 'N/A'); ?>
            </div>
        </div>
        <div>
            <div class="info-group">
                <span class="label">Temperature (°C):</span>
                <?php echo htmlspecialchars($clinicLog['temperature'] ?? 'N/A'); ?>
            </div>
            <div class="info-group">
                <span class="label">BMI:</span>
                <?php echo htmlspecialchars($bmi); ?>
            </div>
            <div class="info-group">
                <span class="label">Time In:</span>
                <?php echo htmlspecialchars(convertTo12HourFormat($clinicLog['time_in'] ?? 'N/A')); ?>
            </div>
            <div class="info-group">
                <span class="label">Time Out:</span>
                <?php echo htmlspecialchars(convertTo12HourFormat($clinicLog['time_out'] ?? 'N/A')); ?>
            </div>
            <div class="info-group">
                <span class="label">Purpose of Visit:</span>
                <?php echo htmlspecialchars($historyRecord['purpose_of_visit'] ?? 'N/A'); ?>
            </div>
            <div class="info-group">
                <span class="label">Health History:</span>
                <?php echo htmlspecialchars($historyRecord['health_history'] ?? 'N/A'); ?>
            </div>
            <div class="info-group">
                <span class="label">Medicine:</span>
                <?php echo htmlspecialchars($historyRecord['medicine'] ?? 'N/A'); ?>
            </div>
            <div class="info-group">
                <span class="label">Quantity:</span>
                <?php echo htmlspecialchars($historyRecord['quantity'] ?? 'N/A'); ?>
            </div>
            <div class="info-group">
                <span class="label">Remarks:</span>
                <?php echo htmlspecialchars($historyRecord['remarks'] ?? 'N/A'); ?>
            </div>
        </div>
    </div>

    <div class="history-section">
        <h3>Visit History</h3>
        <?php if (!empty($history_rows)): ?>
            <table class="history-table">
                <thead>
                    <tr>
                        <th style="width: 15%">Date</th>
                        <th style="width: 15%">Log In</th>
                        <th style="width: 15%">Log Out</th>
                        <th style="width: 15%">Medicine</th>
                        <th style="width: 5%">Quantity</th>
                        <th style="width: 20%">Health History</th>
                        <th style="width: 15%">Purpose</th>
                        <th style="width: 15%">Remarks</th>
                        <th>Blood Pressure</th>
                        <th>Heart Rate</th>
                        <th>Blood Oxygen</th>
                        <th>Temperature</th>
                        <th>BMI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history_rows as $row):
                        // Calculate BMI for each history row
                        $row_height = isset($row['height']) ? (float) $row['height'] : 0;
                        $row_weight = isset($row['weight']) ? (float) $row['weight'] : 0;
                        $row_bmi = 'N/A';

                        if ($row_height > 0 && $row_weight > 0) {
                            $row_heightInMeters = $row_height / 100;
                            $row_bmiValue = $row_weight / ($row_heightInMeters * $row_heightInMeters);
                            $row_bmi = number_format($row_bmiValue, 1) . ' (' . getBmiClassification($row_bmiValue) . ')';
                        }
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars(convertToMMDDYYYY($row['date'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars(convertTo12HourFormat($row['time_in'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars(convertTo12HourFormat($row['time_out'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars($row['medicine'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['health_history'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['purpose_of_visit'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['remarks'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['blood_pressure'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['heart_rate'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['blood_oxygen'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['temperature'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row_bmi); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No historical records found</p>
        <?php endif; ?>
    </div>

    <script>
        window.onload = function() {
            window.print();
            setTimeout(() => window.close(), 100);
        };
    </script>
</body>

</html>

</html>