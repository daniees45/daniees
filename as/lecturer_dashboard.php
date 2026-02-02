<?php
$page_title = 'Lecturer Dashboard';
include 'includes/header.php';
require_once 'api/db.php';

// Access Check
if ($_SESSION['role'] !== 'lecturer') {
    echo "<div class='glass-panel' style='padding: 2rem; margin: 2rem; text-align: center;'>Access Denied. Lecturer area only.</div>";
    include 'includes/footer.php';
    exit;
}

$user_id = $_SESSION['user_id'];
$lecturer_id = null;
$lecturer_name = '';
$availability = [];

// Get Lecturer Details
$stmt = $conn->prepare("SELECT l.id, l.name, l.availability_json FROM users u JOIN lecturers l ON u.lecturer_id = l.id WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $lecturer_id = $row['id'];
    $lecturer_name = $row['name'];
    $availability = json_decode($row['availability_json'] ?? '[]', true);
    if (!is_array($availability)) $availability = [];
} else {
    echo "<div class='glass-panel' style='padding: 2rem; margin: 2rem; text-align: center; color: var(--warning);'>Your account is not linked to a Lecturer Profile. Please contact Admin.</div>";
    include 'includes/footer.php';
    exit;
}

// Handle Availability Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_availability'])) {
    $new_avail = [];
    if (isset($_POST['days'])) {
        foreach ($_POST['days'] as $day_idx) {
            $new_avail[] = (int)$day_idx;
        }
    }
    $json = json_encode($new_avail);
    
    // Update DB
    $up_stmt = $conn->prepare("UPDATE lecturers SET availability_json = ? WHERE id = ?");
    $up_stmt->bind_param("si", $json, $lecturer_id);
    if ($up_stmt->execute()) {
        $msg = "Availability updated successfully.";
        $availability = $new_avail;
        
        // Trigger Sync to CSV for AI (Optional, but good practice)
        // We'll just update the CSV file directly or let the sync run later
    } else {
        $error = "Failed to update.";
    }
}

// Get My Schedule
$my_schedule = [];
$csv_file = realpath('../') . '/final_web_schedule.csv';
/*
    Indices based on sync.php export:
    0:Code, 1:Title, 2:Credits, 3:Lecturer, 4:Room, 5:Day, 6:Time
*/
if (file_exists($csv_file) && ($handle = fopen($csv_file, "r")) !== FALSE) {
    fgetcsv($handle);
    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (trim($row[3]) == $lecturer_name) {
            $my_schedule[] = [
                'code' => $row[0],
                'title' => $row[1],
                'room' => $row[4],
                'day' => $row[5],
                'time' => $row[6]
            ];
        }
    }
    fclose($handle);
}

$days_map = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>

<div class="glass-panel" style="padding: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h2>Welcome, <?php echo htmlspecialchars($lecturer_name); ?></h2>
            <p style="color: var(--text-muted);">Manage your potential availability and view your current classes.</p>
        </div>
        <div class="status-badge online">
            <i class="fa-solid fa-check"></i> Profile Active
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <!-- 1. My Schedule -->
        <div>
            <h3 style="margin-bottom: 1rem;"><i class="fa-solid fa-calendar"></i> My Classes</h3>
            <?php if (empty($my_schedule)): ?>
                <div style="padding: 2rem; background: rgba(255,255,255,0.03); border-radius: 8px; text-align: center; color: var(--text-muted);">
                    No classes assigned in current schedule.
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Day/Time</th>
                                <th>Course</th>
                                <th>Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($my_schedule as $c): ?>
                            <tr>
                                <td>
                                    <div style="color: var(--primary-color); font-weight: 500;"><?php echo $c['day']; ?></div>
                                    <div style="font-size: 0.85rem; font-family: monospace;"><?php echo $c['time']; ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo $c['code']; ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo $c['title']; ?></div>
                                </td>
                                <td><?php echo $c['room']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Availability Settings -->
        <div>
            <h3 style="margin-bottom: 1rem;"><i class="fa-solid fa-clock"></i> Set Availability</h3>
            <?php if(isset($msg)) echo "<div class='alert alert-success'>$msg</div>"; ?>
            
            <form method="POST" style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">
                    Select the days you are available to teach. This will guide the AI for the next schedule generation.
                </p>
                
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php foreach($days_map as $idx => $day): ?>
                        <label class="glass-btn secondary" style="display: flex; align-items: center; gap: 10px; justify-content: flex-start; cursor: pointer; text-align: left;">
                            <input type="checkbox" name="days[]" value="<?php echo $idx; ?>" <?php if(in_array($idx, $availability)) echo 'checked'; ?>>
                            <span><?php echo $day; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" name="update_availability" class="glass-btn" style="width: 100%;">
                        <i class="fa-solid fa-save"></i> Save Availability
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
