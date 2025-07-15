<?php
include('connection.php');

$password = password_hash('admin123', PASSWORD_DEFAULT);
$conn->query("UPDATE admins SET Password='$password' WHERE Email='admin@example.com'");
echo "Admin password hashed successfully.";
?>
