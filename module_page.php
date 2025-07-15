<?php
session_start();
include 'connection.php';
require 'phpqrcode/qrlib.php';

// Redirect if not logged in
if (!isset($_SESSION['lecturer_id'])) {
    header("Location: index.php");
    exit();
}

$lecturer_id = $_SESSION['lecturer_id'];
$module_id = $_GET['module_id'] ?? null;

if (!$module_id) {
    header("Location: lecturer_dashboard.php");
    exit();
}

// Fetch module details
$module_query = "SELECT ModuleName FROM Modules WHERE ModuleID = ? AND LecturerID = ?";
$stmt = $conn->prepare($module_query);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Database query preparation failed: " . $conn->error]);
    exit();
}
$stmt->bind_param("ii", $module_id, $lecturer_id);
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Database query execution failed: " . $stmt->error]);
    exit();
}
$stmt->bind_result($module_name);
$stmt->fetch();
$stmt->close();

if (isset($_GET['search_date'])) {
    $search_date = $_GET['search_date'];
    $formatted_search_date = date("Y-m-d", strtotime($search_date));
    
    // Adjust the query to search by date
    $sessions_query = "SELECT SessionID, SessionName, SessionDate FROM Sessions WHERE ModuleID = ? AND SessionDate = ? ORDER BY SessionDate DESC";
    $stmt = $conn->prepare($sessions_query);
    
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Database query preparation failed: " . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("is", $module_id, $formatted_search_date);
    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "message" => "Database query execution failed: " . $stmt->error]);
        exit();
    }

    $sessions_result = $stmt->get_result();
    $sessions = [];
    
    while ($session = $sessions_result->fetch_assoc()) {
        $sessions[] = $session;
    }

    // If no sessions are found, send a message back
    if (empty($sessions)) {
        echo json_encode(["success" => false, "message" => "No sessions found for the selected date."]);
        exit();
    }

    // Send the sessions back as JSON
    echo json_encode([
        "success" => true,
        "sessions" => $sessions
    ]);
    exit();
}

// Handle session addition (AJAX request)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['session_date'], $_POST['start_time'], $_POST['end_time'])) {
    $session_date = trim($_POST['session_date']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);

    if (!empty($session_date) && !empty($start_time) && !empty($end_time)) {
        // Combine date and time to create full datetime
        $start_datetime = $session_date . ' ' . $start_time;
        $end_datetime = $session_date . ' ' . $end_time;

        // Validate start and end time
        if (strtotime($end_datetime) <= strtotime($start_datetime)) {
            echo json_encode(["success" => false, "message" => "End time must be after start time."]);
            exit();
        }

        $session_name = $module_name . " - " . $session_date;

        // Check if session already exists for the same module and date
        $check_session_query = "SELECT SessionID FROM Sessions WHERE ModuleID = ? AND SessionDate = ? AND StartTime = ? AND EndTime = ?";
        $stmt = $conn->prepare($check_session_query);
        if (!$stmt) {
            echo json_encode(["success" => false, "message" => "Database query preparation failed: " . $conn->error]);
            exit();
        }
        $stmt->bind_param("isss", $module_id, $session_date, $start_datetime, $end_datetime);
        if (!$stmt->execute()) {
            echo json_encode(["success" => false, "message" => "Database query execution failed: " . $stmt->error]);
            exit();
        }
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Session already exists for this date
            echo json_encode(["success" => false, "message" => "This session already exists for the selected date and time."]);
            exit();
        }

        // Generate unique token for this session
        $token = bin2hex(random_bytes(16));  // Generates a random 16-byte token

        // Insert new session
        $insert_session_query = "INSERT INTO Sessions (SessionName, ModuleID, SessionDate, StartTime, EndTime) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_session_query);
        if (!$stmt) {
            echo json_encode(["success" => false, "message" => "Database query preparation failed: " . $conn->error]);
            exit();
        }
        $stmt->bind_param("sssss", $session_name, $module_id, $session_date, $start_datetime, $end_datetime);

        if (!$stmt->execute()) {
            echo json_encode(["success" => false, "message" => "Error adding session: " . $stmt->error]);
            exit();
        }

        $session_id = $stmt->insert_id;
        $qr_folder = "qrcodes/";

        // Ensure QR code directory exists
        if (!file_exists($qr_folder)) {
            if (!mkdir($qr_folder, 0777, true)) {
                echo json_encode(["success" => false, "message" => "Failed to create QR code folder."]);
                exit();
            }
        }

        // Store the token in the database with a 'used' flag (initially set to 0)
        $insert_token_query = "INSERT INTO QR_Tokens (SessionID, Token, Used) VALUES (?, ?, 0)";
        $stmt = $conn->prepare($insert_token_query);
        if (!$stmt) {
            echo json_encode(["success" => false, "message" => "Database query preparation failed: " . $conn->error]);
            exit();
        }
        $stmt->bind_param("is", $session_id, $token);
        if (!$stmt->execute()) {
            echo json_encode(["success" => false, "message" => "Failed to store token in the database."]);
            exit();
        }

        
        // Generate QR Code link with expiration time and token
        $qr_text = "http://localhost/QR_Code_System/student_form.php?session_id=" . $session_id . "&token=" . $token. "&start_time=" . urlencode($start_datetime) . "&end_time=" . urlencode($end_datetime);
        $qr_file = $qr_folder . "session_" . $session_id . ".png";

        // Generate QR Code
        QRcode::png($qr_text, $qr_file, QR_ECLEVEL_L, 10);



