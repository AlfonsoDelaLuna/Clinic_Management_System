<?php
session_start();

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

try {
    $db = new PDO("mysql:host=localhost;dbname=clinic_db", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle Excel Upload
if (isset($_POST["upload_excel"])) {
    if (isset($_FILES["import_excel"]) && $_FILES["import_excel"]["error"] == 0) {
        $file_name = $_FILES["import_excel"]["name"];
        $file_tmp = $_FILES["import_excel"]["tmp_name"];
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);

        $allowed_ext = array("xlsx", "xls");

        if (in_array($file_ext, $allowed_ext)) {
            $inputFileName = tempnam(sys_get_temp_dir(), "import_");
            if (move_uploaded_file($file_tmp, $inputFileName, )) {
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
                    $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

                    $header = array_shift($sheetData);

                    $insertCount = 0;
                    foreach ($sheetData as $row) {
                        $patient_id = $row["A"] ?? null;
                        $name = $row["B"] ?? null;
                        $date = $row["C"] ?? null;
                        $time_in = $row["D"] ?? null;
                        $time_out = $row["E"] ?? null;
                        $medicine = $row["F"] ?? null;
                        $quantity = $row["G"] ?? null;
                        $health_history = $row["H"] ?? null;
                        $purpose_of_visit = $row["I"] ?? null;
                        $remarks = $row["J"] ?? null;
                        $gender = $row["K"] ?? null;
                        $age = $row["L"] ?? null;
                        $role = $row["M"] ?? null;
                        $course_section = $row["N"] ?? null;
                        $blood_pressure = $row["O"] ?? null;
                        $heart_rate = $row["P"] ?? null;
                        $blood_oxygen = $row["Q"] ?? null;
                        $height = $row["R"] ?? null;
                        $weight = $row["S"] ?? null;
                        $temperature = $row["T"] ?? null;

                        if (empty($patient_id) || empty($name)) {
                            continue;
                        }

                        // Parse the date string into a DateTime object
                        try {
                            $date = DateTime::createFromFormat("m/d/Y", $date)->format("Y-m-d");
                        } catch (Exception $e) {
                            // If the date is not in the expected format, skip the row
                            error_log("Invalid date format: " . $date);
                            continue;
                        }

                        // Insert into admin_history
                        $queryAdminHistory = "INSERT INTO admin_history (patient_id, name, date, time_in, time_out, medicine, quantity, health_history, purpose_of_visit, remarks, created_at)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmtAdminHistory = $db->prepare($queryAdminHistory);
                        $stmtAdminHistory->execute([$patient_id, $name, $date, $time_in, $time_out, $medicine, $quantity, $health_history, $purpose_of_visit, $remarks]);

                        // Insert into clinic_logs
                        $queryClinicLogs = "INSERT INTO clinic_logs (patient_id, name, gender, age, role, course_section, blood_pressure, heart_rate, blood_oxygen, height, weight, temperature, remarks, date, time_in, created_at)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmtClinicLogs = $db->prepare($queryClinicLogs);
                        $stmtClinicLogs->execute([$patient_id, $name, $gender, $age, $role, $course_section, $blood_pressure, $heart_rate, $blood_oxygen, $height, $weight, $temperature, $remarks, $date, $time_in]);

                        $insertCount++;
                    }

                    $_SESSION["success"] = "Successfully imported $insertCount records from Excel.";
                } catch (Exception $e) {
                    $_SESSION["error"] = "Error importing Excel file: " . $e->getMessage();
                    error_log("Excel import failed: " . $e->getMessage());
                } finally {
                    unlink($inputFileName);
                }
            } else {
                $_SESSION["error"] = "Error moving uploaded file.";
            }
        } else {
            $_SESSION["error"] = "Invalid file type. Only Excel files (xlsx, xls) are allowed.";
        }
    } else {
        $_SESSION["error"] = "No file uploaded or upload error.";
    }
    header("Location: admin_history.php");
    exit;
}

