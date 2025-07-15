<?php
session_start();
include 'connection.php'; // Database connection
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // PHPMailer

// Load SMTP settings from a configuration file
$smtpConfig = parse_ini_file('smtp_config.ini', true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    // Check if email exists
    $query = "SELECT * FROM Lecturers WHERE Email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $lecturer = $result->fetch_assoc();

    if ($lecturer) {
        $token = bin2hex(random_bytes(50)); // Secure token
        $expires = date("Y-m-d H:i:s", strtotime('+1 hour')); // Expiry time

        // Store token in the database
        $insertQuery = "UPDATE Lecturers SET ResetToken = ?, ResetTokenExpiry = ? WHERE Email = ?";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("sss", $token, $expires, $email);
        $stmt->execute();

        // Send email
        $mail = new PHPMailer(true);

        try {
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = $smtpConfig['smtp']['host']; 
            $mail->SMTPAuth = true;
            $mail->Username = $smtpConfig['smtp']['username'];
            $mail->Password = $smtpConfig['smtp']['password'];
            $mail->SMTPSecure = $smtpConfig['smtp']['encryption'];
            $mail->Port = $smtpConfig['smtp']['port'];

            // Email Content
            $mail->setFrom($smtpConfig['smtp']['from_email'], $smtpConfig['smtp']['from_name']);
            $mail->addAddress($email);
            $mail->Subject = "Password Reset Request";
            $mail->Body = "Click the link to reset your password: http://kesho.co.za/reset_password.php?token=$token";

            $mail->send();
            $_SESSION['message'] = "Password reset link sent to your email.";
        } catch (Exception $e) {
            $_SESSION['message'] = "Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $_SESSION['message'] = "Email not found!";
    }
}
header("Location: index.php");
exit();
?>
