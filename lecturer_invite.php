<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('connection.php'); 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Load PHPMailer

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);

    if (empty($name) || empty($email)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email already exists
        $check_query = $conn->prepare("SELECT * FROM Lecturers WHERE Email = ?");
        $check_query->bind_param("s", $email);
        $check_query->execute();
        $result = $check_query->get_result();
        $check_query->close();

        if ($result->num_rows > 0) {
            $error = "A lecturer with this email already exists.";
        } else {
            // Generate a unique invite token
            $token = bin2hex(random_bytes(32));

            // Insert into database
            $stmt = $conn->prepare("INSERT INTO Lecturers (Name, Email, InviteToken) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $token);
            
            if ($stmt->execute()) {
                $stmt->close();

                // Generate the invitation link
                $invite_link = "https://kesho.co.za/qrcodesystem/set_password.php?token=$token";

                // Email Subject & Body (Clickable Link)
                $subject = "Invitation to Join the Attendance Register System";
                $message = "
                    <p>Hello <strong>$name</strong>,</p>
                    <p>You have been invited to join the lecturer portal.</p>
                    <p>Click the link below to set up your password:</p>
                    <p><a href='$invite_link' style='color:blue; font-weight:bold;'>Set Up Your Password</a></p>
                    <p>This link will expire after first use.</p>
                    <p>Regards,<br><strong>Kesho Admin</strong></p>
                ";

                $mail = new PHPMailer(true);

                try {
                    // SMTP Configuration (Replace with your cPanel SMTP settings)
                    $mail->isSMTP();
                    $mail->Host = 'smtp.office365.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'karabo@kesho.co.za';
                    $mail->Password = 'Pleasure@2001';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    // Sender and Recipient
                    $mail->setFrom('your-email@yourdomain.com', 'Kesho Admin');
                    $mail->addAddress($email, $name); // Recipient

                    // Email Content
                    $mail->Subject = $subject;
                    $mail->isHTML(true);
                    $mail->Body = $message;

                    // Send Email
                    $mail->send();
                    $success = "Invitation sent successfully!";
                } catch (Exception $e) {
                    $error = "Mailer Error: {$mail->ErrorInfo}";
                    error_log("Mailer Error: " . $mail->ErrorInfo);
                }
            } else {
                $error = "Error inserting lecturer details.";
                error_log("Database error: " . $stmt->error);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invite Lecturer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="sidebar">
    <h2>QR Attendance</h2>
    <a href="admin_dashboard.php">Back to Dashboard</a>
    <a href="admin_logout.php" class="logout-btn">Logout</a>
</div>

<div class="container">
    <h2 style="color:#000000;">Invite Lecturer</h2>
    
    <?php if (isset($error)) echo "<p class='error message'>$error</p>"; ?>
    <?php if (isset($success)) echo "<p class='success message'>$success</p>"; ?>

    <form method="POST">
        <input type="text" name="name" placeholder="Lecturer Name" required>
        <input type="email" name="email" placeholder="Lecturer Email" required>
        <button type="submit">Send Invitation</button>
    </form>
</div>

</body>
</html>