// Save QR code filename and link in database
$update_query = "UPDATE Sessions SET QRCodeFile=?, QRCodeLink=? WHERE SessionID=?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("ssi", $qr_file, $qr_text, $session_id);
$stmt->execute();

        // Return data as JSON (AJAX response)
        echo json_encode([
            "success" => true,
            "session_id" => $session_id,
            "session_name" => $session_name,
            "session_date" => $session_date,
            "start_time" => $start_datetime,
            "end_time" => $end_datetime,
            "qr_file" => $qr_file,
            "qr_text" => $qr_text
        ]);
        exit();
    } else {
        echo json_encode(["success" => false, "message" => "Please fill all the fields."]);
        exit();
    }



 // Query to fetch session data
 $query = "SELECT SessionID, SessionName, SessionDate FROM sessions WHERE SessionID = ?";
 $stmt = $mysqli->prepare($query);
 $stmt->bind_param("i", $session_id);
 $stmt->execute();
 $result = $stmt->get_result();

 if ($result->num_rows > 0) {
     $session = $result->fetch_assoc();
     // Return session details in JSON format
     echo json_encode([
         'success' => true,
         'session_id' => $session['SessionID'],
         'session_name' => $session['SessionName'],
         'session_date' => $session['SessionDate']
     ]);
 } else {
     // If no session found, return error
     echo json_encode(['success' => false, 'message' => 'Session not found.']);
     exit();
}
}
// Fetch previous sessions
$sessions_query = "SELECT SessionID, SessionName, SessionDate FROM Sessions WHERE ModuleID = ? ORDER BY SessionDate DESC";
$stmt = $conn->prepare($sessions_query);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Database query preparation failed: " . $conn->error]);
    exit();
}
$stmt->bind_param("i", $module_id);
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Database query execution failed: " . $stmt->error]);
    exit();
}
$sessions_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($module_name); ?> - Add Session</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        
        
        form {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            width: 350px;
        }
        input[type="date"], button {
            width: 96%;
            padding: 10px;
            margin-top: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            background: #259B45;
            color: white;
            cursor: pointer;
        }
        button:hover {
            background: #25559D;
        }
        .session-list {
            margin-top: 20px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }
        .session-item {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .session-item:last-child {
            border-bottom: none;
        }
        .qr-container {
            margin-top: 20px;
            text-align: center;
        }
        .qr-container img {
            width: 200px;
            height: 200px;
        }
        /* Session Grid */
        .session-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .session-box {
            background: #ffffff;
            color: black;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            transition: 0.3s;
        }
        .session-box:hover {
            background: #FE8900;
        }
        .session-box h3 {
            margin: 0;
            font-size: 18px;
        }
        .session-box p {
            font-size: 14px;
            margin-top: 5px;
        }
        .header-container {
        display: flex;
        align-items: center; 
        justify-content: flex-start; 
        gap: 20px; 
    }

    /* Align input and button in a row */
    .input-button-container {
        display: flex;
        align-items: center;  
        justify-content: space-between;  
    }

    /* Style for input and button */
    .input-button-container input {
        margin-right: 10px;  
        padding: 8px;
    }

    .input-button-container button {
        padding: 8px 15px;
        cursor: pointer;
    }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>QR Attendance</h2>
        <a href="lecturer_dashboard.php">Home</a>
        <a href="add_module.php">Add Module</a>
        <a href="report.php?module_id=<?php echo $module_id; ?>">Report</a> 
         <!-- new-->
        <a href="class_list.php?module_id=<?php echo $module_id; ?>">Add Class List</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="main-content">
        <h1><?php echo htmlspecialchars($module_name); ?></h1>
        <h2 style="color:#000000;">Add a Session & Generate QR Code</h2>
        
        <form id="add-session-form">
    <label>Lecture Date:</label>
    <input type="date" name="session_date" required>
    
    <label>Start Time:</label>
    <input type="time" name="start_time" required>
    
    <label>End Time:</label>
    <input type="time" name="end_time" required>

    <button type="submit">Generate QR Code</button>
</form>


        <p id="message"></p>

     <!-- QR Code Display -->
<div class="qr-container" id="qr-container" style="display:none;">
    <h3 id="qr-session-name"></h3>
    <img id="qr-image" src="" alt="QR Code">
    <p>Students can scan this QR code to submit their attendance.</p>
    <a id="qr-download" download><button>Download QR Code</button></a>
    <p><a id="qr-link" target="_blank">Open Form</a></p>
</div>

<div class="header-container">
    <h2 style="color:#000000;">Search Sessions by Date:</h2>
    <form method="GET" action="" id="search-form" class="search-form" >
      
        <div class="input-button-container">
            <input style="width: 850px;"; type="date" id="search_date" placeholder="Search Sessions by Date" name="search_date">
            <button type="submit">Search</button>
        </div>
    </form>
</div>

<div class="session-container" id="session-container">
    <?php while ($session = $sessions_result->fetch_assoc()): ?>
        <a href="view_attendance.php?session_id=<?php echo $session['SessionID']; ?>" class="session-box">
            <h3><?php echo htmlspecialchars($session['SessionName']); ?></h3>
            <p>View & Download Attendance</p>
        </a>
    <?php endwhile; ?>
</div>

<!-- Modal for Editing Session -->
<div id="edit-modal" style="display:none;">
    <div class="modal-content">
        <h3>Edit Session</h3>
        <form id="edit-session-form">
            <input type="hidden" id="session-id" name="session_id">
            <label for="edit-session-name">Session Name:</label>
            <input type="text" id="edit-session-name" name="session_name"><br><br>

            <label for="edit-session-date">Session Date:</label>
            <input type="datetime-local" id="edit-session-date" name="session_date"><br><br>

            <button type="submit">Update Session</button>
            <button type="button" onclick="closeEditModal()">Close</button>
        </form>
    </div>
</div>

    </div>

    <script>
     $(document).ready(function() {
            // Handle session addition
            $("#add-session-form").submit(function(event) {
                event.preventDefault(); // Prevent form from reloading the page

                // Clear previous messages and QR code
                $("#message").text(""); // Clear any previous message
                $("#qr-container").hide(); // Hide the QR code container
                $("#qr-image").attr("src", ""); // Clear the previous QR code image
                $("#qr-session-name").text(""); // Clear the previous QR session name

                $.ajax({
                    type: "POST",
                    url: "", // Submits to the same PHP file
                    data: $(this).serialize(),
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            // Update message for success
                            $("#message").text("Session added successfully! QR Code generated.");

                            // Show the QR code container
                            $("#qr-container").show();

                            // Display QR code
                            $("#qr-session-name").text("QR Code for " + response.session_name);
                            $("#qr-image").attr("src", response.qr_file);
                            $("#qr-download").attr("href", response.qr_file);
                            $("#qr-link").attr("href", response.qr_text);

                            // Append new session without reloading
                            $("#session-container").prepend(`
                                <a href="view_attendance.php?session_id=${response.session_id}" class="session-box">
                                    <h3>${response.session_name}</h3>
                                    <p>View & Download Attendance</p>
                                </a>
                            `);
                        } else {
                            // Display error message if session already exists
                            $("#message").text(response.message);
                        }
                    },
                    error: function() {
                        // Handle AJAX errors
                        $("#message").text("An error occurred while adding the session.");
                    }
                });
            });

            $(document).ready(function() {
    // Handle search form submission via AJAX
    $("#search-form").submit(function(event) {
        event.preventDefault(); // Prevent form reload
        const search_date = $("#search_date").val(); // Get search date from input
        
        $.ajax({
            type: "GET",
            url: "", // Current PHP file
            data: { search_date: search_date }, // Pass the search date
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    // Clear previous results
                    $("#session-container").html(''); 
                    
                    // Iterate through the response sessions and display them
                    response.sessions.forEach(session => {
                        $("#session-container").append(`
                            <div class="session-box" id="session-${session.SessionID}">
                                <a href="view_attendance.php?session_id=${session.SessionID}" class="session-link">
                                    <h3>${session.SessionName}</h3>
                                    <p>View & Download Attendance</p>
                                </a>
                                <!-- Edit Button, hidden by default -->
                                <button class="edit-session-btn" onclick="openEditModal(${session.SessionID})" style="display: inline-block;">Edit</button>
                            </div>
                        `);
                    });
                } else {
                    // Handle error
                    $("#message").text(response.message);
                }
            },
            error: function() {
                // Handle AJAX error
                $("#message").text("An error occurred while searching.");
            }
        });
    });

    // Handle the submission of the edit session form
    $("#edit-session-form").submit(function(event) {
        event.preventDefault(); // Prevent form reload
        
        $.ajax({
            type: "POST",
            url: "update_session.php", // The PHP script that updates the session
            data: $(this).serialize(),
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    // Update the session details in the UI
                    const sessionID = $("#session-id").val();
                    $(`#session-${sessionID} h3`).text(response.session_name);
                    $(`#session-${sessionID} p`).text("View & Download Attendance");

                    // Close the modal
                    closeEditModal();
                } else {
                    alert("Error updating session: " + response.message);
                }
            },
            error: function() {
                alert("An error occurred while updating the session.");
            }
        });
    });
});

// Open the edit modal with session data
function openEditModal(sessionID) {
    $.ajax({
        type: "GET",
        url: "", // Stay on the same page
        data: { action: "get_session", session_id: sessionID },
        dataType: "json",
        success: function(response) {
            if (response.success) {
                // Populate modal with session data
                $("#session-id").val(response.session_id);
                $("#edit-session-name").val(response.session_name);
                $("#edit-session-date").val(response.session_date);

                // Show the modal
                $("#edit-modal").show();
            } else {
                alert("Error fetching session data.");
            }
        },
        error: function() {
            alert("An error occurred while fetching session data.");
        }
    });
}


// Close the edit modal
function closeEditModal() {
    $("#edit-modal").hide();
}

     });


    </script>

</body>
</html>
