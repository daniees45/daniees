<?php
$page_title = 'My Personal Schedule';
include 'includes/header.php';
require_once 'api/db.php';

requireRole(['lecturer', 'student']);

$schedule_items = [];
$error = null;

try {
    if ($_SESSION['role'] === 'lecturer') {
        // Show lecturer's teaching schedule
        $lecturer_id = $_SESSION['lecturer_id'] ?? 0;
        
        $sql = "SELECT c.course_code, c.course_title, s.assigned_day, s.assigned_time, r.room_name,
                       s.id as section_id
                FROM sections s
                JOIN courses c ON s.course_id = c.id
                LEFT JOIN rooms r ON s.room_id = r.id
                WHERE s.lecturer_id = ?
                ORDER BY FIELD(s.assigned_day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.assigned_time";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $lecturer_id);
    } else {
        // Show student's enrolled courses schedule
        $user_id = $_SESSION['user_id'];
        
        // Ensure student_enrollments table exists (Phase 1.3)
        // Query joins student_enrollments -> sections -> courses
        // Note: Logic assumes if a student is enrolled in a course, they are in the sections of that course.
        // If there are multiple sections per course, we might need more logic (e.g. enrolled in specific section).
        // For now assuming 1 section per course or enrollment tracks course_id.
        
        $sql = "SELECT c.course_code, c.course_title, s.assigned_day, s.assigned_time, r.room_name, l.name as lecturer,
                       s.id as section_id
                FROM student_enrollments e
                JOIN sections s ON e.course_id = s.course_id
                JOIN courses c ON s.course_id = c.id
                LEFT JOIN rooms r ON s.room_id = r.id
                LEFT JOIN lecturers l ON s.lecturer_id = l.id
                WHERE e.user_id = ?
                ORDER BY FIELD(s.assigned_day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.assigned_time";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $schedule_items = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Failed to load schedule: " . $conn->error;
    }
} catch (Exception $e) {
    // If table doesn't exist yet, catch error
    $error = "Error loading schedule (System update might be in progress): " . $e->getMessage();
}
?>

<div class="glass-panel" style="padding: 2rem;">
    <h2 style="margin-bottom: 1.5rem;">
        <i class="fa-solid fa-calendar-check" style="color: var(--primary-color);"></i> 
        <?php echo ($_SESSION['role'] === 'lecturer') ? 'Teaching Timetable' : 'My Weekly Classes'; ?>
    </h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php if (strpos($error, "doesn't exist") !== false): ?>
            <div class="alert alert-info">The system database is currently being updated. Please try again later.</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($schedule_items) && !$error): ?>
        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
            <i class="fa-solid fa-calendar-xmark" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p>No classes found for your schedule.</p>
            <?php if ($_SESSION['role'] === 'student'): ?>
                <a href="my_courses.php" class="glass-btn secondary" style="margin-top: 1rem;">Enroll in Courses</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Course Code</th>
                        <th>Course Title</th>
                        <th>Room</th>
                        <?php if ($_SESSION['role'] === 'student'): ?>
                        <th>Lecturer</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedule_items as $item): ?>
                    <tr>
                        <td>
                            <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #a5b4fc;">
                                <?php echo htmlspecialchars($item['assigned_day'] ?? 'Unassigned'); ?>
                            </span>
                        </td>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($item['assigned_time'] ?? '--:--'); ?></td>
                        <td style="color: var(--primary-color); font-weight: 700;">
                            <?php echo htmlspecialchars($item['course_code']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['course_title']); ?></td>
                        <td>
                            <?php if (!empty($item['room_name'])): ?>
                                <i class="fa-solid fa-location-dot" style="margin-right: 5px; color: var(--secondary-color);"></i>
                                <?php echo htmlspecialchars($item['room_name']); ?>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">TBA</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($_SESSION['role'] === 'student'): ?>
                        <td><?php echo htmlspecialchars($item['lecturer'] ?? 'TBA'); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