// Handle Deletion Request
if (isset($_POST['delete_id'])) {
    $delete_id = (int) $_POST['delete_id'];
    $delete_from_popup = isset($_POST['delete_from_popup']) && $_POST['delete_from_popup'] === 'true';

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT patient_id, medicine, quantity FROM admin_history WHERE id = ?");
        $stmt->execute([$delete_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            $patient_id = $record['patient_id'];

            if ($delete_from_popup) {
                $medicine = $record['medicine'];
                $quantity = (int) $record['quantity'];

                $deleteStmt = $db->prepare("DELETE FROM admin_history WHERE id = ?");
                $deleteStmt->execute([$delete_id]);
                error_log("Deleted specific admin_history entry ID: $delete_id for patient_id: $patient_id");

                $stmt = $db->prepare("SELECT COUNT(*) FROM admin_history WHERE patient_id = ? AND id != ?");
                $stmt->execute([$patient_id, $delete_id]);
                $remaining_entries = $stmt->fetchColumn();

                if ($remaining_entries == 0) {
                    $deleteClinicStmt = $db->prepare("DELETE FROM clinic_logs WHERE patient_id = ?");
                    $deleteClinicStmt->execute([$patient_id]);
                    error_log("Deleted from clinic_logs for patient_id: $patient_id");
                }

                if (!empty($medicine) && $quantity > 0) {
                    $checkStmt = $db->prepare("SELECT quantity, remaining_items FROM inventory WHERE name = ?");
                    $checkStmt->execute([$medicine]);
                    $inventoryRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($inventoryRecord) {
                        $newRemainingItems = $inventoryRecord['remaining_items'] + $quantity;
                        $newQuantity = $inventoryRecord['quantity'] - $quantity;
                        $updateStmt = $db->prepare("UPDATE inventory SET remaining_items = ?, quantity = ? WHERE name = ?");
                        $updateStmt->execute([$newRemainingItems, $newQuantity, $medicine]);
                        error_log("Adjusted inventory for medicine: $medicine, added back $quantity to remaining_items (now $newRemainingItems), subtracted $quantity from quantity (now $newQuantity)");
                    } else {
                        error_log("Medicine $medicine not found in inventory during deletion for patient_id: $patient_id");
                    }
                }

                $_SESSION['popup_notification'] = "History entry deleted successfully.";
            } else {
                $stmt = $db->prepare("SELECT medicine, quantity FROM admin_history WHERE patient_id = ?");
                $stmt->execute([$patient_id]);
                $history_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($history_entries as $entry) {
                    $medicine = $entry['medicine'];
                    $quantity = (int) $entry['quantity'];
                    if (!empty($medicine) && $quantity > 0) {
                        $checkStmt = $db->prepare("SELECT quantity, remaining_items FROM inventory WHERE name = ?");
                        $checkStmt->execute([$medicine]);
                        $inventoryRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

                        if ($inventoryRecord) {
                            $newRemainingItems = $inventoryRecord['remaining_items'] + $quantity;
                            $newQuantity = $inventoryRecord['quantity'] - $quantity;
                            $updateStmt = $db->prepare("UPDATE inventory SET remaining_items = ?, quantity = ? WHERE name = ?");
                            $updateStmt->execute([$newRemainingItems, $newQuantity, $medicine]);
                            error_log("Adjusted inventory for medicine: $medicine, added back $quantity to remaining_items (now $newRemainingItems), subtracted $quantity from quantity (now $newQuantity) for patient_id: $patient_id");
                        } else {
                            error_log("Medicine $medicine not found in inventory during deletion for patient_id: $patient_id");
                        }
                    }
                }

                $deleteStmt = $db->prepare("DELETE FROM admin_history WHERE patient_id = ?");
                $deleteStmt->execute([$patient_id]);
                error_log("Deleted all admin_history entries for patient_id: $patient_id");

                $deleteClinicStmt = $db->prepare("DELETE FROM clinic_logs WHERE patient_id = ?");
                $deleteClinicStmt->execute([$patient_id]);
                error_log("Deleted from clinic_logs for patient_id: $patient_id");
            }
        }

        $db->commit();
        header("Location: admin_history.php");
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Delete error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to delete record: " . $e->getMessage();
        header("Location: admin_history.php");
        exit;
    }
}

// Pagination for Main History Table
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Handle search
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = '';
$params = [];
if (!empty($searchTerm)) {
    $whereClause = "WHERE patient_id LIKE ? OR name LIKE ? OR DATE_FORMAT(date, '%m/%d/%Y') LIKE ? OR DATE_FORMAT(time_in, '%h:%i %p') LIKE ? OR DATE_FORMAT(time_out, '%h:%i %p') LIKE ? OR medicine LIKE ? OR quantity LIKE ? OR health_history LIKE ? OR purpose_of_visit LIKE ? OR remarks LIKE ?";
    $searchLike = '%' . $searchTerm . '%';
    $params = array_fill(0, 10, $searchLike);
}

// Fetch History Records with Pagination and Search
$query = "SELECT id, patient_id, name, DATE_FORMAT(date, '%m/%d/%Y') as date, time_in, time_out, medicine, quantity, health_history, purpose_of_visit, remarks, created_at 
          FROM admin_history 
          $whereClause 
          ORDER BY created_at DESC 
          LIMIT ? OFFSET ?";
$stmt = $db->prepare($query);

