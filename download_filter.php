<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'connection.php';
require 'vendor/autoload.php'; 
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
require 'vendor/tecnickcom/tcpdf/tcpdf.php';

$filename = $_GET['filename'] ?? 'Attendance_Report';

if (!isset($_GET['module_id'])) {
    die("Invalid request.");
}

$module_id = $_GET['module_id'];
$search_date = $_GET['search_date'] ?? '';
$student_name = $_GET['student_name'] ?? '';
$student_number = $_GET['student_number'] ?? '';

// Calculate total sessions held
if (!empty($search_date)) {
    $stmt_sessions = $conn->prepare("SELECT COUNT(*) FROM Sessions WHERE ModuleID = ? AND SessionDate = ?");
    $stmt_sessions->bind_param("is", $module_id, $search_date);
} else {
    $stmt_sessions = $conn->prepare("SELECT COUNT(*) FROM Sessions WHERE ModuleID = ?");
    $stmt_sessions->bind_param("i", $module_id);
}
$stmt_sessions->execute();
$stmt_sessions->bind_result($total_sessions);
$stmt_sessions->fetch();
$stmt_sessions->close();

// Main student query
$student_filters = ["s.ModuleID = ?"];
$params = [$module_id];
$param_types = "i";

if (!empty($student_name)) {
    $student_filters[] = "s.StudentName LIKE ?";
    $params[] = "%$student_name%";
    $param_types .= "s";
}
if (!empty($student_number)) {
    $student_filters[] = "s.StudentNumber LIKE ?";
    $params[] = "%$student_number%";
    $param_types .= "s";
}
$where_clause = implode(" AND ", $student_filters);

if (!empty($search_date)) {
    $join_sql = "INNER JOIN Attendance a 
        ON s.StudentNumber = a.StudentNumber 
        AND s.ModuleID = a.ModuleID
        AND a.SessionID IN (SELECT SessionID FROM Sessions WHERE SessionDate = ? AND ModuleID = ?)";
    $params[] = $search_date;
    $params[] = $module_id;
    $param_types .= "si";
} else {
    $join_sql = "LEFT JOIN Attendance a ON s.StudentNumber = a.StudentNumber AND s.ModuleID = a.ModuleID";
}

$sql = "
    SELECT 
        s.StudentName,
        s.StudentNumber,
        COUNT(a.AttendanceID) AS SessionsAttended
    FROM Students s
    $join_sql
    WHERE $where_clause
    GROUP BY s.StudentName, s.StudentNumber
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $row['SessionsHeld'] = $total_sessions;
    $row['AttendancePercentage'] = $total_sessions > 0
        ? round(($row['SessionsAttended'] / $total_sessions) * 100, 2)
        : 0;
    $students[] = $row;
}

// Query for unknown students
$unknown_sql = "
    SELECT 
        a.StudentName,
        a.StudentNumber,
        MAX(ses.SessionDate) as LastSeenDate,
        COUNT(a.AttendanceID) as SessionsAttended
    FROM Attendance a
    LEFT JOIN Students s ON a.StudentNumber = s.StudentNumber AND a.ModuleID = s.ModuleID
    INNER JOIN Sessions ses ON a.SessionID = ses.SessionID
    WHERE s.StudentNumber IS NULL AND a.ModuleID = ?
";

$unknown_params = [$module_id];
$unknown_types = "i";

if (!empty($search_date)) {
    $unknown_sql .= " AND ses.SessionDate = ?";
    $unknown_params[] = $search_date;
    $unknown_types .= "s";
}

if (!empty($student_name)) {
    $unknown_sql .= " AND a.StudentName LIKE ?";
    $unknown_params[] = "%$student_name%";
    $unknown_types .= "s";
}

if (!empty($student_number)) {
    $unknown_sql .= " AND a.StudentNumber LIKE ?";
    $unknown_params[] = "%$student_number%";
    $unknown_types .= "s";
}

$unknown_sql .= " GROUP BY a.StudentNumber, a.StudentName";

$unknown_stmt = $conn->prepare($unknown_sql);
$unknown_stmt->bind_param($unknown_types, ...$unknown_params);
$unknown_stmt->execute();
$unknown_result = $unknown_stmt->get_result();

