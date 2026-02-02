<?php
// as/api/validate_data.php
header('Content-Type: application/json');
require_once 'db.php';

$issues = [];
$warnings = [];

try {
    // 1. Check Rooms with 0 Capacity
    $res = $conn->query("SELECT room_name FROM rooms WHERE capacity = 0 OR capacity IS NULL");
    while ($r = $res->fetch_assoc()) {
        $issues[] = "Room '{$r['room_name']}' has 0 capacity.";
    }

    // 2. Check Lecturers with No Availability
    $res = $conn->query("SELECT name, availability_json FROM lecturers");
    while ($l = $res->fetch_assoc()) {
        $avail = json_decode($l['availability_json'] ?? '[]', true);
        if (is_array($avail) && empty($avail)) { // Empty array means no availability if using index logic? Wait, logic is index of AVAILABLE days.
            // If avail is empty array [], it means NO days available.
            $issues[] = "Lecturer '{$l['name']}' has NO available days.";
        }
    }

    // 3. Check Duplicate Course Codes (if unique constraint missing)
    $res = $conn->query("SELECT course_code, COUNT(*) as c FROM courses GROUP BY course_code HAVING c > 1");
    while ($c = $res->fetch_assoc()) {
        $issues[] = "Duplicate Course Code detected: '{$c['course_code']}' appears {$c['c']} times.";
    }

    // 4. Check Sections without Lecturer
    $res = $conn->query("SELECT c.course_code FROM sections s JOIN courses c ON s.course_id = c.id WHERE s.lecturer_id IS NULL");
    while ($s = $res->fetch_assoc()) {
        $warnings[] = "Section for '{$s['course_code']}' has no assigned lecturer.";
    }

    // 5. Check missing data files in DB
    $required_files = ['rooms.csv', 'departmental_courses.csv'];
    foreach ($required_files as $f) {
        $stmt = $conn->prepare("SELECT id FROM csv_storage WHERE filename = ?");
        $stmt->bind_param("s", $f);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $warnings[] = "Core file '$f' missing from database storage.";
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'issues' => $issues,
        'warnings' => $warnings,
        'message' => (empty($issues) && empty($warnings)) ? "Data integrity check passed! System ready." : "Found " . count($issues) . " issues and " . count($warnings) . " warnings."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
