<?php
$page_title = 'Import Data';
include 'includes/header.php';
require_once 'api/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    
    // 1. Import Rooms
    if (($handle = fopen("../rooms.csv", "r")) !== FALSE) {
        $conn->query("TRUNCATE TABLE rooms"); // Reset for sync
        fgetcsv($handle); // Skip header
        $stmt = $conn->prepare("INSERT INTO rooms (room_name, capacity) VALUES (?, ?)");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $name = $data[0] ?? 'Unknown';
            $cap = $data[1] ?? 50;
            $stmt->bind_param("si", $name, $cap);
            try { $stmt->execute(); } catch(Exception $e){}
        }
        fclose($handle);
    }
    
    // 2. Import Lecturers (From Availability)
    if (($handle = fopen("../lecturer_availability.csv", "r")) !== FALSE) {
        // $conn->query("TRUNCATE TABLE lecturers"); // Don't truncate if we want to keep IDs
        fgetcsv($handle); // Skip header
        $stmt = $conn->prepare("INSERT INTO lecturers (name, availability_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE availability_json = VALUES(availability_json)");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $name = $data[0] ?? '';
            if(!$name) continue;
            
            // Build simple availability JSON: [0, 1, 1, 1, 0] (Indices of days)
            $avail = [];
            // Mon(1), Tue(2), Wed(3), Thu(4), Fri(5) in csv indices
            if(($data[1]??0) == 1) $avail[] = 0;
            if(($data[2]??0) == 1) $avail[] = 1;
            if(($data[3]??0) == 1) $avail[] = 2;
            if(($data[4]??0) == 1) $avail[] = 3;
            if(($data[5]??0) == 1) $avail[] = 4;
            
            $json = json_encode($avail);
            $stmt->bind_param("ss", $name, $json);
            $stmt->execute();
        }
        fclose($handle);
    }
    
    // 3. Import Courses and create Sections
    if (($handle = fopen("../departmental_courses.csv", "r")) !== FALSE) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query("TRUNCATE TABLE sections");
        $conn->query("TRUNCATE TABLE courses");
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        fgetcsv($handle);
        
        // Cache lecturers for fast mapping
        $lecturers = [];
        $res = $conn->query("SELECT id, name FROM lecturers");
        while ($lr = $res->fetch_assoc()) { $lecturers[$lr['name']] = $lr['id']; }

        // Cache rooms for optional initial mapping
        $rooms = [];
        $res = $conn->query("SELECT id, room_name FROM rooms");
        while ($rr = $res->fetch_assoc()) { $rooms[$rr['room_name']] = $rr['id']; }

        $stmt = $conn->prepare("INSERT INTO courses (course_code, course_title, semester, type, level, credit_hours) VALUES (?, ?, ?, ?, ?, ?)");
        $s_stmt = $conn->prepare("INSERT INTO sections (course_id, lecturer_id, room_id, assigned_day, assigned_time) VALUES (?, ?, ?, ?, ?)");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // course_code,course_title,lecturer_name,Semester,day,start_time,end_time,room_name,source_type,course_level,credit_hours
            $code = $data[0] ?? '';
            if (!$code) continue;
            
            $title = $data[1] ?? '';
            $lecturer_name = $data[2] ?? '';
            $sem = $data[3] ?? '1';
            $assigned_day = $data[4] ?? null;
            $assigned_time = $data[5] ?? null;
            $room_name = $data[7] ?? null;
            $type = $data[8] ?? 'Departmental';
            $level = $data[9] ?? 100;
            $credits = $data[10] ?? 3;
            
            // Insert course
            $stmt->bind_param("ssssss", $code, $title, $sem, $type, $level, $credits);
            try { 
                $stmt->execute(); 
                $course_id = $conn->insert_id;
                
                // If we have a lecturer, create a section
                if ($lecturer_name && isset($lecturers[$lecturer_name])) {
                    $l_id = $lecturers[$lecturer_name];
                    $r_id = ($room_name && isset($rooms[$room_name])) ? $rooms[$room_name] : null;
                    $s_stmt->bind_param("iiiss", $course_id, $l_id, $r_id, $assigned_day, $assigned_time);
                    $s_stmt->execute();
                }
            } catch(Exception $e) {
                // Skip duplicates or errors
            }
        }
        fclose($handle);
    }
    
    $message = "Import Successful! Database synced with CSV files.";
}
?>

