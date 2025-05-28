<?php
session_start();
session_regenerate_id(true);

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Check admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

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

// Handle item deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['item_id'])) {
    $item_id = (int) $_POST['item_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->execute([$item_id]);
        $_SESSION['message'] = "Item removed successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error removing item: " . $e->getMessage();
    }
    header("Location: admin_inventory.php");
    exit();
}

// Handle form submission for adding/updating an item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item-name'])) {
    $itemName = trim($_POST['item-name']);
    $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
    $remainingItems = (int) $_POST['remaining-items'];

    $itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : null;

    if (empty($itemName) || $quantity < 0 || $remainingItems < 0) {
        $_SESSION['error'] = 'Invalid input values. Item name cannot be empty, and quantity/remaining items must be 0 or greater.';
        header("Location: admin_inventory.php");
        exit();
    }

    try {
        if ($itemId) {
            // Editing an existing item
            $checkStmt = $pdo->prepare("SELECT id FROM inventory WHERE name = ? AND id != ?");
            $checkStmt->execute([$itemName, $itemId]);
            if ($checkStmt->fetch()) {
                $_SESSION['error'] = "Another item with the name '$itemName' already exists.";
                header("Location: admin_inventory.php");
                exit();
            }

            if ($remainingItems > 1000) {
                $_SESSION['error'] = "Cannot update $itemName. Total remaining items cannot exceed 1000.";
                header("Location: admin_inventory.php");
                exit();
            }

            $updateStmt = $pdo->prepare("UPDATE inventory SET name = ?, quantity = ?, remaining_items = ? WHERE id = ?");
            $updateStmt->execute([$itemName, $quantity, $remainingItems, $itemId]);
            $_SESSION['message'] = "Item updated successfully.";
        } else {
            // Adding a new item
            $stmt = $pdo->prepare("SELECT * FROM inventory WHERE name = ?");
            $stmt->execute([$itemName]);
            $existing = $stmt->fetch();

            if ($existing) {
                $newRemaining = $existing['remaining_items'] + $remainingItems;
                if ($newRemaining > 1000) {
                    $_SESSION['error'] = "Cannot add $remainingItems to $itemName. Total remaining items would exceed 1000.";
                    header("Location: admin_inventory.php");
                    exit();
                }
                $updateStmt = $pdo->prepare("UPDATE inventory SET remaining_items = ? WHERE id = ?");
                $updateStmt->execute([$newRemaining, $existing['id']]);
                $_SESSION['message'] = "Item quantity updated successfully.";
            } else {
                if ($remainingItems > 1000) {
                    $_SESSION['error'] = "Cannot add $itemName with $remainingItems items. Total must not exceed 1000.";
                    header("Location: admin_inventory.php");
                    exit();
                }
                $insertStmt = $pdo->prepare("INSERT INTO inventory (name, quantity, remaining_items) VALUES (?, ?, ?)");
                $insertStmt->execute([$itemName, 0, $remainingItems]);
                $_SESSION['message'] = "Item added successfully.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    header("Location: admin_inventory.php");
    exit();
}

// Handle form submission for clearing the inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear'])) {
    try {
        $clearStmt = $pdo->prepare("DELETE FROM inventory");
        $clearStmt->execute();
        $_SESSION['message'] = "Inventory cleared successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error clearing inventory: " . $e->getMessage();
    }
    header("Location: admin_inventory.php");
    exit();
}

// Handle form submission for importing from Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel-file'])) {
    if ($_FILES['excel-file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['excel-file']['tmp_name'];
        $fileName = $_FILES['excel-file']['name'];
        $uploadFileDir = './uploaded_files/';
        $destPath = $uploadFileDir . $fileName;
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            try {
                $reader = IOFactory::createReader('Xlsx');
                $spreadsheet = $reader->load($destPath);
                $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

                $itemsToProcess = [];
                $errors = [];
                $processedCount = 0;
                $skippedCount = 0;

                foreach ($sheetData as $rowIndex => $row) {
                    if ($rowIndex == 1 || empty(trim($row['A'] ?? ''))) {
                        continue;
                    }
                    $itemName = trim($row['A']);
                    $quantity = (int) trim($row['B'] ?? 0);
                    $remainingItems = (int) trim($row['C'] ?? 0);

                    if (empty($itemName)) {
                        $errors[] = "Row $rowIndex: Item name is empty.";
                        continue;
                    }
                    if ($quantity < 0 || $remainingItems < 0) {
                        $errors[] = "Row $rowIndex: Quantity/Remaining items for '$itemName' must be 0 or greater.";
                        continue;
                    }

                    $itemsToProcess[] = ['name' => $itemName, 'quantity' => $quantity, 'remaining' => $remainingItems, 'row' => $rowIndex];
                }

                $pdo->beginTransaction();
                try {
                    foreach ($itemsToProcess as $item) {
                        $itemName = $item['name'];
                        $quantity = $item['quantity'];
                        $remainingItems = $item['remaining'];
                        $rowIndex = $item['row'];

                        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE name = ?");
                        $stmt->execute([$itemName]);
                        $existing = $stmt->fetch();

                        if ($existing) {
                            $newRemaining = $existing['remaining_items'] + $remainingItems;
                            if ($newRemaining > 1000) {
                                $errors[] = "Row $rowIndex: Adding $remainingItems to '$itemName' exceeds limit (1000). Skipped.";
                                $skippedCount++;
                                continue;
                            }
                            $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = ?, remaining_items = ? WHERE id = ?");
                            $updateStmt->execute([$quantity, $newRemaining, $existing['id']]);
                            $processedCount++;
                        } else {
                            if ($remainingItems > 1000) {
                                $errors[] = "Row $rowIndex: Initial quantity for '$itemName' ($remainingItems) exceeds limit (1000). Skipped.";
                                $skippedCount++;
                                continue;
                            }
                            $insertStmt = $pdo->prepare("INSERT INTO inventory (name, quantity, remaining_items) VALUES (?, ?, ?)");
                            $insertStmt->execute([$itemName, $quantity, $remainingItems]);
                            $processedCount++;
                        }
                    }
                    $pdo->commit();
                    $_SESSION['message'] = "Excel file processed. $processedCount items added/updated.";
                    if ($skippedCount > 0) {
                        $_SESSION['message'] .= " $skippedCount items skipped (limit exceeded).";
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "Database error during import: " . $e->getMessage();
                    $errors[] = "Import failed due to database error.";
                }

                if (!empty($errors)) {
                    $_SESSION['error'] = (isset($_SESSION['error']) ? $_SESSION['error'] . "<br>" : "") . "Import issues:<br>" . implode("<br>", $errors);
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Error importing Excel file: " . $e->getMessage();
            } finally {
                if (isset($destPath) && file_exists($destPath)) {
                    unlink($destPath);
                }
            }
        } else {
            $_SESSION['error'] = "There was an error moving the uploaded file.";
        }
    } else {
        $_SESSION['error'] = "Error uploading file. Code: " . $_FILES['excel-file']['error'];
    }
    header("Location: admin_inventory.php");
    exit();
}

// Pagination logic
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$itemsPerPage = 5;
$offset = ($page - 1) * $itemsPerPage;
$searchTerm = "%" . (isset($_GET['search']) ? $_GET['search'] : "") . "%";

$stmt = $pdo->prepare("SELECT id, name, quantity, remaining_items FROM inventory WHERE name LIKE ? ORDER BY name ASC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $searchTerm, PDO::PARAM_STR);
$stmt->bindValue(2, $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE name LIKE ?");
$totalStmt->execute([$searchTerm]);
$totalCount = $totalStmt->fetchColumn();
$totalPages = ceil($totalCount / $itemsPerPage);

$stmt = $pdo->query("SELECT name, quantity, remaining_items FROM inventory ORDER BY name ASC");
$inventoryForPdf = $stmt->fetchAll(PDO::FETCH_NUM);

function generatePagination($page, $totalPages, $searchParam)
{
    $pagination = '';
    if ($page > 1) {
        $pagination .= '<a href="admin_inventory.php?' . $searchParam . 'page=' . ($page - 1) . '" class="btn previous">PREVIOUS</a>';
    }
    if ($totalPages <= 5) {
        for ($i = 1; $i <= $totalPages; $i++) {
            $pagination .= '<a href="admin_inventory.php?' . $searchParam . 'page=' . $i . '" class="btn' . ($i == $page ? ' active' : '') . '">' . $i . '</a>';
        }
    } else {
        $pagination .= '<a href="admin_inventory.php?' . $searchParam . 'page=1" class="btn' . (1 == $page ? ' active' : '') . '">1</a>';
        if ($page > 3) {
            $pagination .= '<span class="ellipsis">...</span>';
        }
        $start = max(2, $page - 2);
        $end = min($totalPages - 1, $page + 2);
        for ($i = $start; $i <= $end; $i++) {
            $pagination .= '<a href="admin_inventory.php?' . $searchParam . 'page=' . $i . '" class="btn' . ($i == $page ? ' active' : '') . '">' . $i . '</a>';
        }
        if ($page < $totalPages - 2) {
            $pagination .= '<span class="ellipsis">...</span>';
        }
        $pagination .= '<a href="admin_inventory.php?' . $searchParam . 'page=' . $totalPages . '" class="btn' . ($totalPages == $page ? ' active' : '') . '">' . $totalPages . '</a>';
    }
    if ($page < $totalPages) {
        $pagination .= '<a href="admin_inventory.php?' . $searchParam . 'page=' . ($page + 1) . '" class="btn next">NEXT</a>';
    }
    return $pagination;
}

$searchParam = isset($_GET['search']) && $_GET['search'] !== '' ? 'search=' . urlencode($_GET['search']) . '&' : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Inventory Management</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="images/sti_logo.png" type="image/png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.24/jspdf.plugin.autotable.min.js"></script>
    <style>
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

        /* Inventory Form Inputs */
        .inventory-form input[type="text"],
        .inventory-form input[type="number"],
        .inventory-form select {
            width: 100%;
            max-width: 462px;
            padding: 12.32px;
            border: 1.54px solid #d9d9d9;
            border-radius: 7.7px;
            box-sizing: border-box;
            font-size: 21.56px;
            height: 58.52px;
        }

        /* Excel Import Input */
        .excel-import input[type="file"] {
            width: 100%;
            max-width: 462px;
            padding: 12.32px;
            border: 1.54px solid #d9d9d9;
            border-radius: 7.7px;
            box-sizing: border-box;
            font-size: 21.56px;
            height: 58.52px;
        }

        /* Search Bar Input */
        .search-bar input[type="text"] {
            width: 100%;
            height: 58.52px;
            padding: 12.32px;
            border: 1.54px solid #d9d9d9;
            border-radius: 7.7px;
            box-sizing: border-box;
            font-size: 21.56px;
        }

        body.dark-mode .inventory-form,
        body.dark-mode .excel-import {
            background-color: #000000;
        }

        body.dark-mode .inventory-form input[type="text"],
        body.dark-mode .inventory-form input[type="number"],
        body.dark-mode .inventory-form select,
        body.dark-mode .excel-import input[type="file"],
        body.dark-mode .search-bar input[type="text"] {
            background-color: #000000;
            color: #e0e0e0;
            border: 1.54px solid #555;
            font-size: 21.56px;
            height: 58.52px;
            padding: 12.32px;
        }

        body.dark-mode .btn {
            background-color: #0056b3;
            color: #ffffff;
        }

        body.dark-mode .btn:hover {
            background-color: #004494;
        }

        body.dark-mode .download-pdf-btn {
            background-color: #1e1e1e;
            color: #05a3ca;
            border: 1px solid #05a3ca;
        }

        body.dark-mode .download-pdf-btn:hover {
            background-color: #05a3ca;
            color: #ffffff;
            border: 1px solid #05a3ca;
        }

        body.dark-mode .pagination a.btn {
            background-color: #333;
            color: #e0e0e0;
            border-color: #555;
        }

        body.dark-mode .pagination a.btn.active {
            background-color: #007bff;
            color: #fff;
            border-color: #007bff;
        }

        body.dark-mode .pagination a.btn.previous,
        body.dark-mode .pagination a.btn.next {
            background-color: #444;
            color: #fff;
            border-color: #444;
        }

        body.dark-mode .success-message {
            color: #28a745;
        }

        body.dark-mode .error-message {
            color: #dc3545;
        }

        body.dark-mode label {
            color: #e0e0e0;
        }

        body.dark-mode table {
            background-color: #777;
            border: 1px solid #ffffff;
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

        .edit-btn {
            background: #f1c40f;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 5px 10px;
            cursor: pointer;
            transition: background 0.3s ease;
            height: 52.36px;
            padding: 0 24.64px;
            border: none;
            border-radius: 7.7px;
            cursor: pointer;
            box-sizing: border-box;
            font-size: 21.56px;
        }

        body.dark-mode .edit-btn {
            background: #f1c40f;
        }

        body.dark-mode .edit-btn:hover {
            background: #e0a800;
        }

        .quantity-field {
            display: none;
        }

        .quantity-field.visible {
            display: block;
        }

        .btn,
        .btn-primary,
        .btn-secondary {
            margin-top: 10px;
            padding: 15.4px;
            font-size: 21.56px;
            border-radius: 7.7px;
        }

        .btn-secondary {
            width: 25%;
            background-color: #6c757d;
            color: #fff;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h2>Clinic Management</h2>
            <ul class="menu">
                <li><a href="admin_history.php">History</a></li>
                <li><a href="admin_loginsheet.php">Login Sheet</a></li>
                <li><a href="admin_inventory.php" class="active">Inventory</a></li>
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
            <h1>Admin: Inventory Management</h1>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="success-message">
                    <?= htmlspecialchars($_SESSION['message']) ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <?= nl2br(htmlspecialchars($_SESSION['error'])) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <div class="header-actions">
                <div class="search-bar">
                    <form method="GET" action="admin_inventory.php">
                        <input type="text" name="search" placeholder="Search item..."
                            value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                            <a href="admin_inventory.php">Reset Search</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="table-actions">
                    <form id="clear-table-form" method="POST" action="admin_inventory.php" style="display: inline;">
                        <input type="hidden" name="clear" value="1">
                        <button type="submit" class="clear-btn">Clear Inventory</button>
                    </form>
                    <button id="download-inventory-pdf" class="download-pdf-btn">Download PDF</button>
                    <button id="download-inventory-excel" class="download-excel-btn"
                        onclick="window.location.href='export_inventory_excel.php'">Download Excel</button>
                </div>
            </div>
            <table id="table-inventory">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Quantity</th>
                        <th>Remain Items</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 20px;">No items available.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= $item['remaining_items'] ?></td>
                                <td>
                                    <button class="action-btn edit-btn"
                                        onclick="populateEditForm(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)">Edit</button>
                                    <form class="remove-form" method="POST" action="admin_inventory.php"
                                        style="display: inline;">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="action-btn remove-btn">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($totalCount > 0 && $totalPages > 1): ?>
                <div class="pagination">
                    <?= generatePagination($page, $totalPages, $searchParam) ?>
                </div>
            <?php endif; ?>
            <div class="inventory-form">
                <h3 id="form-title">Add Inventory Item</h3>
                <form id="add-item-form" action="admin_inventory.php" method="POST"
                    onsubmit="return validateAddItemForm()">
                    <input type="hidden" id="item-id" name="item_id" value="">
                    <label for="item-name">Item Name</label>
                    <input type="text" id="item-name" name="item-name" required>
                    <div class="quantity-field" id="quantity-field">
                        <label for="quantity">Quantity</label>
                        <input type="number" id="quantity" name="quantity" min="0">
                    </div>
                    <label for="remaining-items">Remaining Items</label>
                    <input type="number" id="remaining-items" name="remaining-items" min="0" required>
                    <button type="submit" id="form-submit-btn" name="add_item" class="btn btn-primary w-100">Add
                        Item</button>
                    <button type="button" id="cancel-edit-btn" class="btn-secondary"
                        style="display: none; margin-top: 5px;" onclick="resetForm()">Cancel Edit</button>
                </form>
            </div>
            <div class="excel-import">
                <h3>Import from Excel</h3>
                <form method="POST" enctype="multipart/form-data" action="admin_inventory.php">
                    <label for="excel-file">Choose File (.xlsx)</label>
                    <input type="file" name="excel-file" id="excel-file" accept=".xlsx" required>
                    <button type="submit" class="btn btn-primary w-100">Upload Excel</button>
                </form>
            </div>
        </div>
    </div>
    <script>
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
        });
        const clearForm = document.getElementById('clear-table-form');
        if (clearForm) {
            clearForm.addEventListener('submit', function (event) {
                if (!confirm('Are you sure you want to clear the ENTIRE inventory? This action cannot be undone.')) {
                    event.preventDefault();
                }
            });
        }
        const removeForms = document.querySelectorAll('.remove-form');
        removeForms.forEach(form => {
            form.addEventListener('submit', function (event) {
                const row = form.closest('tr');
                const itemName = row ? row.cells[0].textContent : 'this item';
                if (!confirm(`Are you sure you want to remove ${itemName}?`)) {
                    event.preventDefault();
                }
            });
        });

        function validateAddItemForm() {
            const itemName = document.getElementById('item-name').value.trim();
            const quantityField = document.getElementById('quantity-field');
            const quantity = quantityField.classList.contains('visible') ? document.getElementById('quantity').value : 0;
            const remainingItems = document.getElementById('remaining-items').value;

            if (itemName === '') {
                alert('Item name cannot be empty.');
                return false;
            }
            if (quantityField.classList.contains('visible')) {
                if (quantity === '' || isNaN(quantity) || parseInt(quantity) < 0 || !Number.isInteger(parseFloat(quantity))) {
                    alert('Quantity must be a whole number (0 or greater).');
                    return false;
                }
            }
            if (remainingItems === '' || isNaN(remainingItems) || parseInt(remainingItems) < 0 || !Number.isInteger(parseFloat(remainingItems))) {
                alert('Remaining items must be a whole number (0 or greater).');
                return false;
            }
            return true;
        }

        const logoutLink = document.querySelector('.logout');
        if (logoutLink) {
            logoutLink.addEventListener('click', function (event) {
                event.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = this.href;
                }
            });
        }

        function populateEditForm(item) {
            document.getElementById('form-title').textContent = 'Edit Inventory Item';
            document.getElementById('item-id').value = item.id;
            document.getElementById('item-name').value = item.name;
            document.getElementById('quantity').value = item.quantity;
            document.getElementById('remaining-items').value = item.remaining_items;

            const quantityField = document.getElementById('quantity-field');
            quantityField.classList.add('visible');

            document.getElementById('form-submit-btn').textContent = 'Update Item';
            document.getElementById('cancel-edit-btn').style.display = 'block';
            document.getElementById('add-item-form').scrollIntoView({
                behavior: 'smooth'
            });
        }

        function resetForm() {
            document.getElementById('form-title').textContent = 'Add Inventory Item';
            document.getElementById('add-item-form').reset();
            document.getElementById('item-id').value = '';
            document.getElementById('form-submit-btn').textContent = 'Add Item';
            document.getElementById('cancel-edit-btn').style.display = 'none';
            document.getElementById('quantity-field').classList.remove('visible');
        }

        document.getElementById('download-inventory-pdf').addEventListener('click', function (e) {
            e.preventDefault();
            try {
                const {
                    jsPDF
                } = window.jspdf;
                const pdf = new jsPDF('portrait', 'mm', 'letter');
                pdf.setProperties({
                    title: 'Inventory List',
                    subject: 'Clinic Inventory',
                    author: 'STI Clinic System'
                });
                pdf.setFontSize(14);
                pdf.setFont("helvetica", "bold");
                pdf.text("STI Clinic Management System - Inventory List", pdf.internal.pageSize.getWidth() / 2, 15, {
                    align: 'center'
                });
                pdf.setFontSize(10);
                pdf.setFont("helvetica", "normal");
                const inventoryData = <?php echo json_encode($inventoryForPdf); ?>.map(item => [
                    item[0],
                    item[1],
                    item[2]
                ]);
                pdf.autoTable({
                    startY: 25,
                    head: [
                        ['Name', 'Quantity', 'Remain Items']
                    ],
                    body: inventoryData,
                    theme: 'grid',
                    styles: {
                        font: 'helvetica',
                        fontSize: 9,
                        cellPadding: 2,
                        overflow: 'linebreak',
                    },
                    headStyles: {
                        fillColor: [1, 35, 101],
                        textColor: 255,
                        fontStyle: 'bold',
                        fontSize: 9
                    },
                    columnStyles: {
                        0: {
                            cellWidth: 'auto'
                        },
                        1: {
                            cellWidth: 30
                        },
                        2: {
                            cellWidth: 35
                        }
                    },
                    didDrawPage: function (data) {
                        const pageCount = pdf.internal.getNumberOfPages();
                        pdf.setFontSize(8);
                        pdf.text(`Page ${data.pageNumber} of ${pageCount} - STI Clinic Management System`, pdf.internal.pageSize.getWidth() / 2, pdf.internal.pageSize.getHeight() - 10, {
                            align: 'center'
                        });
                    },
                    margin: {
                        top: 10,
                        bottom: 20,
                        left: 10,
                        right: 10
                    }
                });
                pdf.save(`inventory-${new Date().toISOString().slice(0, 10)}.pdf`);
            } catch (error) {
                console.error('PDF Generation Error:', error);
                if (error instanceof ReferenceError && error.message.includes("jspdf")) {
                    alert('Failed to generate PDF: jsPDF library not loaded correctly.');
                } else if (error.message.includes("autoTable")) {
                    alert('Failed to generate PDF: jsPDF-AutoTable plugin not loaded correctly.');
                } else {
                    alert('Failed to generate PDF: '.error.message);
                }
            }
        });

        const cancelBtn = document.getElementById('cancel-edit-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', resetForm);
        }
    </script>
</body>

</html>