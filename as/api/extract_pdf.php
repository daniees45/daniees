<?php
// as/api/extract_pdf.php
header('Content-Type: application/json');
require_once 'db.php';

$output_name = isset($_POST['filename']) ? $_POST['filename'] : 'vvu_raw.csv';
// Ensure .csv extension
if (substr($output_name, -4) !== '.csv') $output_name .= '.csv';

if (!isset($_FILES['pdf_file'])) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded.']);
    exit;
}

$file = $_FILES['pdf_file'];
$root_dir = realpath('../../') . '/';
$target_pdf = $root_dir . 'vvu_temp_extract.pdf';
$temp_csv = $root_dir . 'vvu_temp_extract.csv';

if (move_uploaded_file($file['tmp_name'], $target_pdf)) {
    $python_code = "
import sys
import os
sys.path.append('.')
sys.path.append('lib')

import tabula
import pandas as pd

pdf_file = r'$target_pdf'
out_csv = r'$temp_csv'

try:
    dfs = tabula.read_pdf(pdf_file, pages='all', multiple_tables=True, lattice=True)
    if dfs:
        all_data = pd.concat(dfs, ignore_index=True)
        all_data.to_csv(out_csv, index=False)
        print('Extraction successful')
    else:
        print('No tables found in PDF.')
except Exception as e:
    print(f'Python Error: {str(e)}')
";
    
    $cmd = "cd " . escapeshellarg($root_dir) . " && /usr/local/bin/python3 -c " . escapeshellarg($python_code) . " 2>&1";
    $output = shell_exec($cmd);
    
    // Check if temp CSV was created
    if (file_exists($temp_csv)) {
        $csv_content = file_get_contents($temp_csv);
        
        // Save to DB
        $stmt = $conn->prepare("INSERT INTO csv_storage (filename, content) VALUES (?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)");
        $stmt->bind_param("sb", $output_name, $null);
        $stmt->send_long_data(1, $csv_content);
        
        if ($stmt->execute()) {
            $db_status = "Saved to DB as $output_name";
        } else {
            $db_status = "Error saving to DB: " . $stmt->error;
        }
        
        // Cleanup temp files
        unlink($temp_csv);
        unlink($target_pdf);
        
        echo json_encode([
            'status' => 'success',
            'message' => "Extraction complete. $db_status",
            'filename' => $output_name,
            'output' => $output
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Extraction failed to produce CSV.',
            'output' => $output
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded PDF.']);
}
