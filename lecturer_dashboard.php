<?php
session_start();
include 'connection.php';

// Redirect if not logged in
if (!isset($_SESSION['lecturer_id'])) {
    header("Location: index.php");
    exit();
}

$lecturer_id = $_SESSION['lecturer_id'];
$name = $_SESSION['name'];

// Fetch Modules for Lecturer
$modules_query = "SELECT * FROM Modules WHERE LecturerID = ?";
$stmt = $conn->prepare($modules_query);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$modules = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h2>QR Attendance</h2>
        <a href="lecturer_dashboard.php">Home</a>
        <a href="add_module.php">Add Module</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="header">
    <h1>Welcome, <?php echo $name; ?>!</h1>
        </div>

    <!-- Main Content -->
    <div class="main-content">

        <h2 style="color:#000000;";>Your Modules</h2>

        <div class="module-container">

  
            <?php while ($module = $modules->fetch_assoc()): ?>
                <a href="module_page.php?module_id=<?php echo $module['ModuleID']; ?>" class="module-box">
                    <h3><?php echo $module['ModuleName']; ?></h3>
                    <p>View & Download Attendance</p>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
