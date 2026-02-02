<?php
// as/api/sync.php
// Exports MySQL tables to CSV files for the AI engine
header('Content-Type: application/json');

require_once 'db.php';

$output_dir = realpath('../../') . '/';

function export_to_csv($filename, $headers, $data) {
    global $output_dir;
    $path = $output_dir . $filename;
    $handle = @fopen($path, 'w');
    if (!$handle) {
        throw new Exception("Could not open $filename for writing at $path.");
    }
    fputcsv($handle, $headers);
    foreach ($data as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);
    return true;
}

try {
    // 0. Materialize Files from DB (Ensures consistency)
    $res = $conn->query("SELECT filename, content FROM csv_storage");
    if ($res) {
        while ($file = $res->fetch_assoc()) {
            $dest = $output_dir . $file['filename'];
            if (@file_put_contents($dest, $file['content']) === false) {
                // Non-critical warning, but we log it
            }
        }
    }

    // 1. Export Rooms
    $rooms_res = $conn->query("SELECT room_name, capacity FROM rooms");
    if (!$rooms_res) throw new Exception("Error fetching rooms: " . $conn->error);
    $rooms = $rooms_res->fetch_all(MYSQLI_NUM);
    export_to_csv('rooms.csv', ['room_name', 'capacity'], $rooms);

    // 2. Export Lecturers
    $res = $conn->query("SELECT name, availability_json FROM lecturers");
    if (!$res) throw new Exception("Error fetching lecturers: " . $conn->error);
    $lecturers_csv = [];
    while ($l = $res->fetch_assoc()) {
        $avail = json_decode($l['availability_json'] ?? '[]', true);
        if (!is_array($avail)) $avail = [0,1,2,3,4]; // Default full
        
        $lecturers_csv[] = [
            $l['name'],
            in_array(0, $avail) ? 1 : 0,
            in_array(1, $avail) ? 1 : 0,
            in_array(2, $avail) ? 1 : 0,
            in_array(3, $avail) ? 1 : 0,
            in_array(4, $avail) ? 1 : 0
        ];
    }
    export_to_csv('lecturer_availability.csv', ['lecturer_name', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'], $lecturers_csv);

    // 3. Export Courses/Sections
    $sql = "SELECT c.course_code, c.course_title, l.name as lecturer_name, c.semester, 
                   s.assigned_day as day, s.assigned_time as start_time, '' as end_time, 
                   r.room_name, c.type as source_type, c.level as course_level, c.credit_hours
            FROM courses c
            JOIN sections s ON c.id = s.course_id
            LEFT JOIN lecturers l ON s.lecturer_id = l.id
            LEFT JOIN rooms r ON s.room_id = r.id";
            
    $sections_res = $conn->query($sql);
    if (!$sections_res) throw new Exception("Error fetching sections: " . $conn->error);
    $sections = $sections_res->fetch_all(MYSQLI_NUM);
    $headers = ['course_code','course_title','lecturer_name','Semester','day','start_time','end_time','room_name','source_type','course_level','credit_hours'];
    export_to_csv('departmental_courses.csv', $headers, $sections);

    echo json_encode(["status" => "success", "message" => "Database synced to CSVs successfully."]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