<div class="glass-panel" style="padding: 2rem; max-width: 800px; margin: 0 auto;">
    <h2 style="margin-bottom: 1rem; text-align: center;"><i class="fa-solid fa-file-csv"></i> Manage Data</h2>
    <p style="color: var(--text-muted); margin-bottom: 2rem; text-align: center;">
        Directly edit CSV files or synchronize them with the database.
    </p>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="data-actions" style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- 1. PDF Extraction & Cleanup Wizard -->
        <div style="background: rgba(99, 102, 241, 0.1); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(99, 102, 241, 0.2);">
            <h3 style="margin-bottom: 1rem; color: #818cf8;"><i class="fa-solid fa-wand-magic-sparkles"></i> Data Processing Wizard (ETL)</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <!-- Extraction -->
                <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px;">
                    <h4 style="font-size: 0.95rem; margin-bottom: 0.5rem;">1. Extract PDF</h4>
                    <form id="extractForm" enctype="multipart/form-data">
                        <input type="file" name="pdf_file" class="glass-input" style="font-size: 0.8rem; margin-bottom: 0.5rem;" accept=".pdf">
                        <input type="text" name="filename" class="glass-input" placeholder="Save as (e.g. vvu_raw.csv)" style="font-size: 0.8rem; margin-bottom: 0.5rem;">
                        <button type="submit" class="glass-btn secondary small" style="width: 100%"><i class="fa-solid fa-file-export"></i> Extract & Save to DB</button>
                    </form>
                </div>
                <!-- Cleanup -->
                <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px;">
                    <h4 style="font-size: 0.95rem; margin-bottom: 0.5rem;">2. Clean Raw Data</h4>
                    <div style="margin-bottom: 0.5rem;">
                        <input type="text" id="cleanInput" class="glass-input" placeholder="Input (e.g. vvu_raw.csv)" style="font-size: 0.8rem; margin-bottom: 0.5rem;">
                        <input type="text" id="cleanOutput" class="glass-input" placeholder="Output (e.g. clean_data.csv)" style="font-size: 0.8rem; margin-bottom: 0.5rem;">
                    </div>
                    <button onclick="runCleanup()" id="cleanupBtn" class="glass-btn secondary small" style="width: 100%"><i class="fa-solid fa-broom"></i> Clean & Save to DB</button>
                </div>
            </div>
            <div id="wizardLog" style="margin-top: 10px; font-family: monospace; font-size: 0.75rem; max-height: 120px; overflow-y: auto; background: rgba(0,0,0,0.3); padding: 8px; border-radius: 6px; display: none;"></div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- 2. Core Scheduling Files -->
            <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                <h3 style="margin-bottom: 1rem; font-size: 1.1rem;"><i class="fa-solid fa-table"></i> Core Files</h3>
                <div class="file-grid">
                    <div class="file-item">
                        <span>Rooms</span>
                        <a href="edit_csv.php?file=rooms.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>Lecturer Availability</span>
                        <a href="edit_csv.php?file=lecturer_availability.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>Main Courses</span>
                        <a href="edit_csv.php?file=departmental_courses.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>Special Room Locks</span>
                        <a href="edit_csv.php?file=special_rooms.csv" class="edit-link">Edit</a>
                    </div>
                </div>
                <h4 style="font-size: 0.85rem; margin-top: 1rem; color: var(--text-muted);">Departmental Specific Rooms</h4>
                <div class="file-grid">
                    <div class="file-item">
                        <span>CS Rooms</span>
                        <a href="edit_csv.php?file=computing_science_rooms.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>Nursing Rooms</span>
                        <a href="edit_csv.php?file=nursing_rooms.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>Theology Rooms</span>
                        <a href="edit_csv.php?file=theology_rooms.csv" class="edit-link">Edit</a>
                    </div>
                </div>
            </div>

            <!-- 3. AI Knowledge & Rules -->
            <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                <h3 style="margin-bottom: 1rem; font-size: 1.1rem;"><i class="fa-solid fa-brain"></i> AI Rules & Categorization</h3>
                <div class="file-grid">
                    <div class="file-item">
                        <span>Curriculum Mapping</span>
                        <a href="edit_csv.php?file=curriculum.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>General Prefixes</span>
                        <a href="edit_csv.php?file=general_courses.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>Dept Keywords</span>
                        <a href="edit_csv.php?file=dept.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>General Schedule Blocks</span>
                        <a href="edit_csv.php?file=vvu_general_schedule.csv" class="edit-link">Edit</a>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- 4. Level Data & History -->
            <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                <h3 style="margin-bottom: 1rem; font-size: 1.1rem;"><i class="fa-solid fa-history"></i> History & Training</h3>
                <div class="file-grid">
                    <div class="file-item">
                        <span>Historical Schedules</span>
                        <a href="edit_csv.php?file=historical_schedule.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>User Feedback</span>
                        <a href="edit_csv.php?file=user_feedback.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>Level 100 Data</span>
                        <a href="edit_csv.php?file=level_100.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>Level 200 Data</span>
                        <a href="edit_csv.php?file=level_200.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>Level 300 Data</span>
                        <a href="edit_csv.php?file=level_300.csv" class="edit-link">Edit</a>
                    </div>
                    <div class="file-item">
                        <span>Level 400 Data</span>
                        <a href="edit_csv.php?file=level_400.csv" class="edit-link">Edit</a>
                    </div>
                </div>
            </div>

            <!-- 5. System Actions -->
            <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                <h3 style="margin-bottom: 1rem; font-size: 1.1rem;"><i class="fa-solid fa-cogs"></i> System Sync</h3>
                <form method="POST" style="margin-bottom: 1rem;">
                    <button type="submit" name="import" class="glass-btn secondary small" style="width: 100%;">
                        <i class="fa-solid fa-sync"></i> Re-Sync DB from CSVs
                    </button>
                </form>
                <button onclick="runAnalysis()" id="analyzeBtn" class="glass-btn secondary small" style="width: 100%;">
                    <i class="fa-solid fa-microchip"></i> Run AI Analysis
                </button>
                <div style="margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px;">
                    <button onclick="runValidation()" id="validateBtn" class="glass-btn small" style="width: 100%; background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4);">
                        <i class="fa-solid fa-check-double"></i> Pre-flight Data Check
                    </button>
                </div>
                <div id="analysisLog" style="margin-top: 10px; font-family: monospace; font-size: 0.75rem; max-height: 120px; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 8px; padding: 10px; display: none;"></div>
            </div>
        </div>
    </div>