// Bind parameters
$paramCount = 1;
if (!empty($searchTerm)) {
    for ($i = 0; $i < 10; $i++) {
        $stmt->bindValue($paramCount++, $params[$i], PDO::PARAM_STR);
    }
}
$stmt->bindValue($paramCount++, $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue($paramCount++, $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch earliest name for each patient_id
$nameMap = [];
$nameStmt = $db->prepare("SELECT patient_id, name FROM admin_history WHERE patient_id NOT IN ('na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A') GROUP BY patient_id HAVING MIN(created_at)");
$nameStmt->execute();
while ($row = $nameStmt->fetch(PDO::FETCH_ASSOC)) {
    $nameMap[$row['patient_id']] = $row['name'];
}

// Total count for pagination
$totalStmt = $db->prepare("SELECT COUNT(*) FROM admin_history $whereClause");
if (!empty($searchTerm)) {
    $totalStmt->execute(array_fill(0, 10, '%' . $searchTerm . '%'));
} else {
    $totalStmt->execute();
}
$totalCount = $totalStmt->fetchColumn();
$totalPages = ceil($totalCount / $itemsPerPage);

// Group rows by patient_id or patient_id+id for NA entries
$naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];
$grouped_rows = [];
$history_by_patient = [];
foreach ($rows as $row) {
    $patient_id = $row['patient_id'];
    $key = in_array(strtolower($patient_id), array_map('strtolower', $naValues)) ? $patient_id . '_' . $row['id'] : $patient_id;

    // Use earliest name for non-NA patient_id, otherwise use the record's name
    $display_name = in_array(strtolower($patient_id), array_map('strtolower', $naValues)) ? $row['name'] : ($nameMap[$patient_id] ?? $row['name']);

    if (!isset($grouped_rows[$key]) || strtotime($row['created_at']) > strtotime($grouped_rows[$key]['created_at'])) {
        $grouped_rows[$key] = [
            'id' => $row['id'],
            'patient_id' => $row['patient_id'],
            'name' => $display_name,
            'date' => $row['date'],
            'time_in' => $row['time_in'],
            'time_out' => $row['time_out'],
            'medicine' => $row['medicine'] ?? '',
            'quantity' => $row['quantity'] ?? '',
            'health_history' => $row['health_history'] ?? '',
            'purpose_of_visit' => $row['purpose_of_visit'] ?? '',
            'remarks' => $row['remarks'] ?? '',
            'created_at' => $row['created_at']
        ];
    }

    if (!isset($history_by_patient[$key])) {
        $history_by_patient[$key] = [];
    }
    $history_by_patient[$key][] = [
        'id' => $row['id'],
        'date' => $row['date'],
        'time_in' => $row['time_in'],
        'time_out' => $row['time_out'],
        'medicine' => $row['medicine'],
        'quantity' => $row['quantity'],
        'health_history' => $row['health_history'],
        'purpose_of_visit' => $row['purpose_of_visit'],
        'remarks' => $row['remarks'],
        'created_at' => $row['created_at'],
        'name' => $row['name'] // Keep original name for popup
    ];
}

$combined_rows = array_values($grouped_rows);

// Fetch filtered history records for Excel and PDF
$allHistoryQuery = "SELECT id, patient_id, name, DATE_FORMAT(date, '%m/%d/%Y') as date, time_in, time_out, medicine, quantity, health_history, purpose_of_visit, remarks, created_at 
                    FROM admin_history 
                    $whereClause 
                    ORDER BY created_at DESC";
$allHistoryStmt = $db->prepare($allHistoryQuery);
if (!empty($searchTerm)) {
    $allHistoryStmt->execute(array_fill(0, 10, '%' . $searchTerm . '%'));
} else {
    $allHistoryStmt->execute();
}
$allHistoryRows = $allHistoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Apply earliest name to allHistoryRows for Excel and PDF
foreach ($allHistoryRows as &$row) {
    if (!in_array(strtolower($row['patient_id']), array_map('strtolower', $naValues))) {
        $row['name'] = $nameMap[$row['patient_id']] ?? $row['name'];
    }
}

// Prepare data for PDF
$historyForPdf = array_map(function ($row) {
    $time_in = $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : 'N/A';
    $time_out = $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : 'N/A';
    return [
        $row['patient_id'] ? htmlspecialchars($row['patient_id']) : 'N/A',
        htmlspecialchars($row['name']),
        $row['date'],
        $time_in,
        $time_out,
        $row['medicine'] ?? 'N/A',
        $row['quantity'] ?? 'N/A',
        $row['health_history'] ?? 'N/A',
        $row['purpose_of_visit'] ?? 'N/A',
        $row['remarks'] ?? 'N/A'
    ];
}, $allHistoryRows);

// Handle Excel download
if (isset($_POST['download_excel'])) {
    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('History List');

        $headers = ['ID', 'Name', 'Date', 'Log In', 'Log Out', 'Release Medicine', 'Quantity Used', 'Health History', 'Purpose of Visit', 'Remarks'];
        $clinicLogHeaders = ['Gender', 'Age', 'Role', 'Course and Section', 'Blood Pressure', 'Heart Rate (BPM)', 'Blood Oxygen (%)', 'Height (cm)', 'Weight (kg)', 'Temperature (°C)'];
        $headers = array_merge($headers, $clinicLogHeaders);
        $sheet->fromArray($headers, NULL, 'A1');

        $rowNum = 2;
        foreach ($allHistoryRows as $row) {
            $time_in = $row["time_in"] ? date("h:i A", strtotime($row["time_in"])) : "N/A";
            $time_out = $row["time_out"] ? date("h:i A", strtotime($row["time_out"])) : "N/A";
            $date = $row["date"] ? date("m/d/Y", strtotime($row["date"])) : "N/A";

            // Fetch clinic_logs data for the patient
            $clinicLogsQuery = "SELECT gender, age, role, course_section, blood_pressure, heart_rate, blood_oxygen, height, weight, temperature, remarks FROM clinic_logs WHERE patient_id = ?";
            $clinicLogsStmt = $db->prepare($clinicLogsQuery);
            $clinicLogsStmt->execute([$row["patient_id"]]);
            $clinicLogs = $clinicLogsStmt->fetch(PDO::FETCH_ASSOC);

            // Prepare clinic_logs data for Excel
            $gender = $clinicLogs ? htmlspecialchars($clinicLogs['gender'] ?? 'N/A') : 'N/A';
            $age = $clinicLogs ? htmlspecialchars($clinicLogs['age'] ?? 'N/A') : 'N/A';
            $role = $clinicLogs ? htmlspecialchars($clinicLogs['role'] ?? 'N/A') : 'N/A';
            $courseSection = $clinicLogs ? htmlspecialchars($clinicLogs['course_section'] ?? 'N/A') : 'N/A';
            $bloodPressure = $clinicLogs ? htmlspecialchars($clinicLogs['blood_pressure'] ?? 'N/A') : 'N/A';
            $heartRate = $clinicLogs ? htmlspecialchars($clinicLogs['heart_rate'] ?? 'N/A') : 'N/A';
            $bloodOxygen = $clinicLogs ? htmlspecialchars($clinicLogs['blood_oxygen'] ?? 'N/A') : 'N/A';
            $height = $clinicLogs ? htmlspecialchars($clinicLogs['height'] ?? 'N/A') : 'N/A';
            $weight = $clinicLogs ? htmlspecialchars($clinicLogs['weight'] ?? 'N/A') : 'N/A';
            $temperature = $clinicLogs ? htmlspecialchars($clinicLogs['temperature'] ?? 'N/A') : 'N/A';
            $remarks = $clinicLogs ? htmlspecialchars($clinicLogs['remarks'] ?? 'N/A') : 'N/A';

            $rowData = [
                $row["patient_id"] ? htmlspecialchars($row["patient_id"]) : "N/A",
                htmlspecialchars($row["name"]),
                $date,
                $time_in,
                $time_out,
                $row["medicine"] ?? "N/A",
                $row["quantity"] ?? "N/A",
                $row["health_history"] ?? "N/A",
                $row["purpose_of_visit"] ?? "N/A",
                $row["remarks"] ?? "N/A",
                $gender,
                $age,
                $role,
                $courseSection,
                $bloodPressure,
                $heartRate,
                $bloodOxygen,
                $height,
                $weight,
                $temperature
            ];

            $sheet->fromArray($rowData, null, "A" . $rowNum);
            $rowNum++;
        }

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highestColumn . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF012365'],
            ],
        ]);

        foreach (range('A', $highestColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="history.xlsx"');
        header('Cache-Control: max-age=0');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    } catch (Exception $e) {
        error_log("Excel generation failed: " . $e->getMessage());
        $_SESSION['error'] = "Failed to generate Excel file: " . $e->getMessage();
        header("Location: admin_history.php");
        exit;
    }
}

