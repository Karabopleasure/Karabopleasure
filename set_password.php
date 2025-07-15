<?php
session_start();
include('connection.php'); // Database connection file

// Check if token is provided in the URL
if (!isset($_GET['token']) || empty($_GET['token'])) {
    die("Invalid or missing token.");
}

$token = $_GET['token'];

// Check if the token exists in the database
$query = $conn->prepare("SELECT * FROM Lecturers WHERE InviteToken = ?");
$query->bind_param("s", $token);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    die("Invalid or expired token.");
}

$lecturer = $result->fetch_assoc();
$email = $lecturer['Email'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Password validation
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Update lecturer record with new password and remove invite token
        $update_query = $conn->prepare("UPDATE Lecturers SET Password = ?, InviteToken = NULL WHERE Email = ?");
        $update_query->bind_param("ss", $hashed_password, $email);

        if ($update_query->execute()) {
            $success = "Password set successfully! You can now <a href='index.php'>log in</a>.";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <h2>Set Your Password</h2>

    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <?php if (isset($success)) echo "<p style='color:green;'>$success</p>"; ?>

    <form method="POST">
        <label for="password">New Password:</label>
        <input type="password" name="password" id="password" required placeholder="Enter new password">
        
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirm new password">
        
        <button type="submit">Set Password</button>
    </form>
</div>

</body>
</html>
