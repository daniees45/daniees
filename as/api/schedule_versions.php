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
        
        if (!file_exists($csv_path)) {
            throw new Exception("No current schedule file found to save.");
        }
        
        $content = file_get_contents($csv_path);
        
        $stmt = $conn->prepare("INSERT INTO schedule_versions (version_name, description, file_content) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $desc, $content);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Version saved successfully.']);
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
