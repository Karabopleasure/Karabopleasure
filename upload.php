<?php
session_start();
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_file'])) {
    $lecturer_id = $_POST['LecturerID'];
    $module_id = $_POST['ModuleID'];
    $file = $_FILES['student_file'];

    // Validate file type
    $allowed_ext = ['xls', 'xlsx', 'pdf'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (!in_array($ext, $allowed_ext)) {
        die("Invalid file type.");
    }

    // Move file to uploads directory
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_path = $upload_dir . uniqid() . '_' . basename($file['name']);

    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Redirect to mapping page
        header("Location: map_columns.php?file=" . urlencode($file_path) . "&module_id=$module_id&lecturer_id=$lecturer_id");
        exit();
    } else {
        die("File upload failed.");
    }
}
?>
