<?php
session_start();
include 'connection.php';
require 'phpqrcode/qrlib.php';

if (!isset($_SESSION['lecturer_id'])) {
    header("Location: index.php");
    exit();
}

$lecturer_id = $_SESSION['lecturer_id'];
$message = "";

// Fetch all modules assigned to the lecturer
$modules_query = "SELECT * FROM Modules WHERE LecturerID = ?";
$stmt = $conn->prepare($modules_query);
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$modules_result = $stmt->get_result();

$module_id = null; // Ensure module_id is defined

// When a specific module is clicked
if (isset($_GET['module_id'])) {
    $module_id = $_GET['module_id'];

    // Fetch module details (name)
    $module_query = "SELECT ModuleName FROM Modules WHERE ModuleID = ?";
    $stmt = $conn->prepare($module_query);
    $stmt->bind_param("i", $module_id);
    $stmt->execute();
    $module_name_result = $stmt->get_result();
    $module_name = $module_name_result->fetch_assoc()['ModuleName'];

    // Fetch sessions for the module
    $sessions_query = "SELECT * FROM Sessions WHERE ModuleID = ? ORDER BY SessionDate DESC";
    $stmt = $conn->prepare($sessions_query);
    $stmt->bind_param("i", $module_id);
    $stmt->execute();
    $sessions_result = $stmt->get_result();
}

// When a session is selected
if (isset($_GET['session_id'])) {
    $session_id = $_GET['session_id'];

   // Fetch session details including QR code
$session_query = "SELECT s.SessionName, s.SessionDate, s.StartTime, s.EndTime, s.QRCodeFile, s.QRCodeLink, m.ModuleName 
FROM Sessions s 
JOIN Modules m ON s.ModuleID = m.ModuleID 
WHERE s.SessionID = ?";
$stmt = $conn->prepare($session_query);
$stmt->bind_param("i", $session_id);
$stmt->execute();
$session_result = $stmt->get_result();
$session_details = $session_result->fetch_assoc();

$session_name = $session_details['SessionName'];
$session_date = $session_details['SessionDate'];
$start_datetime = $session_details['StartTime'];
$end_datetime = $session_details['EndTime'];
$module_name = $session_details['ModuleName'];
$module_id = $session_details['ModuleID']; // Ensure module_id is set
$qr_file = $session_details['QRCodeFile']; // Fetch saved QR Code file
$qr_url = $session_details['QRCodeLink']; // Fetch saved QR Code link




    // Fetch attendance for the session
    $attendance_query = "SELECT * FROM Attendance WHERE SessionID = ? ORDER BY Timestamp DESC";
    $stmt = $conn->prepare($attendance_query);
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Attendance</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .session-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .session-box {
            background: #3498db;
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            transition: 0.3s;
        }
        .session-box:hover {
            background: #2980b9;
        }
        .session-box h3 {
            margin: 0;
            font-size: 18px;
        }
        .session-box p {
            font-size: 14px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>QR Attendance</h2>
        <a href="lecturer_dashboard.php">Home</a>
        <a href="add_module.php">Add Module</a>
        <a href="Module_page.php?module_id=<?php echo htmlspecialchars($module_id); ?>">Back to Module</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="main-content">
        <?php if (!isset($_GET['module_id']) && !isset($_GET['session_id'])): ?>
            <h3>Modules</h3>
            <ul>
                <?php while ($row = $modules_result->fetch_assoc()): ?>
                    <li><a href="view_attendance.php?module_id=<?php echo $row['ModuleID']; ?>"><?php echo htmlspecialchars($row['ModuleName']); ?></a></li>
                <?php endwhile; ?>
            </ul>
        <?php elseif (isset($_GET['module_id']) && !isset($_GET['session_id'])): ?>
            <h1>Sessions for <?php echo htmlspecialchars($module_name); ?></h1>
            <div class="session-container">
                <?php while ($session = $sessions_result->fetch_assoc()): ?>
                    <a href="view_attendance.php?session_id=<?php echo $session['SessionID']; ?>" class="session-box">
                        <h3><?php echo htmlspecialchars($session['SessionName']); ?></h3>
                        <p>Date: <?php echo htmlspecialchars($session['SessionDate']); ?></p>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php elseif (isset($_GET['session_id'])): ?>
            
<h1>Attendance for <?php echo htmlspecialchars($session_name); ?> 
    (<?php echo date("h:i A", strtotime($start_datetime)); ?> - <?php echo date("h:i A", strtotime($end_datetime)); ?>)
</h1>


            <!-- QR Code -->
            <h4>QR Code</h4>
<img src="<?php echo htmlspecialchars($qr_file); ?>" alt="QR Code" />
<p>
    <a href="<?php echo htmlspecialchars($qr_file); ?>" download>
        <button style="background:#25559D;">Download QR Code</button>
    </a>
</p>

<!-- Attendance Form Link -->
<a href="<?php echo htmlspecialchars($qr_url); ?>" target="_blank">Open Attendance Form</a>
            
            <h4>Attendance List</h4>
            <table border="1">
                <tr>
                    <th>Timestamp</th>
                    <th>Student Number</th>
                    <th>Student Name</th>
                </tr>
                <?php while ($attendance = $attendance_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($attendance['Timestamp']); ?></td>
                        <td><?php echo htmlspecialchars($attendance['StudentNumber']); ?></td>
                        <td><?php echo htmlspecialchars($attendance['StudentName']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
            <br>
            <a href="download_attendance.php?session_id=<?php echo $session_id; ?>&type=excel">
                <button class="btn excel">Download as Excel</button>
            </a>
            <a href="download_attendance.php?session_id=<?php echo $session_id; ?>&type=pdf">
                <button class="btn pdf">Download as PDF</button>
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