// Pagination function
function generatePagination($page, $totalPages, $baseUrl, $isPopup = false)
{
    $pagination = '';
    if ($page > 1) {
        if ($isPopup) {
            $pagination .= '<a href="#" class="btn previous" onclick="loadPopupPage(' . ($page - 1) . '); return false;">PREVIOUS</a>';
        } else {
            $pagination .= '<a href="' . $baseUrl . 'page=' . ($page - 1) . '" class="btn previous">PREVIOUS</a>';
        }
    }
    if ($totalPages <= 5) {
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($isPopup) {
                $pagination .= '<a href="#" class="btn' . ($i == $page ? ' active' : '') . '" onclick="loadPopupPage(' . $i . '); return false;">' . $i . '</a>';
            } else {
                $pagination .= '<a href="' . $baseUrl . 'page=' . $i . '" class="btn' . ($i == $page ? ' active' : '') . '">' . $i . '</a>';
            }
        }
    } else {
        if ($isPopup) {
            $pagination .= '<a href="#" class="btn' . (1 == $page ? ' active' : '') . '" onclick="loadPopupPage(1); return false;">1</a>';
        } else {
            $pagination .= '<a href="' . $baseUrl . 'page=1" class="btn' . (1 == $page ? ' active' : '') . '">1</a>';
        }
        if ($page > 3) {
            $pagination .= '<span class="ellipsis">...</span>';
        }
        $start = max(2, $page - 2);
        $end = min($totalPages - 1, $page + 2);
        for ($i = $start; $i <= $end; $i++) {
            if ($isPopup) {
                $pagination .= '<a href="#" class="btn' . ($i == $page ? ' active' : '') . '" onclick="loadPopupPage(' . $i . '); return false;">' . $i . '</a>';
            } else {
                $pagination .= '<a href="' . $baseUrl . 'page=' . $i . '" class="btn' . ($i == $page ? ' active' : '') . '">' . $i . '</a>';
            }
        }
        if ($page < $totalPages - 2) {
            $pagination .= '<span class="ellipsis">...</span>';
        }
        if ($isPopup) {
            $pagination .= '<a href="#" class="btn' . ($totalPages == $page ? ' active' : '') . '" onclick="loadPopupPage(' . $totalPages . '); return false;">' . $totalPages . '</a>';
        } else {
            $pagination .= '<a href="' . $baseUrl . 'page=' . $totalPages . '" class="btn' . ($totalPages == $page ? ' active' : '') . '">' . $totalPages . '</a>';
        }
    }
    if ($page < $totalPages) {
        if ($isPopup) {
            $pagination .= '<a href="#" class="btn next" onclick="loadPopupPage(' . ($page + 1) . '); return false;">NEXT</a>';
        } else {
            $pagination .= '<a href="' . $baseUrl . 'page=' . ($page + 1) . '" class="btn next">NEXT</a>';
        }
    }
    return $pagination;
}

