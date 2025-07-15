student 
<?php
include 'connection.php';

session_start();

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$token = isset($_GET['token']) ? $_GET['token'] : "";
$message = "";

$module_id = 0;
$session_date = "";
$start_time = "";
$end_time = "";
$is_qr_scan = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'QR_Code') !== false;

// Get session details
if ($session_id > 0 && !empty($token)) {
    $token_query = "SELECT TokenID, SessionID, Used FROM QR_Tokens WHERE Token = ? AND SessionID = ?";
    $stmt = $conn->prepare($token_query);
    $stmt->bind_param("si", $token, $session_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['Used'] == 1) {
            $message = "This QR code has already been used.";
        } else {
            $session_query = "SELECT ModuleID, DATE(SessionDate) AS SessionDate, TIME(StartTime) AS StartTime, TIME(EndTime) AS EndTime 
                              FROM Sessions WHERE SessionID = ?";
            $stmt = $conn->prepare($session_query);
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $module_id = $row['ModuleID'];
                $session_date = $row['SessionDate'];
                $start_time = $row['StartTime'];
                $end_time = $row['EndTime'];
            } else {
                $message = "Session details not found.";
            }
            $stmt->close();
        }
    } else {
        $message = "Invalid or expired QR code.";
    }
} else {
    $message = "Invalid session or token.";
}

// Current date & time
date_default_timezone_set("Africa/Johannesburg");
$current_date = date("Y-m-d");
$current_time = date("H:i:s");

// Session expiration check
$expired = false;
if ($current_date !== $session_date || strtotime($current_time) < strtotime($start_time) || strtotime($current_time) > strtotime($end_time)) {
    $message = "Attendance submission is closed for this session.";
    $expired = true;
}

// Handle attendance submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$expired) {
    $student_name = trim($_POST['student_name']);
    $student_id = trim($_POST['student_id']);

    // Validate student number (9 digits only)
    if (!preg_match('/^\d{9}$/', $student_id)) {
        $message = "Error: Student number must be exactly 9 digits.";
    } elseif (!empty($student_name) && !empty($student_id) && $session_id > 0 && $module_id > 0) {
        // Check if the student has already recorded attendance for this session
        $check_query = "SELECT * FROM Attendance WHERE SessionID = ? AND StudentNumber = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("is", $session_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // If attendance has already been recorded for the student, show an error
            $message = "You have already submitted attendance for this session.";
        } else {
            // Insert into Attendance table with ModuleID
            $insert_query = "INSERT INTO Attendance (SessionID, ModuleID, StudentNumber, StudentName, Timestamp) 
                             VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("iiss", $session_id, $module_id, $student_id, $student_name);
        
            if ($stmt->execute()) {
                $message = "Attendance recorded successfully!";
            } else {
                $message = "Error submitting attendance.";
            }
        }
    } else {
        $message = "All fields are required.";
    
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Student Attendance Form</h1>

        <?php if (!empty($message)): ?>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <?php if (!$expired): ?>
            <form method="POST" id="attendance-form">
                <label>Student Name:</label>
                <input type="text" name="student_name" required>

                <label>Student Number:</label>
                <input type="text" name="student_id" id="student_id" oninput="validateStudentNumber(this)" required>

                <button type="submit" onclick="markAsSubmitted()">Submit Attendance</button>
            </form>
        <?php endif; ?>

        <?php if ($expired): ?>
            <p style="color: red; font-weight: bold;">This session has expired. Attendance is closed.</p>
        <?php endif; ?>
    </div>

    <script>
        function validateStudentNumber(input) {
            input.value = input.value.replace(/\D/g, '').slice(0, 9);
        }

        function markAsSubmitted() {
            const sessionId = "<?php echo $session_id; ?>";
            localStorage.setItem("submitted_session_" + sessionId, "1");
        }

        document.addEventListener("DOMContentLoaded", function () {
            const sessionId = "<?php echo $session_id; ?>";
            if (localStorage.getItem("submitted_session_" + sessionId)) {
                alert("You have already submitted attendance for this session.");
                document.getElementById("attendance-form").style.display = "none";
            }
        });
    </script>
</body>
</html>
