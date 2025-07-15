<?php
// Start the session at the very beginning
session_start();

// Check if the lecturer is logged in
if (!isset($_SESSION['lecturer_id'])) {
    header("Location: index.php"); 
    exit();
}

// Check if module_id is set in the URL
if (!isset($_GET['module_id']) || empty($_GET['module_id'])) {
    die("Error: No module selected.");
}

$module_id = $_GET['module_id']; 

include('connection.php');

// Fetch the module name 
$query_module = "SELECT * FROM modules WHERE ModuleID = '$module_id'";
$module_result = $conn->query($query_module);
$module = $module_result->fetch_assoc();

// Initialize where_clauses for attendance filtering
$where_clauses = [];

// If filters are set, add them to the query
if (isset($_GET['search_date']) && !empty($_GET['search_date'])) {
    $date = $_GET['search_date'];
    $where_clauses[] = "sessions.SessionDate = '$date'";
}
if (isset($_GET['student_name']) && !empty($_GET['student_name'])) {
    $student_name = $_GET['student_name'];
    $where_clauses[] = "attendance.StudentName LIKE '%$student_name%'";
}
if (isset($_GET['student_number']) && !empty($_GET['student_number'])) {
    $student_number = $_GET['student_number'];
    $where_clauses[] = "attendance.StudentNumber LIKE '%$student_number%'";
}

// Construct the query with filters
$where_sql = '';
if (count($where_clauses) > 0) {
    $where_sql = ' AND ' . implode(' AND ', $where_clauses);
}

$query_attendance = "SELECT attendance.AttendanceID, attendance.StudentName, attendance.StudentNumber, 
                            sessions.SessionDate, attendance.Timestamp
                     FROM attendance
                     INNER JOIN sessions ON attendance.SessionID = sessions.SessionID
                     WHERE attendance.ModuleID = '$module_id' $where_sql
                     ORDER BY sessions.SessionDate DESC";

$attendance_result = $conn->query($query_attendance);

// Generate a dynamic file name based on filters
$filename_base = $module['ModuleName'];
if (isset($_GET['search_date']) && !empty($_GET['search_date'])) {
    $filename_base .= "_Date_" . $_GET['search_date'];
}
if (isset($_GET['student_name']) && !empty($_GET['student_name'])) {
    $filename_base .= "_Student_" . $_GET['student_name'];
}
if (isset($_GET['student_number']) && !empty($_GET['student_number'])) {
    $filename_base .= "_StudentNum_" . $_GET['student_number'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($module['ModuleName']); ?> - Attendance Report</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>

.main-content {
            padding: 20px;
            max-width: 100%;
        }

        h2 {
            color: #000000;
            margin-bottom: 20px;
        }

        .filter-container {
        display: flex;
        align-items: center; 
        justify-content: flex-start; 
        gap: 15px; 
    }

    /* Align input and button in a row */
    .input-button-container {
        display: flex;
        align-items: center;  
        justify-content: space-between; 
        gap:15px; 
    }

    /* Style for input and button */
    .input-button-container input {
        padding: 10px; 
        font-size: 14px;
        height: 40px; 
        width: 220px; 
        box-sizing: border-box; 
        line-height: 1.2;
        
    }

    .input-button-container button {
        padding: 10px 15px;
        cursor: pointer;
        font-size: 14px;
        height: 40px;
        border: none;
        border-radius: 5px;
        display: flex;
        align-items: center; 
        justify-content: center;
        margin-top:2px;
    }

        /* Export button form styling */
        #export-form {
            margin-top: 0px;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        #export-form button {
            padding: 10px 15px;
            font-size: 14px;
            
        }
    </style>

</head>
<body>

    <div class="sidebar">
        <h2 style="color:#ffffff">QR Attendance</h2>
        <a href="lecturer_dashboard.php">Home</a>
        <a href="add_module.php">Add Module</a>
        <a href="Module_page.php?module_id=<?php echo htmlspecialchars($module_id); ?>">Module</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <!-- Report Page Layout -->
    <div class="main-content">

  
        <h2>Attendance Report for: <?php echo htmlspecialchars($module['ModuleName']); ?></h2>

        <div class="filter-container">
        <form method="GET" action="" id="attendance-filter-form">
            <input type="hidden" name="module_id" value="<?php echo htmlspecialchars($module_id); ?>">
            <div class="input-button-container">

            
            <input type="date" id="search_date" name="search_date" value="<?php echo htmlspecialchars($_GET['search_date'] ?? ''); ?>">

            <input type="text" id="student_name" name="student_name" value="<?php echo htmlspecialchars($_GET['student_name'] ?? ''); ?>" placeholder="Filter by student name">

            <input type="text" id="student_number" name="student_number" value="<?php echo htmlspecialchars($_GET['student_number'] ?? ''); ?>" placeholder="Filter by Student Number">

            <button type="submit">Filter</button>
    </div>
        </form>
  

        <!-- Export Button Form -->
         
        <form method="GET" action="download_filter.php" id="export-form">
        <div class="input-button-container">
            <input type="hidden" name="module_id" value="<?php echo htmlspecialchars($module_id); ?>">
            <input type="hidden" name="search_date" value="<?php echo htmlspecialchars($_GET['search_date'] ?? ''); ?>">
            <input type="hidden" name="student_name" value="<?php echo htmlspecialchars($_GET['student_name'] ?? ''); ?>">
            <input type="hidden" name="student_number" value="<?php echo htmlspecialchars($_GET['student_number'] ?? ''); ?>">
            <button type="submit" name="export" value="pdf">Download PDF</button>
            <button type="submit" name="export" value="excel">Download Excel</button>
    </div>
        </form>
        </div>

        <!-- Attendance List -->
        <h3>Attendance Records</h3>
        <table>
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Student Number</th>
                    <th>Attendance Date</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($attendance = $attendance_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($attendance['StudentName']); ?></td>
                        <td><?php echo htmlspecialchars($attendance['StudentNumber']); ?></td>
                        <td><?php echo htmlspecialchars($attendance['SessionDate']); ?></td>
                        <td><?php echo htmlspecialchars($attendance['Timestamp']); ?></td> <!-- Display Timestamp -->
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <script>
    // Check if the page is being reloaded
    if (performance.navigation.type === 1) {
        // Reload page without any filters (keep only module_id)
        window.location.href = window.location.pathname + "?module_id=<?php echo htmlspecialchars($module_id); ?>";
    }
</script>
</body>
</html>

