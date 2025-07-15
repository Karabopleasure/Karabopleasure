<?php
require 'connection.php';
require 'vendor/autoload.php'; 
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_GET['session_id']) || !isset($_GET['type'])) {
    die("Invalid request.");
}

$session_id = $_GET['session_id'];
$type = $_GET['type'];

// Fetch session details
$session_query = "SELECT s.SessionName, m.ModuleName FROM Sessions s
                  JOIN Modules m ON s.ModuleID = m.ModuleID
                  WHERE s.SessionID = ?";
$stmt = $conn->prepare($session_query);
$stmt->bind_param("i", $session_id);
$stmt->execute();
$session_result = $stmt->get_result();
$session_details = $session_result->fetch_assoc();
$session_name = $session_details['SessionName'];
$module_name = $session_details['ModuleName'];

// Fetch attendance data
$attendance_query = "SELECT Timestamp, StudentNumber, StudentName FROM Attendance WHERE SessionID = ? ORDER BY Timestamp DESC";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $session_id);
$stmt->execute();
$attendance_result = $stmt->get_result();

$data = [];
while ($row = $attendance_result->fetch_assoc()) {
    $data[] = $row;
}

// Export as Excel
if ($type == 'excel') {
   

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Timestamp');
    $sheet->setCellValue('B1', 'Student Number');
    $sheet->setCellValue('C1', 'Student Name');

    $rowNum = 2;
    foreach ($data as $row) {
        $sheet->setCellValue("A$rowNum", $row['Timestamp']);
        $sheet->setCellValue("B$rowNum", $row['StudentNumber']);
        $sheet->setCellValue("C$rowNum", $row['StudentName']);
        $rowNum++;
    }

    $writer = new Xlsx($spreadsheet);
    $filename = "{$session_name}.xlsx";

    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $writer->save('php://output');
    exit();
}

// Export as PDF
if ($type == 'pdf') {
    require 'vendor/tecnickcom/tcpdf/tcpdf.php';
    
    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    
    $pdf->Cell(0, 10, "Attendance for $session_name ", 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(50, 10, 'Timestamp', 1);
    $pdf->Cell(40, 10, 'Student Number', 1);
    $pdf->Cell(60, 10, 'Student Name', 1);
    $pdf->Ln();

    foreach ($data as $row) {
        $pdf->Cell(50, 10, $row['Timestamp'], 1);
        $pdf->Cell(40, 10, $row['StudentNumber'], 1);
        $pdf->Cell(60, 10, $row['StudentName'], 1);
        $pdf->Ln();
    }

    $pdf->Output("{$session_name}.pdf", 'D');
    exit();
}

?>
