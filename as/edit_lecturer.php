<?php
$page_title = 'Edit Lecturer';
include 'includes/header.php';
require_once 'api/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: lecturers.php");
    exit;
}

// Fetch existing
$stmt = $conn->prepare("SELECT * FROM lecturers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$lecturer = $stmt->get_result()->fetch_assoc();

if (!$lecturer) {
    echo "<div class='glass-panel' style='padding: 2rem;'>Lecturer not found.</div>";
    include 'includes/footer.php';
    exit;
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    
    $stmt = $conn->prepare("UPDATE lecturers SET name=?, email=? WHERE id=?");
    $stmt->bind_param("ssi", $name, $email, $id);
    $stmt->execute();
    
    header("Location: lecturers.php?msg=updated");
    exit;
}
?>

<div class="glass-panel" style="padding: 2rem; max-width: 500px; margin: 0 auto;">
    <h3 style="margin-bottom: 2rem;">Edit Lecturer</h3>
    
    <form method="POST">
        <div style="display: grid; gap: 1.5rem;">
            <div>
                <label class="stat-label">Full Name</label>
                <input type="text" name="name" class="glass-input" value="<?php echo htmlspecialchars($lecturer['name']); ?>" required>
            </div>
            
            <div>
                <label class="stat-label">Email</label>
                <input type="email" name="email" class="glass-input" value="<?php echo htmlspecialchars($lecturer['email']); ?>">
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 1rem;">
                <button type="submit" class="glass-btn" style="flex: 1;">Update Lecturer</button>
                <a href="lecturers.php" class="glass-btn secondary" style="text-align: center;">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
