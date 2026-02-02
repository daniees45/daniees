<?php
$page_title = 'Register New User';
include 'includes/header.php';
require_once 'api/db.php';

// Only admins
if ($_SESSION['role'] != 'super_admin' && $_SESSION['role'] != 'faculty_admin') {
    echo "<div class='glass-panel' style='padding: 2rem; margin: 2rem; text-align: center; color: var(--danger);'>Access Denied</div>";
    include 'includes/footer.php';
    exit;
}

// Fetch Lecturers for linking
$lecturers = [];
$res = $conn->query("SELECT id, name FROM lecturers ORDER BY name ASC");
if ($res) $lecturers = $res->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']); // Keep for non-lecturers
    $role = $_POST['role'];
    $department = ($role === 'faculty_admin') ? $_POST['department'] : null;
    $password = $_POST['password'];
    $lecturer_id = ($role === 'lecturer' && !empty($_POST['lecturer_id'])) ? $_POST['lecturer_id'] : null;

    // If linking to a lecturer, use their name as full name
    if ($lecturer_id) {
        $stmt = $conn->prepare("SELECT name FROM lecturers WHERE id = ?");
        $stmt->bind_param("i", $lecturer_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($l = $res->fetch_assoc()) {
            $fullname = $l['name'];
        }
    }
    
    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $conn->prepare("INSERT INTO users (username, full_name, role, department, password_hash, lecturer_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $username, $fullname, $role, $department, $hash, $lecturer_id);
        
        if ($stmt->execute()) {
            echo "<script>
                window.addEventListener('load', async () => {
                    await customAlert('User Created', 'New user account has been successfully registered.', 'success');
                    window.location.href='users.php';
                });
            </script>";
        } else {
            $error = "Error: " . $stmt->error;
        }
    } catch (Exception $e) {
        $error = "Error creating user: " . $e->getMessage(); 
    }
}
?>

<div class="glass-panel" style="padding: 2rem; max-width: 500px; margin: 0 auto;">
    <h2 style="margin-bottom: 2rem;">Add New User</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Role Selection -->
        <div class="form-group">
            <label style="display:block; margin-bottom: 0.5rem; color: var(--text-muted);">Role</label>
            <select name="role" id="roleSelect" class="glass-input" onchange="toggleFields()" required>
                <option value="student">Student</option>
                <option value="lecturer">Lecturer</option>
                <option value="faculty_admin">Faculty Admin</option>
                <?php if($_SESSION['role'] === 'super_admin'): ?>
                    <option value="super_admin">Super Admin</option>
                <?php endif; ?>
            </select>
        </div>

        <!-- Conditional Fields -->
        <div class="form-group" id="deptField" style="display: none;">
            <label style="display:block; margin-bottom: 0.5rem; color: var(--text-muted);">Department</label>
            <?php if ($_SESSION['role'] === 'faculty_admin'): ?>
                 <input type="hidden" name="department" value="<?php echo htmlspecialchars($_SESSION['department']); ?>">
                 <div class="glass-input" style="background: rgba(255, 255, 255, 0.1); color: var(--text-muted); cursor: not-allowed;">
                    <i class="fa-solid fa-lock"></i> <?php echo htmlspecialchars($_SESSION['department']); ?> (Locked)
                </div>
            <?php else: ?>
                <select name="department" class="glass-input">
                    <option value="">Select Department...</option>
                    <option value="Computer Science">Computer Science</option>
                    <option value="Nursing">Nursing</option>
                    <option value="Theology">Theology</option>
                    <option value="Business">Business</option>
                    <option value="Education">Education</option>
                    <option value="General">General</option>
                </select>
            <?php endif; ?>
        </div>

        <div class="form-group" id="lecturerField" style="margin-top: 1rem; display: none;">
            <label style="display:block; margin-bottom: 0.5rem; color: var(--text-muted);">Link to Lecturer Profile</label>
            <select name="lecturer_id" class="glass-input" style="background: rgba(15,23,42,0.9);">
                <option value="">-- Select Lecturer --</option>
                <?php foreach($lecturers as $l): ?>
                    <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

                <option value="Computer Science">Computer Science</option>
                <option value="Nursing">Nursing</option>
                <option value="Theology">Theology</option>
                <option value="Business">Business</option>
                <option value="Education">Education</option>
            </select>
        </div>

        <div class="form-group" id="nameField">
            <label style="display:block; margin-bottom: 0.5rem; color: var(--text-muted);">Full Name</label>
            <input type="text" name="fullname" class="glass-input">
        </div>
        
        <div class="form-group" style="margin-top: 1rem;">
            <label style="display:block; margin-bottom: 0.5rem; color: var(--text-muted);">Username</label>
            <input type="text" name="username" class="glass-input" required>
        </div>
        
        <div class="form-group" style="margin-top: 1rem;">
            <label style="display:block; margin-bottom: 0.5rem; color: var(--text-muted);">Password</label>
            <input type="password" name="password" class="glass-input" required>
        </div>
        
        <button type="submit" class="glass-btn" style="width: 100%; margin-top: 2rem;">Create Account</button>
    </form>
</div>

<script>
function toggleFields() {
    const role = document.getElementById('roleSelect').value;
    const lecturerField = document.getElementById('lecturerField');
    const deptField = document.getElementById('deptField');
    const nameField = document.getElementById('nameField');
    
    if (role === 'lecturer') {
        lecturerField.style.display = 'block';
        deptField.style.display = 'none';
        nameField.style.display = 'none';
        document.querySelector('input[name="fullname"]').removeAttribute('required');
        document.querySelector('select[name="lecturer_id"]').setAttribute('required', 'required');
        document.querySelector('select[name="department"]').removeAttribute('required');
    } else if (role === 'faculty_admin') {
        lecturerField.style.display = 'none';
        deptField.style.display = 'block';
        nameField.style.display = 'block';
        document.querySelector('input[name="fullname"]').setAttribute('required', 'required');
        document.querySelector('select[name="lecturer_id"]').removeAttribute('required');
        document.querySelector('select[name="department"]').setAttribute('required', 'required');
    } else {
        lecturerField.style.display = 'none';
        deptField.style.display = 'none';
        nameField.style.display = 'block';
        document.querySelector('input[name="fullname"]').setAttribute('required', 'required');
        document.querySelector('select[name="lecturer_id"]').removeAttribute('required');
        document.querySelector('select[name="department"]').removeAttribute('required');
    }
}
// Run on load
toggleFields();
</script>

<?php include 'includes/footer.php'; ?>
