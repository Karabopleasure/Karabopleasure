<?php
session_start();
include 'connection.php'; // Database connection

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Validate token
    $query = "SELECT * FROM Lecturers WHERE ResetToken = ? AND ResetTokenExpiry > NOW()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $lecturer = $result->fetch_assoc();

    if (!$lecturer) {
        die("Invalid or expired token.");
    }
} else {
    die("No token provided.");
}

// Handle password reset form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Update password
    $updateQuery = "UPDATE Lecturers SET PasswordHash = ?, ResetToken = NULL, ResetTokenExpiry = NULL WHERE ResetToken = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ss", $newPassword, $token);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Password reset successful! You can now log in.";
        header("Location: login.php");
        exit();
    } else {
        $_SESSION['message'] = "Error resetting password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
</head>
<body>
    <h2>Reset Your Password</h2>
    <form method="POST">
        <label for="password">New Password:</label>
        <input type="password" name="password" required>
        <button type="submit">Reset Password</button>
    </form>
</body>
</html>
