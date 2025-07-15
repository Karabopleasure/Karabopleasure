<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['lecturer_id'])) {
    header("Location: index.php");
    exit();
}

$lecturer_id = $_SESSION['lecturer_id'];
$module_id = $_GET['module_id'] ?? null;

// Fetch module details
$module_query = "SELECT ModuleName FROM Modules WHERE ModuleID = ? AND LecturerID = ?";
$stmt = $conn->prepare($module_query);
$stmt->bind_param("ii", $module_id, $lecturer_id);
$stmt->execute();
$stmt->bind_result($module_name);
$stmt->fetch();
$stmt->close();

// Check if editing
$edit_mode = false;
$edit_id = null;
$edit_student = [];

if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_mode = true;

    $stmt = $conn->prepare("SELECT * FROM students WHERE StudentID = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_student = $result->fetch_assoc();
}

// Handle Add Student
if (isset($_POST['add_student'])) {
    $name = $_POST['StudentName'];
    $student_number = $_POST['StudentNumber'];

    $stmt = $conn->prepare("INSERT INTO students (StudentName, StudentNumber, LecturerID, ModuleID) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $name, $student_number, $lecturer_id, $module_id);
    $stmt->execute();
}

// Handle Update Student
if (isset($_POST['update_student'])) {
    $id = $_POST['student_id'];
    $name = $_POST['StudentName'];
    $student_number = $_POST['StudentNumber'];

    $stmt = $conn->prepare("UPDATE students SET StudentName = ?, StudentNumber = ? WHERE StudentID = ?");
    $stmt->bind_param("ssi", $name, $student_number, $id);
    $stmt->execute();

    header("Location: class_list.php?module_id=$module_id");
    exit();
}

// Handle Delete
if (isset($_POST['delete_student'])) {
    $id = $_POST['delete_student'];
    $stmt = $conn->prepare("DELETE FROM students WHERE StudentID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: class_list.php?module_id=$module_id");
    exit();
}

if (isset($_POST['bulk_delete']) && !empty($_POST['delete_students'])) {
    $ids = $_POST['delete_students'];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("DELETE FROM students WHERE StudentID IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
}


// Fetch all students
$stmt = $conn->prepare("SELECT * FROM students WHERE LecturerID = ? AND ModuleID = ?");
$stmt->bind_param("ii", $lecturer_id, $module_id);
$stmt->execute();
$students = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class List</title>
    <link rel="stylesheet" href="style.css">
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

        .input-button-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap:15px;
        }

        .input-button-container input {
            padding: 10px;
            font-size: 14px;
            height: 40px;
            width: 300px;
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
        .btn {
    padding: 8px 12px;
    font-size: 13px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-align: center;
}

.btn-primary {
    background-color: #259B45;
    color: white;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-sm {
    font-size: 12px;
    padding: 6px 10px;
}

    </style>
</head>
<body class="p-4">
<div class="sidebar">
    <h2 style="color:#ffffff">QR Attendance</h2>
    <a href="lecturer_dashboard.php">Home</a>
    <a href="add_module.php">Add Module</a>
    <a href="report.php?module_id=<?php echo $module_id; ?>">Report</a> 
    <a href="Module_page.php?module_id=<?php echo htmlspecialchars($module_id); ?>">Back to Module</a>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<div class="main-content">
    <h2 style="color:#000000">Class List Manager</h2>

    <div class="filter-container">
        <form action="upload.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="LecturerID" value="<?= htmlspecialchars($lecturer_id) ?>">
            <input type="hidden" name="ModuleID" value="<?= htmlspecialchars($module_id) ?>">
            <label>Select student list (Excel):</label><br><br>
            <input type="file" name="student_file" accept=".xlsx,.xls,.pdf" required>
            <button type="submit">Upload</button>
        </form>
    </div>

    <br><br>

    <h4><?= $edit_mode ? "Edit Student" : "Add Student to the list" ?></h4>
    <div class="filter-container">
        <form method="POST" class="mb-4">
            <div class="input-button-container">
                <input type="text" name="StudentName" placeholder="Student Name" class="form-control"
                       value="<?= $edit_mode ? htmlspecialchars($edit_student['StudentName']) : '' ?>" required>

                <input type="text" name="StudentNumber" placeholder="Student Number" class="form-control"
                       value="<?= $edit_mode ? htmlspecialchars($edit_student['StudentNumber']) : '' ?>" required>

                <?php if ($edit_mode): ?>
                    <input type="hidden" name="student_id" value="<?= $edit_student['StudentID'] ?>">
                    <button type="submit" name="update_student" class="btn btn-success">Update</button>
                    <a href="class_list.php?module_id=<?= $module_id ?>" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <h4>Class List</h4>
   

   <!-- Bulk Delete Form -->
<!-- Bulk Delete Form -->
<form method="POST" onsubmit="return confirm('Are you sure you want to delete selected students?');">
    <table class="table table-bordered">
        <thead >
        <tr>
            <th><input type="checkbox" id="select-all"></th>
            <th>Name</th>
            <th>Student Number</th>
            <th>
                Action
                <button type="submit" name="bulk_delete" id="delete-selected-btn" class="btn btn-sm btn-danger" style="display: none; margin-left: 10px;">
                    Delete
                </button>
            </th>
        </tr>
        </thead>
        <tbody>
        <?php while ($row = $students->fetch_assoc()): ?>
            <tr>
                <td>
                    <input type="checkbox" class="student-checkbox" name="delete_students[]" value="<?= $row['StudentID']; ?>">
                </td>
                <td><?= htmlspecialchars($row['StudentName']) ?></td>
                <td><?= htmlspecialchars($row['StudentNumber']) ?></td>
                <td style="display: flex; gap: 10px;">
                    <!-- Edit Button -->
                    <form method="GET" action="class_list.php" style="margin: 0;">
                        <input type="hidden" name="module_id" value="<?= $module_id ?>">
                        <input type="hidden" name="edit" value="<?= $row['StudentID']; ?>">
                        <button type="submit" class="btn btn-sm btn-primary">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</form>
</div>

<script>
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    const deleteBtn = document.getElementById('delete-selected-btn');

    function updateDeleteButtonVisibility() {
        const anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
        deleteBtn.style.display = anyChecked ? 'inline-block' : 'none';
    }

    selectAll.addEventListener('change', () => {
        checkboxes.forEach(checkbox => checkbox.checked = selectAll.checked);
        updateDeleteButtonVisibility();
    });

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateDeleteButtonVisibility);
    });
</script>
</body>
</html>
