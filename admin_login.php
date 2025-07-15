<?php
session_start();

// Hardcoded admin credentials
$admin_email = "admin@example.com";
$admin_password = "Admin@123"; // You can change this

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Check if entered credentials match
    if ($email === $admin_email && $password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin_dashboard.php"); // Redirect to admin panel
        exit();
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

</head>
<body>
    <h2 style ="color:#000000;">Admin Login</h2>
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

    
    <form method="POST">
    <label for="email">Admin Email:</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email" aria-label="Email Address">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password" aria-label="Password">
                <button type="submit" name="login">Log In</button>
    </form>

</body>
</html>
