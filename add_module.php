<?php
session_start();
include 'connection.php';

// Redirect if not logged in
if (!isset($_SESSION['lecturer_id'])) {
    header("Location: index.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lecturer_id = $_SESSION['lecturer_id'];
    $module_name = trim($_POST['module_name']);

    if (!empty($module_name)) {
        // Check if module already exists for the lecturer
        $check_query = "SELECT ModuleID FROM Modules WHERE ModuleName = ? AND LecturerID = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("si", $module_name, $lecturer_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Module already exists
            $message = "This module already exists.";
        } else {
            // Insert new module
            $insert_query = "INSERT INTO Modules (ModuleName, LecturerID) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("si", $module_name, $lecturer_id);

            if ($stmt->execute()) {
                $module_id = $stmt->insert_id;
                header("Location: module_page.php?module_id=" . $module_id);
                exit();
            } else {
                $message = "Error adding module.";
            }
        }
    } else {
        $message = "Please enter a module name.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Module</title>
    <link rel="stylesheet" href="style.css">

    <style>
    input, button {
        width: 100%;
        padding: 10px;
        font-size: 16px;
        box-sizing: border-box;
        display: block;
    }
    
    button {
        background-color: #259B45;
        color: white;
        border: none;
        cursor: pointer;
        text-align: center;
        width: 94%;
    }

    button:hover {
        background-color: #25559D;
    }

    .main-content {
        width: calc(100% - 270px - 10px);
        margin-left: 270px; 
    }
</style>
</head>
<body>
    <div class="sidebar">
        <h2>QR Attendance</h2>
        <a href="lecturer_dashboard.php">Home</a>
        <a href="add_module.php" class="active">Add Module</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="main-content">
        <h1>Add a New Module</h1>
        <form method="POST" class="form-group">
            <label>Module Name:</label>
            <input type="text" name="module_name" required>
            <button type="submit">Add Module</button>
        </form>

        <?php if (!empty($message)): ?>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
