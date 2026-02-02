<?php
// as/api/schedule_versions.php
header('Content-Type: application/json');
require_once 'db.php';

$action = $_POST['action'] ?? '';
$chosen_file = $_POST['file_name'] ?? 'final_web_schedule.csv';
$csv_path = realpath('../../') . '/' . $chosen_file;

try {
    if ($action === 'save') {
        $name = $_POST['name'] ?? 'Untitled Version';
        $desc = $_POST['description'] ?? '';
        $user_id = $_SESSION['user_id'] ?? null;
        
        if (!file_exists($csv_path)) {
            throw new Exception("No current schedule file found to save.");
        }
        
        $content = file_get_contents($csv_path);
        
        // Generate PDF
        $temp_pdf = tempnam(sys_get_temp_dir(), 'sched_') . '.pdf';
        $py_script = realpath('../../csv_to_pdf.py');
        $cmd = "python3 " . escapeshellarg($py_script) . " " . escapeshellarg($csv_path) . " " . escapeshellarg($temp_pdf);
        
        $output = shell_exec($cmd . " 2>&1");
        
        $pdf_content = null;
        if (file_exists($temp_pdf)) {
            $pdf_content = file_get_contents($temp_pdf);
            @unlink($temp_pdf);
        } else {
            // Log error but don't fail the whole CSV save
            error_log("PDF generation failed: " . $output);
        }
        
        $stmt = $conn->prepare("INSERT INTO schedule_versions (version_name, description, file_content, pdf_content, user_id) VALUES (?, ?, ?, ?, ?)");
        $null = NULL;
        $stmt->bind_param("ssssi", $name, $desc, $content, $null, $user_id);
        
        if ($pdf_content !== null) {
            $stmt->send_long_data(3, $pdf_content);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Version saved successfully.', 'id' => $conn->insert_id]);
        } else {
            throw new Exception("Database save failed: " . $stmt->error);
        }

    } elseif ($action === 'load') {
        $id = $_POST['id'] ?? 0;
        
        $stmt = $conn->prepare("SELECT file_content FROM schedule_versions WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            if (file_put_contents($csv_path, $row['file_content']) !== FALSE) {
                echo json_encode(['status' => 'success', 'message' => 'Version loaded successfully.']);
            } else {
                throw new Exception("Failed to write to CSV file.");
            }
        } else {
            throw new Exception("Version not found.");
        }

    } elseif ($action === 'list') {
        $res = $conn->query("SELECT id, version_name, description, created_at FROM schedule_versions ORDER BY created_at DESC");
        $versions = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['status' => 'success', 'versions' => $versions]);
        
    } else {
        throw new Exception("Invalid action.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
