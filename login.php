<?php
session_start();
include 'connection.php'; 


$error = "";
$success = "";

// Handle Lecturer Signup
if (isset($_POST['signup'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if email already exists
    $checkQuery = "SELECT * FROM Lecturers WHERE Email = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = "Email already exists!";
    } else {
        $query = "INSERT INTO Lecturers (Name, Email, PasswordHash) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $name, $email, $password);

        if ($stmt->execute()) {
            $success = "Account created! You can now log in.";
        } else {
            $error = "Error signing up.";
        }
    }
}

// Handle Lecturer Login
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $query = "SELECT * FROM Lecturers WHERE Email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $lecturer = $result->fetch_assoc();

    if ($lecturer && password_verify($password, $lecturer['PasswordHash'])) {
        $_SESSION['lecturer_id'] = $lecturer['LecturerID'];
        $_SESSION['name'] = $lecturer['Name'];

        header("Location: lecturer_dashboard.php");
        exit();
    } else {
        $error = "Invalid credentials!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Login & Signup - QR Attendance</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1 style="color:#259B45;";>QR Code Attendance System</h1>

        <!-- Login Form -->
        <div class="form-container" id="loginForm">
            <div class="form-header">Log In</div>
            <form method="POST" onsubmit="showLoadingSpinner(event)">
                <label for="email">Username (Email Address):</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email" aria-label="Email Address">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password" aria-label="Password">
                <button type="submit" name="login">Log In</button>
                <div class="loading-spinner" id="loginSpinner"></div>
            </form>
            <a href="javascript:void(0)" onclick="showForgotPasswordForm()">Forgot your password?</a>
            <a href="javascript:void(0)" onclick="showRegisterForm()">Create an account</a>
        </div>

         <!-- Forgot Password Form -->
         <div class="form-container" id="forgotPasswordForm">
            <div class="form-header">Forgot Your Password?</div>
            <form action="forgot_password.php" method="POST" onsubmit="showLoadingSpinner(event)">
                <label for="forgot-email">Email Address:</label>
                <input type="email" id="forgot-email" name="email" required placeholder="Enter your email" aria-label="Email Address">
                <button type="submit">Submit</button>
                <div class="loading-spinner" id="forgotPasswordSpinner"></div>
            </form>
            <a href="javascript:void(0)" onclick="showLoginForm()">Back to Login</a>
        </div>

        <!-- Register Form -->
        <div class="form-container" id="registerForm">
            <div class="form-header">Create an Account</div>
            <form method="POST" onsubmit="showLoadingSpinner(event)">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required placeholder="Enter your name" aria-label="Name">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email" aria-label="Email Address">
                <label for="register-password">Password:</label>
                <input type="password" id="register-password" name="password" required placeholder="Enter your password" aria-label="Password">
                <button type="submit" name="signup">Register</button>
                <div class="loading-spinner" id="registerSpinner"></div>
            </form>
            <div class="register-msg">Already have an account? <a href="javascript:void(0)" onclick="showLoginForm()">Log in</a></div>
        </div>
    </div>

    <script>
         function showLoginForm() {
            hideAllForms();
            document.getElementById('loginForm').style.display = 'flex';
        }
        function showRegisterForm() {
            hideAllForms();
            document.getElementById('registerForm').style.display = 'flex';
        }
        function showForgotPasswordForm() {
            hideAllForms();
            document.getElementById('forgotPasswordForm').style.display = 'flex';
        }
        function hideAllForms() {
            document.querySelectorAll('.form-container').forEach(form => form.style.display = 'none');
        }
        function showLoadingSpinner(event) {
            event.target.querySelector('.loading-spinner').style.display = 'block';
        }
        showLoginForm();
    </script>
</body>
</html>
