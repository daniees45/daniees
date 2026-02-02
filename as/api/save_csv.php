<?php
// as/api/save_csv.php
// Saves JSON data to a CSV file and triggers DB sync

require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$filename = $input['file'] ?? '';
$data = $input['data'] ?? [];

// Validate Filename Security
if (!$filename || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename) || pathinfo($filename, PATHINFO_EXTENSION) !== 'csv') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file name. Only CSV files are allowed.']);
    exit;
}

// Verify file exists in parent directory to prevent traversal
// However, since we might be creating a new file, we check the directory writable or if file exists
$file_path = realpath('../../') . '/' . $filename;
$base_dir = realpath('../../');

// Check if the resulting path starts with base_dir (traversal check)
if (strpos(realpath($file_path) ?: $file_path, $base_dir) !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
    exit;
}

$file_path = '../../' . $filename;

try {
    // 0. Ensure csv_storage table exists
    $conn->query("CREATE TABLE IF NOT EXISTS csv_storage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) UNIQUE NOT NULL,
        content LONGBLOB NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // 1. Save to CSV File (for AI engine)
    $handle = fopen($file_path, 'w');
    if (!$handle) {
        throw new Exception("Cannot open file for writing: $filename");
    }

    $csv_content = "";
    foreach ($data as $row) {
        fputcsv($handle, $row);
        
        // Capture for DB
        $f = fopen('php://temp', 'r+');
        fputcsv($f, $row);
        rewind($f);
        $csv_content .= stream_get_contents($f);
        fclose($f);
    }
    fclose($handle);

    // 2. Save to DB (LONGBLOB)
    $stmt = $conn->prepare("INSERT INTO csv_storage (filename, content) VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE content = VALUES(content)");
    $stmt->bind_param("ss", $filename, $csv_content);
    $stmt->execute();

    // 2. Trigger DB Import Logic
    // We'll mimic the logic in import_data.php but specifically for the file that was updated
    
    if ($filename === 'rooms.csv') {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query("TRUNCATE TABLE rooms");
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $handle = fopen($file_path, "r");
        fgetcsv($handle); // Skip header
        $stmt = $conn->prepare("INSERT INTO rooms (room_name, capacity) VALUES (?, ?)");
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $name = $row[0] ?? 'Unknown';
            $cap = $row[1] ?? 50;
            $stmt->bind_param("si", $name, $cap);
            $stmt->execute();
        }
        fclose($handle);
    } 
    elseif ($filename === 'lecturer_availability.csv') {
        $handle = fopen($file_path, "r");
        fgetcsv($handle); // Skip header
        $stmt = $conn->prepare("INSERT INTO lecturers (name, availability_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE availability_json = VALUES(availability_json)");
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $name = $row[0] ?? '';
            if(!$name) continue;
            
            $avail = [];
            if(($row[1]??0) == 1) $avail[] = 0;
            if(($row[2]??0) == 1) $avail[] = 1;
            if(($row[3]??0) == 1) $avail[] = 2;
            if(($row[4]??0) == 1) $avail[] = 3;
            if(($row[5]??0) == 1) $avail[] = 4;
            
            $json = json_encode($avail);
            $stmt->bind_param("ss", $name, $json);
            $stmt->execute();
        }
        fclose($handle);
    }
    elseif ($filename === 'departmental_courses.csv') {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query("TRUNCATE TABLE sections");
        $conn->query("TRUNCATE TABLE courses");
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        $handle = fopen($file_path, "r");
        $headers = fgetcsv($handle);
        $header_map = array_flip(array_map('strtolower', array_map('trim', $headers)));
        
        $lecturers = [];
        $res = $conn->query("SELECT id, name FROM lecturers");
        while($lr = $res->fetch_assoc()) { $lecturers[$lr['name']] = $lr['id']; }
        
        $rooms = [];
        $res = $conn->query("SELECT id, room_name FROM rooms");
        while($rr = $res->fetch_assoc()) { $rooms[$rr['room_name']] = $rr['id']; }

        $stmt = $conn->prepare("INSERT INTO courses (course_code, course_title, semester, type, level, credit_hours) VALUES (?, ?, ?, ?, ?, ?)");
        $s_stmt = $conn->prepare("INSERT INTO sections (course_id, lecturer_id, room_id, assigned_day, assigned_time) VALUES (?, ?, ?, ?, ?)");
        
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $code = $row[$header_map['course_code'] ?? 0] ?? '';
            if (!$code) continue;
            
            $title = $row[$header_map['course_title'] ?? 1] ?? '';
            $lecturer_name = $row[$header_map['lecturer_name'] ?? 2] ?? '';
            $sem = $row[$header_map['semester'] ?? 3] ?? '1';
            $assigned_day = $row[$header_map['day'] ?? 4] ?? null;
            $assigned_time = $row[$header_map['start_time'] ?? 5] ?? null;
            $room_name = $row[$header_map['room_name'] ?? 7] ?? null;
            $type = $row[$header_map['source_type'] ?? 8] ?? 'Departmental';
            $level = $row[$header_map['course_level'] ?? 9] ?? 100;
            $credits = $row[$header_map['credit_hours'] ?? 10] ?? 3;
            
            $stmt->bind_param("ssssss", $code, $title, $sem, $type, $level, $credits);
            $stmt->execute(); 
            $course_id = $conn->insert_id;
            
            if ($lecturer_name && isset($lecturers[$lecturer_name])) {
                $l_id = $lecturers[$lecturer_name];
                $r_id = ($room_name && isset($rooms[$room_name])) ? $rooms[$room_name] : null;
                $s_stmt->bind_param("iiiss", $course_id, $l_id, $r_id, $assigned_day, $assigned_time);
                $s_stmt->execute();
            }
        }
        fclose($handle);
    }

    echo json_encode(['status' => 'success', 'message' => "$filename updated and synced to database."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
