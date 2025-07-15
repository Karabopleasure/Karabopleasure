<?php
session_start();
include('connection.php');

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Fetch all lecturers
$query = "SELECT * FROM lecturers";
$result = $conn->query($query);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
      


        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: ##25559D;
            color: white;
        }

        tr:hover {
            background: #f1f1f1;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .edit-btn, .delete-btn {
            padding: 6px 10px;
            text-decoration: none;
            color: white;
            border-radius: 5px;
        }

        .edit-btn {
            background: #28a745;
        }

        .delete-btn {
            background: #dc3545;
        }

        .delete-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>QR Attendance</h2>
    <a href="lecturer_invite.php">Invite Lecturer</a>
    <a href="admin_logout.php" class="logout-btn">Logout</a>
</div>

<div class="main-content">
    <h2 style="color:#000000;">Welcome, Admin</h2>

    <h3>Lecturers List</h3>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['LecturerID']; ?></td>
                    <td><?= $row['Name']; ?></td>
                    <td><?= $row['Email']; ?></td>
                    <td class="action-buttons">
                        <a href="edit_lecturer.php?id=<?= $row['LecturerID']; ?>" class="edit-btn">Edit</a>
                        <a href="delete_lecturer.php?id=<?= $row['LecturerID']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this lecturer?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>