</div>

<script>
// Wizard Actions
document.getElementById('extractForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const log = document.getElementById('wizardLog');
    log.style.display = 'block';
    log.innerHTML = 'Starting extraction...\n';
    
    const formData = new FormData(e.target);
    try {
        const res = await fetch('api/extract_pdf.php', { method: 'POST', body: formData });
        const data = await res.json();
        log.innerHTML += (data.output || data.message) + '\n';
        if(data.status === 'success') log.innerHTML += 'Success: ' + data.message;
    } catch (err) { log.innerHTML += 'Error: ' + err.message; }
});

async function runCleanup() {
    const log = document.getElementById('wizardLog');
    const inputName = document.getElementById('cleanInput').value;
    const outputName = document.getElementById('cleanOutput').value;
    
    log.style.display = 'block';
    log.innerHTML = 'Running cleanup...\n';
    
    const formData = new FormData();
    formData.append('input_filename', inputName);
    formData.append('output_filename', outputName);

    try {
        const res = await fetch('api/cleanup_data.php', { method: 'POST', body: formData });
        const data = await res.json();
        log.innerHTML += (data.output || data.message) + '\n';
        if(data.status === 'success') log.innerHTML += 'Success: ' + data.message;
    } catch (err) { log.innerHTML += 'Error: ' + err.message; }
}

async function runAnalysis() {
    const btn = document.getElementById('analyzeBtn');
    const log = document.getElementById('analysisLog');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Analyzing...';
    log.style.display = 'block';
    log.innerHTML = 'Starting analysis...\n';

    try {
        const response = await fetch('api/run_analyzer.php');
        const result = await response.json();
        
        log.innerHTML += (result.output || result.message) + '\n';
        if (result.status === 'success') {
            log.innerHTML += 'Analysis complete successfully!';
        } else {
            log.innerHTML += 'Error: ' + result.message;
        }
    } catch (error) {
        log.innerHTML += 'Failed: ' + error.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-microchip"></i> Run AI Analysis';
    }
}

async function runValidation() {
    const btn = document.getElementById('validateBtn');
    const log = document.getElementById('analysisLog');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
    log.style.display = 'block';
    log.innerHTML = 'Running Pre-flight Checks...\n';

    try {
        const response = await fetch('api/validate_data.php');
        const result = await response.json();
        
        if (result.status === 'success') {
            log.innerHTML += result.message + '\n';
            if (result.issues.length > 0) {
                log.innerHTML += '\n[CRITICAL ISSUES]\n';
                result.issues.forEach(i => log.innerHTML += '❌ ' + i + '\n');
            }
            if (result.warnings.length > 0) {
                log.innerHTML += '\n[WARNINGS]\n';
                result.warnings.forEach(w => log.innerHTML += '⚠️ ' + w + '\n');
            }
            if (result.issues.length === 0 && result.warnings.length === 0) {
                log.innerHTML += '✅ All systems go!';
            }
        } else {
            log.innerHTML += 'Error: ' + result.message;
        }
    } catch (error) {
        log.innerHTML += 'Failed: ' + error.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check-double"></i> Pre-flight Data Check';
    }
}
</script>



<?php include 'includes/footer.php'; ?>
