<?php
$page_title = 'AI Schedule Generator';
include 'includes/header.php';
require_once 'api/db.php';

// Access Control
requireRole(['super_admin', 'faculty_admin']);

// Scan for potential input CSVs
$root_path = realpath('../');
$csv_files = glob($root_path . "/*.csv");
$input_options = [];
foreach ($csv_files as $file) {
    $bn = basename($file);
    // Filter out output files or system files to avoid confusion
    if ($bn != 'final_web_schedule.csv' && $bn != 'lecturer_availability.csv' && $bn != 'special_rooms.csv') {
        $input_options[] = $bn;
    }
}
// Default to departmental_courses.csv if exists
$default_csv = 'departmental_courses.csv';
?>

<div class="glass-panel" style="padding: 2rem; max-width: 800px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 2rem;">
        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 0 20px rgba(99, 102, 241, 0.5);">
            <i class="fa-solid fa-wand-magic-sparkles" style="font-size: 2rem; color: white;"></i>
        </div>
        <h2>Generate Schedule</h2>
        <p style="color: var(--text-muted);">Use the AI engine to optimally assign courses to rooms and times slots.</p>
        
        <div id="apiStatus" class="status-badge checking" style="margin-top: 1rem; display: inline-block; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
            <i class="fa-solid fa-circle-notch fa-spin"></i> Checking AI Engine...
        </div>
    </div>

    <!-- Configuration Form -->
    <div id="configStep">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            
            <div class="form-group" style="grid-column: span 2;">
                <label style="display: block; margin-bottom: 0.5rem; color: #a855f7; font-weight: 600;">Schedule Type</label>
                <div style="display: flex; gap: 1rem;">
                    <label class="glass-input" style="flex: 1; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                        <input type="radio" name="scheduleType" value="class" checked onchange="toggleExamMode()"> 
                        <span><i class="fa-solid fa-chalkboard-user"></i> Class Timetable</span>
                    </label>
                    <label class="glass-input" style="flex: 1; cursor: pointer; display: flex; align-items: center; gap: 10px;">
                        <input type="radio" name="scheduleType" value="exam" onchange="toggleExamMode()"> 
                        <span><i class="fa-solid fa-file-pen"></i> Examination Timetable</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label style="display: block; margin-bottom: 0.5rem;">Input Data Source</label>
                <div style="display: flex; gap: 10px;">
                    <select id="inputFile" class="glass-input" style="background: rgba(15, 23, 42, 0.8); color: white; flex: 1;">
                        <?php foreach($input_options as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php if($opt == $default_csv) echo 'selected'; ?>><?php echo $opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="document.getElementById('csvUpload').click()" class="glass-btn secondary" title="Upload New CSV">
                        <i class="fa-solid fa-upload"></i>
                    </button>
                    <input type="file" id="csvUpload" style="display: none;" accept=".csv" onchange="uploadCSV(this)">
                </div>
            </div>

            <div class="form-group">
                <label style="display: block; margin-bottom: 0.5rem;">Academic Semester</label>
                <select id="semester" class="glass-input" style="background: rgba(15, 23, 42, 0.8); color: white;">
                    <option value="1">First Semester</option>
                    <option value="2">Second Semester</option>
                </select>
            </div>

            <!-- New Logic Fields -->
            <div class="form-group">
                <label style="display: block; margin-bottom: 0.5rem;">Course Category</label>
                <select id="courseType" class="glass-input" style="background: rgba(15, 23, 42, 0.8); color: white;" onchange="toggleDept()">
                    <option value="Departmental">Departmental Courses</option>
                    <option value="General">General Courses</option>
                </select>
            </div>

            <div class="form-group" id="deptGroup">
    <label style="display: block; margin-bottom: 0.5rem;">Target Department</label>
    <?php if ($_SESSION['role'] === 'faculty_admin'): 
        // Map session department to ID if possible
        $dept_map = [
            'Computer Science' => '1', 'CS' => '1', 'CS/IT' => '1',
            'Nursing' => '2', 'Nursing & Midwifery' => '2',
            'Theology' => '3',
            'General' => '4'
        ];
        $my_dept = $_SESSION['department'] ?? '1';
        $my_dept_id = $dept_map[$my_dept] ?? '1'; 
    ?>
        <input type="hidden" id="department" value="<?php echo $my_dept_id; ?>">
        <div class="glass-input" style="background: rgba(255, 255, 255, 0.1); color: var(--text-muted); cursor: not-allowed;">
            <i class="fa-solid fa-lock"></i> <?php echo htmlspecialchars($my_dept); ?> (Locked)
        </div>
    <?php else: ?>
        <select id="department" class="glass-input" style="background: rgba(15, 23, 42, 0.8); color: white;">
            <option value="1">Computer Science (CS/IT)</option>
            <option value="2">Nursing & Midwifery</option>
            <option value="3">Theology</option>
            <option value="4">Others / General Pool</option>
        </select>
    <?php endif; ?>
</div>            

            <div class="form-group">
                <label style="display: block; margin-bottom: 0.5rem;">Availability Strategy</label>
                <select id="availabilityMode" class="glass-input" style="background: rgba(15, 23, 42, 0.8); color: white;">
                    <option value="1">AI Automatic (Auto-Expand Limited)</option>
                    <option value="2">Strict (Use File Data Only)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label style="display: block; margin-bottom: 0.5rem;">Output Filename</label>
                <input type="text" id="outputFile" class="glass-input" value="final_web_schedule.csv" placeholder="e.g. final_web_schedule.csv">
            </div>
            
            <div class="form-group">
                <label style="display: block; margin-bottom: 0.5rem;">Optimization Mode</label>
                <select id="optMode" class="glass-input" style="background: rgba(15, 23, 42, 0.8); color: white;">
                    <option value="balance">Balanced Load</option>
                    <option value="capacity">Maximize Capacity</option>
                    <option value="lecturer">Lecturer Preference</option>
                </select>
            </div>
        </div>

        <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 2rem;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;"><i class="fa-solid fa-file-invoice"></i> Review Input Data</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                Ensure your course assignments and preferences are correct before starting.
            </p>
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="editSelected()" class="glass-btn secondary small" style="padding: 0.5rem 1rem !important; font-size: 0.8rem !important;">
                    <i class="fa-solid fa-pencil"></i> Edit Selected CSV
                </button>
                <a href="import_data.php" class="glass-btn secondary small" style="padding: 0.5rem 1rem !important; font-size: 0.8rem !important;">
                    <i class="fa-solid fa-cog"></i> Advanced Settings
                </a>
            </div>
        </div>

        <button id="startBtn" class="glass-btn" style="width: 100%; padding: 1rem; font-size: 1.1rem;">
            <i class="fa-solid fa-play"></i> Start Generation
        </button>
    </div>

    <!-- ... (Progress Step) ... --> 
    <!-- REMOVED to simplify target matching, will only replace config block --> 
    <!-- WAIT, I need to update the JS fetch call too. -->

    <!-- Progress State (Hidden by default) -->
    <div id="progressStep" style="display: none; text-align: center;">
        <div class="loader" style="margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-notch fa-spin" style="font-size: 3rem; color: var(--primary-color);"></i>
        </div>
        
        <!-- Progress Bar -->
        <div style="width: 100%; max-width: 400px; height: 10px; background: rgba(255,255,255,0.1); border-radius: 5px; margin: 0 auto 1rem; overflow: hidden;">
            <div id="progressBar" style="width: 0%; height: 100%; background: var(--primary); transition: width 0.3s;"></div>
        </div>
        <div id="progressStats" style="font-family: monospace; color: var(--text-muted); margin-bottom: 1rem;">0%</div>
        <h3 id="statusText">Initializing AI Engine...</h3>
        <p style="color: var(--text-muted); margin-bottom: 1rem;">This may take a few minutes.</p>
        
        <div class="glass-panel" style="background: rgba(0,0,0,0.3); text-align: left; padding: 1rem; height: 150px; overflow-y: auto; font-family: monospace; font-size: 0.9rem;" id="logs">
            <div style="color: var(--text-muted);">> System ready.</div>
        </div>
    </div>
    
    <!-- Success State (Hidden) -->
    <div id="successStep" style="display: none; text-align: center;">
        <div style="color: var(--success); font-size: 4rem; margin-bottom: 1rem;">
            <i class="fa-regular fa-circle-check"></i>
        </div>
        <h3>Generation Complete!</h3>
        <div id="accuracyBadge" style="display: inline-block; background: rgba(16, 185, 129, 0.2); color: #10b981; padding: 5px 15px; border-radius: 20px; font-weight: 700; margin: 10px 0; border: 1px solid rgba(16, 185, 129, 0.4);">
            Accuracy: <span id="accuracyVal">0%</span>
        </div>
        <p style="margin-bottom: 2rem;">The schedule has been successfully generated and exported.</p>
        <div style="display: flex; justify-content: center; gap: 1rem;">
            <a href="view_schedule.php" id="viewScheduleBtn" class="glass-btn"><i class="fa-solid fa-calendar-days"></i> View Schedule</a>
            <a href="#" id="downloadPdfBtn" class="glass-btn secondary" style="display: none;"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
        </div>
    </div>
</div>

<script>
function toggleDept() {
    const type = document.getElementById('courseType').value;
    const deptDiv = document.getElementById('deptGroup');
    if (type === 'General') {
        deptDiv.style.opacity = '0.5';
        deptDiv.style.pointerEvents = 'none';
        document.getElementById('department').value = '4'; 
    } else {
        deptDiv.style.opacity = '1';
        deptDiv.style.pointerEvents = 'auto';
    }
}

function toggleExamMode() {
    // Check if the element exists to avoid errors on page load if incomplete
    const radio = document.querySelector('input[name="scheduleType"]:checked');
    if (!radio) return;

    const isExam = radio.value === 'exam';
    const outFile = document.getElementById('outputFile');
    const startBtn = document.getElementById('startBtn');
    
    if (isExam) {
        outFile.value = 'exam_schedule_draft.csv';
        startBtn.innerHTML = '<i class="fa-solid fa-file-pen"></i> Generate Exam Timetable';
        document.getElementById('availabilityMode').value = '2';
    } else {
        outFile.value = 'final_web_schedule.csv';
        startBtn.innerHTML = '<i class="fa-solid fa-play"></i> Start Generation';
    }
}

function editSelected() {
    const file = document.getElementById('inputFile').value;
    window.open('edit_csv.php?file=' + file, '_blank');
}

async function uploadCSV(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('csv_file', file);
    
    try {
        const response = await fetch('api/upload_csv.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.status === 'success') {
            await customAlert('Upload Success', data.message, 'success');
            const select = document.getElementById('inputFile');
            const option = document.createElement('option');
            option.value = file.name;
            option.text = file.name;
            select.add(option);
            select.value = file.name;
        } else {
            await customAlert('Upload Error', data.message, 'error');
        }
    } catch (e) {
        await customAlert('Error', 'Failed to upload file.', 'error');
    }
}

async function checkApiStatus() {
    const badge = document.getElementById('apiStatus');
    try {
        const res = await fetch('https://my-ai-service-yj44.onrender.com/health');
        const data = await res.json();
        if (data.status === 'ok') {
            badge.className = 'status-badge online';
            badge.innerHTML = '<i class="fa-solid fa-check-circle"></i> AI Engine Online';
        } else {
            throw new Error();
        }
    } catch (e) {
        badge.className = 'status-badge offline';
        badge.innerHTML = '<i class="fa-solid fa-times-circle"></i> AI Engine Offline (Port 5000)';
    }
}

// Initial checks
checkApiStatus();
setInterval(checkApiStatus, 30000);

document.getElementById('startBtn').addEventListener('click', async () => {
    // 1. UI Switch
    document.getElementById('configStep').style.display = 'none';
    document.getElementById('progressStep').style.display = 'block';
    
    const logs = document.getElementById('logs');
    const log = (msg) => {
        const div = document.createElement('div');
        div.textContent = `> ${msg}`;
        logs.appendChild(div);
        logs.scrollTop = logs.scrollHeight;
    };
    
    try {
        // 0. Import CSV changes to DB
        log("Importing recent CSV edits...");
        await fetch('api/import_before_gen.php');
        
        // 1. Sync DB to CSV
        log("Synchronizing database data...");
        const syncRes = await fetch('api/sync.php');
        const syncData = await syncRes.json();
        if (syncData.status !== 'success') throw new Error(syncData.message);
        log("Database synced to CSV.");

        // 2. Call Flask API
        log("Initializing AI engine...");
        
        // Start Polling
        let pollInterval = setInterval(async () => {
            try {
                const res = await fetch('https://my-ai-service-yj44.onrender.com/progress?t=' + new Date().getTime());
                const p = await res.json();
                if (p.status === 'running' && p.percent) {
                    document.getElementById('progressBar').style.width = p.percent + '%';
                    document.getElementById('progressStats').innerText = `Placing: ${p.placed} (${p.percent}%)`;
                    document.getElementById('statusText').innerText = "AI Solving Constraints...";
                }
            } catch(e) {}
        }, 1000);

        const response = await fetch('https://my-ai-service-yj44.onrender.com/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                semester: document.getElementById('semester').value,
                input_file: document.getElementById('inputFile').value,
                output_file: document.getElementById('outputFile').value,
                course_type: document.getElementById('courseType').value,
                department: document.getElementById('department').value,
                availability_mode: document.getElementById('availabilityMode').value,
                exam_mode: document.querySelector('input[name="scheduleType"]:checked').value === 'exam'
            })
        });
        
        const data = await response.json();
        clearInterval(pollInterval);
        document.getElementById('progressBar').style.width = '100%';
        
        if (data.status === 'success') {
            log(`AI solved the schedule successfully. Accuracy: ${data.accuracy}`);
            document.getElementById('accuracyVal').innerText = data.accuracy;
            
            // 3. Update DB from CSV
            log("Importing results back to database...");
            const updateRes = await fetch('api/update_db.php?file=' + document.getElementById('outputFile').value);
            const updateData = await updateRes.json();
            if (updateData.status !== 'success') throw new Error(updateData.message);
            log("Database updated with new schedule.");

            // 4. Auto-Version Original File
            log("Saving version to storage...");
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('file_name', document.getElementById('outputFile').value);
            formData.append('name', 'Auto Version: ' + document.getElementById('outputFile').value);
            formData.append('description', 'AI Generated at ' + new Date().toLocaleString());
            
            const versionRes = await fetch('api/schedule_versions.php', {
                method: 'POST',
                body: formData
            });
            const versionData = await versionRes.json();
            if (versionData.status === 'success' && versionData.id) {
                const pdfBtn = document.getElementById('downloadPdfBtn');
                pdfBtn.href = 'api/download_pdf.php?id=' + versionData.id;
                pdfBtn.style.display = 'inline-flex';
            }
            log("Version archived in database.");

            log("Success! Redirecting...");
            setTimeout(() => {
                document.getElementById('progressStep').style.display = 'none';
                document.getElementById('successStep').style.display = 'block';
                
                // Update View Button
                const outFile = document.getElementById('outputFile').value;
                document.getElementById('viewScheduleBtn').href = 'view_schedule.php?file=' + encodeURIComponent(outFile);
            }, 1000);
        } else {
            log(`AI Error: ${data.message}`);
            document.getElementById('statusText').textContent = "Generation Failed";
            document.getElementById('statusText').style.color = "var(--danger)";
        }
    } catch (e) {
        log(`Error: ${e.message}`);
        log("Troubleshooting tips:");
        log("- Ensure Python flask is running");
        document.getElementById('statusText').textContent = "System Error";
        document.getElementById('statusText').style.color = "var(--danger)";
    }
});
</script>



<?php include 'includes/footer.php'; ?>
