<?php
session_start();
include('connection.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Check if the lecturer ID is set in the URL
if (isset($_GET['id'])) {
    $lecturer_id = $_GET['id'];

    // Prepare delete statement
    $query = "DELETE FROM lecturers WHERE LecturerID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $lecturer_id);

    if ($stmt->execute()) {
        // Redirect back to admin dashboard with success message
        header("Location: admin_dashboard.php?success=Lecturer deleted successfully");
        exit();
    } else {
        // Redirect back with an error message
        header("Location: admin_dashboard.php?error=Failed to delete lecturer");
        exit();
    }
} else {
    // Redirect if no ID is provided
    header("Location: admin_dashboard.php");
    exit();
}
?>
