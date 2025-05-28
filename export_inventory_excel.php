<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Database connection
$host = 'localhost';
$dbname = 'clinic_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}

// Fetch inventory data
$stmt = $pdo->query("SELECT name, quantity, remaining_items FROM inventory ORDER BY name ASC");
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();

// Add data to the spreadsheet
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Name');
$sheet->setCellValue('B1', 'Quantity');
$sheet->setCellValue('C1', 'Remaining Items');

$row = 2;
foreach ($inventory as $item) {
    $sheet->setCellValue('A' . $row, $item['name']);
    $sheet->setCellValue('B' . $row, $item['quantity']);
    $sheet->setCellValue('C' . $row, $item['remaining_items']);
    $row++;
}

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="inventory.xlsx"');
header('Cache-Control: max-age=0');

// Output the Excel file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
