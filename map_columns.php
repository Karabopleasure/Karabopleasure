<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php'; // PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\IOFactory;

$file = $_GET['file'];
$lecturer_id = $_GET['lecturer_id'];
$module_id = $_GET['module_id'];
$ext = pathinfo($file, PATHINFO_EXTENSION);

$rows = [];

if (in_array($ext, ['xls', 'xlsx'])) {
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true); // Keep keys as A, B, C, etc.
} else {
    die("PDF mapping not yet supported.");
}

$headers = [];
foreach ($rows as $index => $row) {
    // Check if the row is not empty
    if (!empty(array_filter($row))) {
        $headers = $row; // Set this row as the header
        unset($rows[$index]); // Remove the header row from the data
        break;
    }
}

// Re-index the rows array to start from 1
$rows = array_values($rows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class List</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
     <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
          .main-content {
        width: calc(100% - 270px - 10px);
        margin-left: 270px; 
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
    </style>
</head>
<body>
  <div class="main-content">

<div class="sidebar">
        <h2 style="color:#ffffff">QR Attendance</h2>
        <a href="lecturer_dashboard.php">Home</a>
        <a href="add_module.php">Add Module</a>
        <a href="class_list.php?module_id=<?php echo htmlspecialchars($module_id); ?>">Back</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
<h3> Map Columns</h3>
<form id="studentMapForm" method="POST" action="save_students.php">


    <input type="hidden" name="file" value="<?= htmlspecialchars($file) ?>">
    <input type="hidden" name="lecturer_id" value="<?= htmlspecialchars($lecturer_id) ?>">
    <input type="hidden" name="module_id" value="<?= htmlspecialchars($module_id) ?>">

  
  <div class="input-button-container"> 
  <label>Student Name Column(s): <small>(Hold Ctrl to select multiple)</small></label>
    <select name="name_col[]" multiple size="4" required>
        <?php foreach ($headers as $col => $val): ?>
            <option value="<?= $col ?>"><?= "$col: $val" ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Student Number Column:</label>
    <select name="number_col" required>
        <?php foreach ($headers as $col => $val): ?>
            <option value="<?= $col ?>"><?= "$col: $val" ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <button type="submit">Save Students</button>

    </div>
</form>

<hr>

<h3> File Preview (First 5 Rows)</h3>
<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr>
            <?php foreach ($headers as $col => $val): ?>
                <th><?= htmlspecialchars($val) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php
        // Preview next 4 rows (after header)
        for ($i = 2; $i <= min(6, count($rows)); $i++):
            $row = $rows[$i];
        ?>
            <tr>
                <?php foreach ($headers as $col => $_): ?>
                    <td><?= htmlspecialchars($row[$col] ?? '') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endfor; ?>
    </tbody>
</table>
                </div>

                <script>
document.getElementById('studentMapForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    fetch('save_students.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(result => {
        Swal.fire({
            title: 'Success!',
            html: result,
            icon: 'success',
            confirmButtonText: 'OK'
        }).then(() => {
            const moduleId = formData.get('module_id');
            window.location.href = 'class_list.php?module_id=' + moduleId;
        });
    })
    .catch(error => {
        Swal.fire({
            title: 'Error',
            text: "An error occurred: " + error,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    });
});
</script>


</body>
</html>