$baseUrl = "admin_history.php?" . (!empty($searchTerm) ? "search=" . urlencode($searchTerm) . "&" : "");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: History Menu Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="update_history.css">
    <link rel="icon" href="images/sti_logo.png" type="image/png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.24/jspdf.plugin.autotable.min.js"></script>
    <style>
        #file-name-label {
            margin-left: 10px;
            font-style: italic;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
        }

        .pagination a.btn {
            padding: 5px 10px;
            margin: 0 2px;
            text-decoration: none;
            color: #333;
            background-color: #fff;
            border: 1px solid #666;
            border-radius: 4px;
        }

        .pagination a.btn.active {
            background-color: #007bff;
            color: #fff;
            border-color: #007bff;
        }

        .pagination a.btn.previous,
        .pagination a.btn.next {
            background-color: #333;
            color: #fff;
            border-color: #333;
        }

        .pagination span.ellipsis {
            padding: 5px 10px;
            color: #666;
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
            background-color: #333;
            color: #e0e0e0;
        }

        body.dark-mode .dashboard-container {
            background-color: #000000;
        }

        body.dark-mode .header-actions {
            background-color: #1e1e1e;
        }

        body.dark-mode .search-bar input[type="text"] {
            background-color: #1e1e1e;
            color: #ffffff;
            border-color: #666;
        }

        body.dark-mode table {
            background-color: #777;
        }

        body.dark-mode table th {
            background-color: #003d82;
            color: #ffffff;
            border: 1px solid #ffffff;
        }

        body.dark-mode table td {
            background-color: #000000;
            color: #e0e0e0;
            border: 1px solid #ffffff;
        }

        body.dark-mode .btn {
            background-color: #1e1e1e;
            color: #ffffff;
            border-color: #666;
        }

        body.dark-mode .btn.active {
            background-color: #0056b3;
            color: #ffffff;
            border-color: #0056b3;
        }

        body.dark-mode .btn.previous,
        body.dark-mode .btn.next {
            background-color: #666;
            color: #ffffff;
            border-color: #666;
        }

        body.dark-mode .view-btn {
            background-color: #1e1e1e;
            color: #ffffff;
            border-color: #666;
        }

        body.dark-mode .view-btn:hover {
            background-color: #ffffff;
            color: #1e1e1e;
            border-color: #666;
        }

        body.dark-mode .delete-btn {
            background-color: #1e1e1e;
            color: #ff6b6b;
            border-color: #666;
        }

        body.dark-mode .delete-btn:hover {
            background-color: #ff6b6b;
            color: #1e1e1e;
            border-color: #666;
        }

        body.dark-mode .print-btn {
            background-color: #1e1e1e;
            color: #4ecdc4;
            border-color: #666;
        }

        body.dark-mode .print-btn:hover {
            background-color: #4ecdc4;
            color: #1e1e1e;
            border-color: #666;
        }

        body.dark-mode .search-btn {
            background-color: #1e1e1e;
            color: #ffffff;
            border-color: #666;
        }

        body.dark-mode .search-btn:hover {
            background-color: #007bff;
            color: #ffffff;
            border-color: #007bff;
        }

        .search-bar input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }
    </style>
    <script>
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
            <h1>History Dashboard</h1>
            <?php if (isset($_SESSION["success"])): ?>
                <div class="success-message">
                    <?= $_SESSION["success"] ?>
                    <?php unset($_SESSION["success"]); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION["error"])): ?>
                <div class="error-message">
                    <?= $_SESSION["error"] ?>
                    <?php unset($_SESSION["error"]); ?>
                </div>
            <?php endif; ?>
            <div class="header-actions">
                <div class="search-bar">
                    <form id="search-form" method="get">
                        <input type="text" name="search" placeholder="Search history..." id="search-bar"
                            value="<?= htmlspecialchars($searchTerm) ?>">
                    </form>
                </div>
                <div class="table-actions">
                    <button id="download-pdf" class="btn download-pdf-btn2">PDF</button>
                    <form method="post" style="display: inline;">
                        <button type="submit" name="download_excel" class="btn download-excel-btn">Excel</button>
                    </form>
                    <form method="post" enctype="multipart/form-data" style="display: inline;">
                        <input type="file" name="import_excel" id="import_excel" accept=".xlsx, .xls"
                            style="display: none;">
                        <label for="import_excel" class="btn import-excel-btn">Import Excel</label>
                        <span id="file-name-label"></span>
                        <button type="submit" name="upload_excel" class="btn upload-excel-btn">Upload</button>
                    </form>
                </div>
            </div>
            <table id="history-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Date</th>
                        <th>Log In</th>
                        <th>Log Out</th>
                        <th>Release Medicine</th>
                        <th>Quantity Used</th>
                        <th>Health History</th>
                        <th>Purpose</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                    <?php if (empty($combined_rows)): ?>
                        <tr>
                            <td colspan="11">No history yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($combined_rows as $row): ?>
                            <?php
                            $key = in_array(strtolower($row['patient_id']), array_map('strtolower', $naValues)) ? $row['patient_id'] . '_' . $row['id'] : $row['patient_id'];
                            ?>
                            <tr data-key="<?= htmlspecialchars($key) ?>">
                                <td><?= $row['patient_id'] ? htmlspecialchars($row['patient_id']) : 'N/A' ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['date']) ?></td>
                                <td><?= $row['time_in'] ? htmlspecialchars(date('h:i A', strtotime($row['time_in']))) : 'N/A' ?>
                                </td>
                                <td><?= $row['time_out'] ? htmlspecialchars(date('h:i A', strtotime($row['time_out']))) : 'N/A' ?>
                                </td>
                                <td><?= htmlspecialchars($row['medicine'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['quantity'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['health_history'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['purpose_of_visit'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['remarks'] ?? 'N/A') ?></td>
                                <td>
                                    <button class="btn view-btn view-history-btn" data-key="<?= htmlspecialchars($key) ?>"
                                        data-history='<?= json_encode($history_by_patient[$key] ?? []) ?>'>
                                        View
                                    </button>
                                    <form method="post" style="display:inline;"
                                        onsubmit="return confirm('This will delete ALL history entries for this patient and their clinic logs record. Are you sure?')">
                                        <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                        <input type="hidden" name="delete_from_popup" value="false">
                                        <button type="submit" class="btn delete-btn">Delete</button>
                                    </form>
                                    <form method="post" action="print_preview.php" target="_blank" style="display:inline;">
                                        <input type="hidden" name="data" value="<?= htmlspecialchars(json_encode([
                                            "patient_id" => $row["patient_id"] ? $row["patient_id"] : 'N/A',
                                            "name" => $row["name"],
                                            "date" => $row["date"],
                                            "time_in" => $row["time_in"] ? date('h:i A', strtotime($row["time_in"])) : 'N/A',
                                            "time_out" => $row["time_out"] ? date('h:i A', strtotime($row["time_out"])) : 'N/A',
                                            "medicine" => $row["medicine"],
                                            "quantity" => $row["quantity"],
                                            "healthHistory" => $row["health_history"],
                                            "purposeOfVisit" => $row["purpose_of_visit"],
                                            "remarks" => $row["remarks"]
                                        ]), ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn print-btn">Print</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalCount > 0): ?>
                <div class="pagination">
                    <?= generatePagination($page, $totalPages, $baseUrl, false) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>
    <div class="history-popup" id="history-popup">
        <h3>Visit History</h3>
        <div id="popup-notification" class="notification" style="display: none;"></div>
        <table id="popup-history-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Log In</th>
                    <th>Log Out</th>
                    <th>Release Medicine</th>
                    <th>Quantity Used</th>
                    <th>Health History</th>
                    <th>Purpose</th>
                    <th>Remarks</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="history-body"></tbody>
        </table>
        <div class="pagination" id="popup-pagination"></div>
        <button onclick="closePopup()" class="btn">Close</button>
    </div>

    <script>
        const combinedRows = <?php echo json_encode($combined_rows); ?>;
        const historyByPatient = <?php echo json_encode($history_by_patient); ?>;
        const historyForPdf = <?php echo json_encode($historyForPdf); ?>;
        const naValues = ['na', 'NA', 'N/A', 'n/a', 'N/a', 'n/A'];
        let currentPopupHistory = [];
        let popupPage = 1;
        const popupItemsPerPage = 5;

        function to12HourFormat(time) {
            if (!time || time === 'N/A') return 'N/A';
            const [hours, minutes] = time.split(':');
            const hourNum = parseInt(hours);
            const period = hourNum >= 12 ? 'PM' : 'AM';
            const hour12 = hourNum % 12 || 12;
            return `${hour12}:${minutes} ${period}`;
        }

        function loadTableData(data = combinedRows) {
            const tbody = document.getElementById('table-body');
            tbody.innerHTML = '';
            if (data.length > 0) {
                data.forEach(row => {
                    const key = naValues.includes(row.patient_id.toLowerCase()) ? `${row.patient_id}_${row.id}` : row.patient_id;
                    const patientHistory = JSON.stringify(historyByPatient[key] || []);
                    const printData = JSON.stringify({
                        patientId: row.patient_id ? row.patient_id : 'N/A',
                        name: row.name,
                        date: row.date,
                        time_in: to12HourFormat(row.time_in),
                        time_out: to12HourFormat(row.time_out),
                        medicine: row.medicine,
                        quantity: row.quantity,
                        healthHistory: row.health_history,
                        purposeOfVisit: row.purpose_of_visit,
                        remarks: row.remarks
                    });
                    tbody.innerHTML += `
                        <tr data-key="${key}">
                            <td>${row.patient_id ? row.patient_id : 'N/A'}</td>
                            <td>${row.name}</td>
                            <td>${row.date}</td>
                            <td>${to12HourFormat(row.time_in)}</td>
                            <td>${to12HourFormat(row.time_out)}</td>
                            <td>${row.medicine || 'N/A'}</td>
                            <td>${row.quantity || 'N/A'}</td>
                            <td>${row.health_history || 'N/A'}</td>
                            <td>${row.purpose_of_visit || 'N/A'}</td>
                            <td>${row.remarks || 'N/A'}</td>
                            <td>
                                <button class="btn view-btn view-history-btn" data-key="${key}" data-history='${patientHistory}'>View</button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('This will delete ALL history entries for this patient and their clinic logs record. Are you sure?')">
                                    <input type="hidden" name="delete_id" value="${row.id}">
                                    <input type="hidden" name="delete_from_popup" value="false">
                                    <button type="submit" class="btn delete-btn">Delete</button>
                                </form>
                                <form method="post" action="print_preview.php" target="_blank" style="display:inline;">
                                    <input type="hidden" name="data" value='${printData}'>
                                    <button type="submit" class="btn print-btn">Print</button>
                                </form>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="11">No history yet.</td></tr>';
            }
            attachHistoryListeners();
        }

        // Debounce function to limit form submissions
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        document.addEventListener('DOMContentLoaded', function () {
            const searchForm = document.getElementById('search-form');
            const searchInput = document.getElementById('search-bar');

            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault(); // Stop any default behavior
                    searchForm.submit(); // Send the form on its way
                }
            });
        });

        function loadPopupHistory(history, page = 1) {
            popupPage = page;
            currentPopupHistory = history.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            const tbody = document.getElementById('history-body');
            tbody.innerHTML = '';

            const start = (page - 1) * popupItemsPerPage;
            const end = start + popupItemsPerPage;
            const paginatedHistory = currentPopupHistory.slice(start, end);

            if (paginatedHistory.length > 0) {
                paginatedHistory.forEach(entry => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${entry.date}</td>
                            <td>${to12HourFormat(entry.time_in)}</td>
                            <td>${to12HourFormat(entry.time_out)}</td>
                            <td>${entry.medicine || 'N/A'}</td>
                            <td>${entry.quantity || 'N/A'}</td>
                            <td>${entry.health_history || 'N/A'}</td>
                            <td>${entry.purpose_of_visit || 'N/A'}</td>
                            <td>${entry.remarks || 'N/A'}</td>
                            <td>
                                <a href="edit_history.php?id=${entry.id}" class="btn edit-btn">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Sure you want to delete this? This will also remove the patient\'s record from clinic logs if this is the last history entry.')">
                                    <input type="hidden" name="delete_id" value="${entry.id}">
                                    <input type="hidden" name="delete_from_popup" value="true">
                                    <button type="submit" class="btn delete-btn">Delete</button>
                                </form>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="9">No records.</td></tr>';
            }

            const totalPopupPages = Math.ceil(currentPopupHistory.length / popupItemsPerPage);
            const popupPagination = document.getElementById('popup-pagination');
            popupPagination.innerHTML = '';
            if (currentPopupHistory.length > popupItemsPerPage) {
                popupPagination.innerHTML = `<?php echo generatePagination(0, 0, '', true); ?>`.replace('page=0', '').replace('totalPages=0', '');
                const links = popupPagination.querySelectorAll('a.btn');
                links.forEach(link => {
                    const pageNum = parseInt(link.textContent);
                    if (!isNaN(pageNum)) {
                        link.classList.toggle('active', pageNum === popupPage);
                        link.onclick = (e) => {
                            e.preventDefault();
                            loadPopupHistory(currentPopupHistory, pageNum);
                        };
                    }
                });
                const prev = popupPagination.querySelector('.previous');
                if (prev) {
                    prev.onclick = (e) => {
                        e.preventDefault();
                        if (popupPage > 1) loadPopupHistory(currentPopupHistory, popupPage - 1);
                    };
                }
                const next = popupPagination.querySelector('.next');
                if (next) {
                    next.onclick = (e) => {
                        e.preventDefault();
                        if (popupPage < totalPopupPages) loadPopupHistory(currentPopupHistory, popupPage + 1);
                    };
                }
            }
        }

        function loadPopupPage(page) {
            loadPopupHistory(currentPopupHistory, page);
        }

        function attachHistoryListeners() {
            document.querySelectorAll('.view-history-btn').forEach(button => {
                button.addEventListener('click', function () {
                    const key = this.getAttribute('data-key');
                    const history = JSON.parse(this.getAttribute('data-history'));
                    loadPopupHistory(history, 1);
                    document.getElementById('history-popup').style.display = 'block';
                    document.getElementById('overlay').style.display = 'block';
                });
            });
        }

        function closePopup() {
            document.getElementById('history-popup').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
            popupPage = 1;
            currentPopupHistory = [];
        }

        document.getElementById('overlay').addEventListener('click', closePopup);

        window.onload = function () {
            loadTableData();
        };

        function updateHistoryTable(updatedData) {
            const tbody = document.getElementById('table-body');
            const isNa = naValues.includes((updatedData.patient_id || '').toLowerCase());
            const key = isNa ? `${updatedData.patient_id}_${updatedData.id}` : updatedData.patient_id;
            let row = tbody.querySelector(`tr[data-key="${key}"]`);
            const historyEntry = {
                id: updatedData.id,
                date: updatedData.date || new Date().toISOString().slice(0, 10),
                time_in: updatedData.time_in ? to12HourFormat(updatedData.time_in) : 'N/A',
                time_out: updatedData.time_out ? to12HourFormat(updatedData.time_out) : 'N/A',
                medicine: updatedData.medicine || 'N/A',
                quantity: updatedData.quantity || 'N/A',
                health_history: updatedData.health_history || 'N/A',
                purpose_of_visit: updatedData.purpose_of_visit || 'N/A',
                remarks: updatedData.remarks || 'N/A',
                created_at: new Date().toISOString()
            };

            if (row) {
                row.cells[2].textContent = historyEntry.date;
                row.cells[3].textContent = historyEntry.time_in;
                row.cells[4].textContent = historyEntry.time_out;
                row.cells[5].textContent = historyEntry.medicine;
                row.cells[6].textContent = historyEntry.quantity;
                row.cells[7].textContent = historyEntry.health_history;
                row.cells[8].textContent = historyEntry.purpose_of_visit;
                row.cells[9].textContent = historyEntry.remarks;
                let currentHistory = JSON.parse(row.querySelector('.view-history-btn').dataset.history);
                const index = currentHistory.findIndex(entry => entry.id == updatedData.id);
                if (index >= 0) {
                    currentHistory[index] = historyEntry;
                } else {
                    currentHistory.unshift(historyEntry);
                }
                row.querySelector('.view-history-btn').dataset.history = JSON.stringify(currentHistory);
            } else {
                const newRow = document.createElement('tr');
                const patientHistory = JSON.stringify([historyEntry]);
                const printData = JSON.stringify({
                    patientId: updatedData.patient_id ? updatedData.patient_id : 'N/A',
                    name: updatedData.name,
                    date: historyEntry.date,
                    time_in: historyEntry.time_in,
                    time_out: historyEntry.time_out,
                    medicine: historyEntry.medicine,
                    quantity: historyEntry.quantity,
                    healthHistory: historyEntry.health_history,
                    purposeOfVisit: historyEntry.purpose_of_visit,
                    remarks: historyEntry.remarks
                });
                newRow.setAttribute('data-key', key);
                newRow.innerHTML = `
                    <tr data-key="${key}">
                        <td>${updatedData.patient_id ? updatedData.patient_id : 'N/A'}</td>
                        <td>${updatedData.name}</td>
                        <td>${historyEntry.date}</td>
                        <td>${historyEntry.time_in}</td>
                        <td>${historyEntry.time_out}</td>
                        <td>${historyEntry.medicine}</td>
                        <td>${historyEntry.quantity}</td>
                        <td>${historyEntry.health_history}</td>
                        <td>${historyEntry.purpose_of_visit}</td>
                        <td>${historyEntry.remarks}</td>
                        <td>
                            <button class="btn view-btn view-history-btn" data-key="${key}" data-history='${patientHistory}'>View</button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('This will delete ALL history entries for this patient and their clinic logs record. Are you sure?')">
                                <input type="hidden" name="delete_id" value="${updatedData.id || ''}">
                                <input type="hidden" name="delete_from_popup" value="false">
                                <button type="submit" class="btn delete-btn">Delete</button>
                            </form>
                            <form method="post" action="print_preview.php" target="_blank" style="display:inline;">
                                <input type="hidden" name="data" value='${printData}'>
                                <button type="submit" class="btn print-btn">Print</button>
                            </form>
                        </td>
                    </tr>
                `;
                tbody.insertBefore(newRow, tbody.firstChild);
                attachHistoryListeners();
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const themeToggle = document.getElementById('checkbox');
            const currentTheme = localStorage.getItem('theme');
            if (currentTheme === 'dark') {
                document.body.classList.add('dark-mode');
                themeToggle.checked = true;
            } else {
                document.body.classList.remove('dark-mode');
                themeToggle.checked = false;
            }

            themeToggle.addEventListener('change', function () {
                if (this.checked) {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('theme', 'light');
                }
            });

            if (window.location.hash === '#from-update') {
                const updatedData = <?php echo json_encode($_SESSION['updated_data'] ?? []); ?>;
                if (updatedData && updatedData.medicine) {
                    updateHistoryTable(updatedData);
                }
                window.location.hash = '';
            }

            document.querySelector('.logout').addEventListener('click', function (event) {
                event.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = this.href;
                }
            });
        });

        document.getElementById('download-pdf').addEventListener('click', function () {
            try {
                const {
                    jsPDF
                } = window.jspdf;
                const pdf = new jsPDF('portrait', 'mm', 'letter');

                pdf.setProperties({
                    title: 'History List',
                    subject: 'Clinic History',
                    author: 'Clinic Admin'
                });

                pdf.setFontSize(14);
                pdf.setFont("helvetica", "bold");
                pdf.text("STI Clinic Management System - History List", 105, 15, {
                    align: 'center'
                });
                pdf.setFontSize(8);
                pdf.setFont("helvetica", "normal");

                pdf.autoTable({
                    startY: 30,
                    head: [
                        ['ID', 'Name', 'Date', 'Log In', 'Log Out', 'Medicine', 'Quantity', 'Health History', 'Purpose', 'Remarks']
                    ],
                    body: historyForPdf,
                    theme: 'grid',
                    styles: {
                        font: 'helvetica',
                        fontSize: 6,
                        cellPadding: 1,
                        overflow: 'linebreak',
                        minCellHeight: 5,
                        halign: 'center',
                    },
                    headStyles: {
                        fillColor: [1, 35, 101],
                        textColor: 255,
                        fontStyle: 'bold',
                        fontSize: 6,
                        halign: 'center',
                    },
                    columnStyles: {
                        0: {
                            cellWidth: 16
                        },
                        1: {
                            cellWidth: 25
                        },
                        2: {
                            cellWidth: 15
                        },
                        3: {
                            cellWidth: 15
                        },
                        4: {
                            cellWidth: 15
                        },
                        5: {
                            cellWidth: 15
                        },
                        6: {
                            cellWidth: 15
                        },
                        7: {
                            cellWidth: 20
                        },
                        8: {
                            cellWidth: 20
                        },
                        9: {
                            cellWidth: 20
                        }
                    },
                    margin: {
                        left: 10,
                        right: 10
                    },
                    pageBreak: 'avoid'
                });

                const pageCount = pdf.internal.getNumberOfPages();
                for (let i = 1; i <= pageCount; i++) {
                    pdf.setPage(i);
                    pdf.setFontSize(8);
                    pdf.text(`Page ${i} of ${pageCount} - STI Clinic Management System`, 105, pdf.internal.pageSize.getHeight() - 10, {
                        align: 'center'
                    });
                }

                pdf.save(`history-${new Date().toISOString().slice(0, 10)}.pdf`);
            } catch (error) {
                console.error('PDF Generation Error:', error);
                alert('Failed to generate PDF: ' + error.message);
            }
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {
                if (form.querySelector('input[name="delete_id"]')) {
                    console.log('Deleting ID:', form.querySelector('input[name="delete_id"]').value);
                    console.log('Delete from popup:', form.querySelector('input[name="delete_from_popup"]').value);
                }
            });
        });
        document.addEventListener("DOMContentLoaded", function () {
            const importExcel = document.getElementById("import_excel");
            const fileNameLabel = document.getElementById("file-name-label");

            importExcel.addEventListener("change", function () {
                if (this.files && this.files[0]) {
                    fileNameLabel.textContent = this.files[0].name;
                } else {
                    fileNameLabel.textContent = "";
                }
            });
        });
    </script>
</body>

</html>