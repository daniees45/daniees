<?php
$page_title = 'Manage Users';
include 'includes/header.php';
require_once 'api/db.php';

// Role Check
requireRole(['super_admin', 'faculty_admin']);

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if ($_POST['delete_id'] == $_SESSION['user_id']) {
        $error = "You cannot delete yourself.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $_POST['delete_id']);
        $stmt->execute();
        header("Location: users.php?msg=deleted");
        exit;
    }
}

// Pagination
$items_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

$total_items = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$total_pages = ceil($total_items / $items_per_page);

// Fetch Users
$stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $items_per_page, $offset);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="glass-panel" style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h3>System Users</h3>
        <a href="register.php" class="glass-btn"><i class="fa-solid fa-user-plus"></i> Add New User</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                    <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                    <td>
                        <span style="padding: 4px 8px; border-radius: 4px; background: rgba(255,255,255,0.1); font-size: 0.8rem;">
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $u['role']))); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline;" onsubmit="confirmAction(event, 'Delete User', 'Are you sure you want to delete user <?php echo $u['username']; ?>?')">
                            <input type="hidden" name="delete_id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="glass-btn secondary" style="padding: 6px 10px; color: var(--danger); border-color: rgba(239,68,68,0.3);"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">(You)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 1.5rem; display: flex; justify-content: center; align-items: center; gap: 1rem;">
        <?php 
            $params = $_GET;
            function build_user_query($p, $params) {
                $params['page'] = $p;
                return '?' . http_build_query($params);
            }
        ?>
        <a href="<?php echo build_user_query(max(1, $page - 1), $params); ?>" class="glass-btn secondary small <?php if($page <= 1) echo 'disabled'; ?>" style="<?php if($page <= 1) echo 'opacity: 0.5; pointer-events: none;'; ?>">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
        <span style="font-size: 0.9rem; color: var(--text-muted);">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
        <a href="<?php echo build_user_query(min($total_pages, $page + 1), $params); ?>" class="glass-btn secondary small <?php if($page >= $total_pages) echo 'disabled'; ?>" style="<?php if($page >= $total_pages) echo 'opacity: 0.5; pointer-events: none;'; ?>">
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
