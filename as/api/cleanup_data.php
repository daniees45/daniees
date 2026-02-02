<?php
// as/api/cleanup_data.php
header('Content-Type: application/json');
require_once 'db.php';

$input_name = isset($_POST['input_filename']) ? $_POST['input_filename'] : 'vvu_raw.csv';
$output_name = isset($_POST['output_filename']) ? $_POST['output_filename'] : 'departmental_courses.csv';

// Ensure .csv extension
if (substr($input_name, -4) !== '.csv') $input_name .= '.csv';
if (substr($output_name, -4) !== '.csv') $output_name .= '.csv';

$root_dir = realpath('../../') . '/';
$temp_input = $root_dir . 'temp_pre_cleanup.csv';
$temp_output = $root_dir . 'temp_post_cleanup.csv';

// 1. Get input from DB
$stmt = $conn->prepare("SELECT content FROM csv_storage WHERE filename = ?");
$stmt->bind_param("s", $input_name);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => "File $input_name not found in database."]);
    exit;
}

file_put_contents($temp_input, $row['content']);

// 2. Run Python Cleanup
$python_code = "
import sys
import os
sys.path.append('.')
sys.path.append('lib')
try:
    from clean_up import clean_data
    clean_data(r'$temp_input', r'$temp_output')
    print('Cleanup successful')
except Exception as e:
    print(f'Python Error: {str(e)}')
";

$cmd = "cd " . escapeshellarg($root_dir) . " && /usr/local/bin/python3 -c " . escapeshellarg($python_code) . " 2>&1";
$output = shell_exec($cmd);

// 3. Save result back to DB
if (file_exists($temp_output)) {
    $cleaned_content = file_get_contents($temp_output);
    
    $stmt = $conn->prepare("INSERT INTO csv_storage (filename, content) VALUES (?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)");
    $stmt->bind_param("sb", $output_name, $null);
    $stmt->send_long_data(1, $cleaned_content);
    
    if ($stmt->execute()) {
        $db_status = "Saved cleaned data as $output_name";
    } else {
        $db_status = "Error saving to DB: " . $stmt->error;
    }
    
    // Cleanup
    unlink($temp_input);
    unlink($temp_output);
    
    echo json_encode([
        'status' => 'success',
        'message' => "Cleanup complete. $db_status",
        'output' => $output
    ]);
} else {
    @unlink($temp_input);
    echo json_encode([
        'status' => 'error',
        'message' => 'Cleanup failed to produce output.',
        'output' => $output
    ]);
}
