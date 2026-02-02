<?php
// as/api/export_pdf.php
require_once 'db.php';

$requested_file = $_POST['file'] ?? $_GET['file'] ?? 'final_web_schedule.csv';
$h1 = $_POST['h1'] ?? $_GET['h1'] ?? "VALLEY VIEW UNIVERSITY";
$h2 = $_POST['h2'] ?? $_GET['h2'] ?? "COMPUTER SCIENCE, INFORMATION TECHNOLOGY, BUSINESS INFORMATION SYSTEMS AND MATHEMATICAL SCIENCES";
$h3 = $_POST['h3'] ?? $_GET['h3'] ?? "SECOND SEMESTER - 2025 / 2026 ACADEMIC YEAR";
$h4 = $_POST['h4'] ?? $_GET['h4'] ?? "TEACHING TIMETABLE";

// Security Check
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $requested_file) || pathinfo($requested_file, PATHINFO_EXTENSION) !== 'csv') {
    die("Invalid file");
}

$csv_path = realpath('../../' . $requested_file);
$base_dir = realpath('../../');

if (!$csv_path || strpos($csv_path, $base_dir) !== 0 || !file_exists($csv_path)) {
    die("File not found or access denied");
}

$temp_pdf = tempnam(sys_get_temp_dir(), 'export_') . '.pdf';
$py_script = realpath('../../csv_to_pdf.py');

$cmd = "python3 " . escapeshellarg($py_script) . 
       " --input " . escapeshellarg($csv_path) . 
       " --output " . escapeshellarg($temp_pdf) . 
       " --h1 " . escapeshellarg($h1) . 
       " --h2 " . escapeshellarg($h2) . 
       " --h3 " . escapeshellarg($h3) . 
       " --h4 " . escapeshellarg($h4);

$output = shell_exec($cmd . " 2>&1");

if (!file_exists($temp_pdf) || filesize($temp_pdf) === 0) {
    error_log("PDF Generation failed: " . $output);
    http_response_code(500);
    die("Error generating PDF. Please contact the administrator.");
}
?>
