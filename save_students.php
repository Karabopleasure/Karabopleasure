<?php
session_start();
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

include 'connection.php';

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo "Direct access not allowed.";
    exit;
}

$file = $_POST['file'];
$lecturer_id = $_POST['lecturer_id'];
$module_id = $_POST['module_id'];
$name_cols = $_POST['name_col']; // Now this is an array
$number_col = $_POST['number_col'];

$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

$inserted = 0;
$skipped = 0;

$check_stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE StudentNumber = ? AND ModuleID = ? AND LecturerID = ?");
$insert_stmt = $conn->prepare("INSERT INTO students (StudentName, StudentNumber, LecturerID, ModuleID) VALUES (?, ?, ?, ?)");

foreach ($rows as $i => $row) {
    if ($i === 1) continue; // Skip header row

    // Combine multiple name columns into one full name
    $name_parts = [];
    foreach ($name_cols as $col) {
        $part = trim($row[$col] ?? '');
        if (!empty($part)) {
            $name_parts[] = $part;
        }
    }
    $name = implode(' ', $name_parts); // Join name parts with a space

    $number = trim($row[$number_col] ?? '');

    if (!empty($name) && !empty($number)) {
        // Check for duplicates
        $check_stmt->bind_param("sii", $number, $module_id, $lecturer_id);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->reset();

        if ($count == 0) {
            $insert_stmt->bind_param("ssii", $name, $number, $lecturer_id, $module_id);
            if ($insert_stmt->execute()) {
                $inserted++;
            }
        } else {
            $skipped++;
        }
    }
}

// Send response for JavaScript alert
$response = "$inserted student(s) successfully added.";
if ($skipped > 0) {
    $response .= " $skipped duplicate student(s) skipped.";
}

echo $response;