$unknown_students = [];
while ($row = $unknown_result->fetch_assoc()) {
    $row['StudentName'] = $row['StudentName'] ?? 'Unknown';
    $row['SessionsHeld'] = $total_sessions;
    $row['AttendancePercentage'] = $total_sessions > 0
        ? round(($row['SessionsAttended'] / $total_sessions) * 100, 2)
        : 0;
    $unknown_students[] = $row;
}

// Export section
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];

    if ($export_type === 'pdf') {
        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, "Attendance Summary Report", 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(50, 10, 'Student Name', 1);
        $pdf->Cell(40, 10, 'Student Number', 1);
        $pdf->Cell(30, 10, 'Sessions Held', 1);
        $pdf->Cell(40, 10, 'Sessions Attended', 1);
        $pdf->Cell(30, 10, 'Attendance %', 1);
        $pdf->Ln();

        foreach ($students as $student) {
            $pdf->Cell(50, 10, $student['StudentName'], 1);
            $pdf->Cell(40, 10, $student['StudentNumber'], 1);
            $pdf->Cell(30, 10, $student['SessionsHeld'], 1);
            $pdf->Cell(40, 10, $student['SessionsAttended'], 1);
            $pdf->Cell(30, 10, $student['AttendancePercentage'] . '%', 1);
            $pdf->Ln();
        }

        if (!empty($unknown_students)) {
            $pdf->Ln(10);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->Cell(0, 10, "Students Not on Class List", 0, 1, 'L');
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(50, 10, 'Student Name', 1);
            $pdf->Cell(40, 10, 'Student Number', 1);
            $pdf->Cell(30, 10, 'Sessions Held', 1);
            $pdf->Cell(40, 10, 'Sessions Attended', 1);
            $pdf->Cell(30, 10, 'Attendance %', 1);
            $pdf->Ln();

            foreach ($unknown_students as $student) {
                $pdf->Cell(50, 10, $student['StudentName'], 1);
                $pdf->Cell(40, 10, $student['StudentNumber'], 1);
                $pdf->Cell(30, 10, $student['SessionsHeld'], 1);
                $pdf->Cell(40, 10, $student['SessionsAttended'], 1);
                $pdf->Cell(30, 10, $student['AttendancePercentage'] . '%', 1);
                $pdf->Ln();
            }
        }

        $pdf->Output("$filename.pdf", 'D');
        exit();
    }

    if ($export_type === 'excel') {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(
            ['Student Name', 'Student Number', 'Sessions Held', 'Sessions Attended', 'Attendance %'],
            NULL,
            'A1'
        );

        $rowNum = 2;
        foreach ($students as $student) {
            $sheet->setCellValue("A$rowNum", $student['StudentName']);
            $sheet->setCellValue("B$rowNum", $student['StudentNumber']);
            $sheet->setCellValue("C$rowNum", $student['SessionsHeld']);
            $sheet->setCellValue("D$rowNum", $student['SessionsAttended']);
            $sheet->setCellValue("E$rowNum", $student['AttendancePercentage'] . '%');
            $rowNum++;
        }

        if (!empty($unknown_students)) {
            $rowNum += 2;
            $sheet->setCellValue("A$rowNum", "Students Not on Class List");
            $rowNum++;
            $sheet->fromArray(
                ['Student Name', 'Student Number', 'Sessions Held', 'Sessions Attended', 'Attendance %'],
                NULL,
                "A$rowNum"
            );
            $rowNum++;

            foreach ($unknown_students as $student) {
                $sheet->setCellValue("A$rowNum", $student['StudentName']);
                $sheet->setCellValue("B$rowNum", $student['StudentNumber']);
                $sheet->setCellValue("C$rowNum", $student['SessionsHeld']);
                $sheet->setCellValue("D$rowNum", $student['SessionsAttended']);
                $sheet->setCellValue("E$rowNum", $student['AttendancePercentage'] . '%');
                $rowNum++;
            }
        }

        $writer = new Xlsx($spreadsheet);
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=\"$filename.xlsx\"");
        $writer->save('php://output');
        exit();
    }
}

die("Error: No export type specified.");
