<?php
session_start();
include('connection.php');

// Check if admin is logged in
if (!isset($_SESSION['lecturer_id'])) {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Check if the student ID is set in the URL
if (isset($_GET['id'])) {
    $student_id = $_GET['id'];

    // Prepare delete statement
    $query = "DELETE FROM students WHERE StudentID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {
        // Redirect back to classlist page with success message
        header("Location: class_list.php?success=Student deleted successfully");
        exit();
    } else {
        // Redirect back with an error message
        header("Location: class_list.php?error=Failed to delete student");
        exit();
    }
} else {
    // Redirect if no ID is provided
    header("Location: class_list.php");
    exit();
}
?>
