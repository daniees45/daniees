<?php
$page_title = 'Manage Rooms';
include 'includes/header.php';
require_once 'api/db.php';

// Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->bind_param("i", $_POST['delete_id']);
        $stmt->execute();
        header("Location: rooms.php?msg=deleted");
        exit;
    }
    
    if (isset($_POST['add_room'])) {
        $name = $_POST['name'];
        $capacity = $_POST['capacity'];
        $type = $_POST['type'];
        
        $equipment = $_POST['equipment'] ?? '';
        $access = isset($_POST['accessibility']) ? 1 : 0;
        $dept = $_POST['primary_dept'] ?? '';

        try {
            $stmt = $conn->prepare("INSERT INTO rooms (room_name, capacity, type, equipment, accessibility, primary_dept) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissss", $name, $capacity, $type, $equipment, $access, $dept);
            $stmt->execute();
            
            // Sync to CSV: Append to rooms.csv
            $csv_path = '../rooms.csv';
            $csv_data = [$name, $capacity];
            
            $file_handle = fopen($csv_path, 'a');
            if ($file_handle) {
                fputcsv($file_handle, $csv_data);
                fclose($file_handle);
            }
            
            header("Location: rooms.php?msg=added");
            exit;
        } catch (Exception $e) {
            $error = "Error adding room: " . $e->getMessage();
        }
    }
}

$res = $conn->query("SELECT * FROM rooms ORDER BY room_name ASC");
$rooms = $res->fetch_all(MYSQLI_ASSOC);

// CSV Stats logic moved to dashboard.php
?>

<div class="glass-panel" style="padding: 1.5rem;">
     <div style="display: flex; justify-content: flex-end; margin-bottom: 2rem;">
        <button onclick="document.getElementById('addModal').style.display='flex'" class="glass-btn"><i class="fa-solid fa-plus"></i> Add Room</button>
    </div>
    
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem;">Action completed successfully.</div>
    <?php endif; ?>

    <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
        <?php foreach ($rooms as $room): ?>
        <div class="glass-panel" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 10px; border-left: 4px solid var(--success); position: relative;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <h3 style="font-size: 1.1rem;"><?php echo htmlspecialchars($room['room_name']); ?></h3>
                <div class="dropdown">
                    <a href="edit_room.php?id=<?php echo $room['id']; ?>" style="color: var(--text-muted); margin-right: 5px;"><i class="fa-solid fa-pen"></i></a>
                    <button style="background: none; border: none; color: var(--text-muted); cursor: pointer;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                </div>
                <form method="POST" onsubmit="confirmAction(event, 'Delete Room', 'Are you sure you want to delete this room?')">
                    <input type="hidden" name="delete_id" value="<?php echo $room['id']; ?>">
                    <button type="submit" style="background: none; border: none; color: var(--danger); cursor: pointer; opacity: 0.6;"><i class="fa-solid fa-trash"></i></button>
                </form>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 5px;">
                <span style="font-size: 0.9rem; color: var(--text-muted);"><i class="fa-solid fa-users"></i> <?php echo $room['capacity']; ?></span>
                <span style="font-size: 0.9rem; color: var(--text-muted);"><i class="fa-solid fa-tag"></i> <?php echo $room['type']; ?></span>
            </div>
            
            <div style="margin-top: 10px; font-size: 0.85rem;">
                <?php if ($room['primary_dept']): ?>
                    <div style="color: var(--warning);"><i class="fa-solid fa-star"></i> Priority: <?php echo htmlspecialchars($room['primary_dept']); ?></div>
                <?php endif; ?>
                <?php if ($room['accessibility']): ?>
                    <div style="color: #4ade80;"><i class="fa-solid fa-wheelchair"></i> Wheelchair Accessible</div>
                <?php endif; ?>
                <?php if ($room['equipment']): ?>
                    <div style="color: var(--text-muted); margin-top: 5px; font-style: italic;"><i class="fa-solid fa-plug"></i> <?php echo htmlspecialchars(substr($room['equipment'], 0, 30)) . (strlen($room['equipment'])>30?'...':''); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($rooms)): ?>
            <div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 2rem;">
                No rooms available.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" style="display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div class="glass-panel" style="width: 100%; max-width: 400px; padding: 2rem;">
        <h3 style="margin-bottom: 1.5rem;">Add Room</h3>
        <form method="POST">
            <input type="hidden" name="add_room" value="1">
            <div style="display: grid; gap: 1rem;">
                <div>
                    <label style="font-size: 0.9rem; color: var(--text-muted);">Room Name</label>
                    <input type="text" name="name" class="glass-input" required placeholder="e.g. CS Lab 1">
                </div>
                <div>
                    <label style="font-size: 0.9rem; color: var(--text-muted);">Capacity</label>
                    <input type="number" name="capacity" class="glass-input" required value="50">
                </div>
                <div>
                    <label style="font-size: 0.9rem; color: var(--text-muted);">Type</label>
                    <select name="type" class="glass-input" style="background: rgba(15,23,42,0.9);">
                        <option value="Lecture">Lecture Hall</option>
                        <option value="Lab">Laboratory</option>
                        <option value="Auditorium">Auditorium</option>
                    </select>
                </div>
                <div>
                     <label style="font-size: 0.9rem; color: var(--text-muted);">Primary Department (Optional)</label>
                     <input type="text" name="primary_dept" class="glass-input" placeholder="e.g. Nursing">
                </div>
                <div>
                     <label style="font-size: 0.9rem; color: var(--text-muted);">Equipment (comma separated)</label>
                     <textarea name="equipment" class="glass-input" rows="2" placeholder="Projector, Smartboard..."></textarea>
                </div>
                <div>
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="accessibility" value="1">
                        <span style="font-size: 0.9rem; color: var(--text-muted);">Wheelchair Accessible</span>
                    </label>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 1rem;">
                    <button type="submit" class="glass-btn" style="flex: 1;">Save</button>
                    <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="glass-btn secondary">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
