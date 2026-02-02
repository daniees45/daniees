<?php
// as/api/check_conflicts.php
header('Content-Type: application/json');

$csv_file = realpath('../../') . '/final_web_schedule.csv';
$conflicts = [];

if (!file_exists($csv_file)) {
    echo json_encode(['status' => 'error', 'message' => 'Schedule file not found.']);
    exit;
}

$schedule = [];
if (($handle = fopen($csv_file, "r")) !== FALSE) {
    fgetcsv($handle); // Skip header
    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        // [Code, Title, Credits, Lecturer, Room, Day, Time]
        $schedule[] = [
            'code' => $row[0] ?? '',
            'title' => $row[1] ?? '',
            'lecturer' => $row[3] ?? '',
            'room' => $row[4] ?? '',
            'day' => $row[5] ?? '',
            'time' => $row[6] ?? ''
        ];
    }
    fclose($handle);
}

// Group by Day+Time
$time_slots = [];
foreach ($schedule as $idx => $class) {
    $key = $class['day'] . '|' . $class['time'];
    if (!isset($time_slots[$key])) {
        $time_slots[$key] = [];
    }
    $class['original_index'] = $idx;
    $time_slots[$key][] = $class;
}

// Analyze Conflicts
foreach ($time_slots as $slot => $classes) {
    list($day, $time) = explode('|', $slot);
    
    // Check Room Conflicts
    $rooms = [];
    foreach ($classes as $c) {
        $r = $c['room'];
        if (!$r || $r == 'Unassigned') continue;
        if (isset($rooms[$r])) {
            $conflicts[] = [
                'type' => 'Room Double Booking',
                'severity' => 'High',
                'description' => "Room '$r' is booked for multiple classes on $day at $time.",
                'entities' => [$rooms[$r]['code'], $c['code']],
                'details' => $c
            ];
        } else {
            $rooms[$r] = $c;
        }
    }

    // Check Lecturer Conflicts
    $lecturers = [];
    foreach ($classes as $c) {
        $l = $c['lecturer'];
        if (!$l || $l == 'TBD') continue;
        if (isset($lecturers[$l])) {
            $conflicts[] = [
                'type' => 'Lecturer Double Booking',
                'severity' => 'High',
                'description' => "Lecturer '$l' is assigned to multiple classes on $day at $time.",
                'entities' => [$lecturers[$l]['code'], $c['code']],
                'details' => $c
            ];
        } else {
            $lecturers[$l] = $c;
        }
    }
}

echo json_encode([
    'status' => 'success', 
    'count' => count($conflicts), 
    'conflicts' => $conflicts
]);
