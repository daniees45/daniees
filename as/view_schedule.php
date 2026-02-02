<?php
$page_title = 'View Schedule';
include 'includes/header.php';

// Path to the generated schedule
// Validate requested file or default
$requested_file = $_GET['file'] ?? 'final_web_schedule.csv';

// Security Check: Allow only alphanumeric/dash/underscore with .csv extension
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $requested_file) || pathinfo($requested_file, PATHINFO_EXTENSION) !== 'csv') {
    $requested_file = 'final_web_schedule.csv'; // Fallback if invalid
}

// Path to the generated schedule (Parent directory only)
$csv_file = realpath('../' . $requested_file);
$base_dir = realpath('../');

// Directory traversal protection
if (!$csv_file || strpos($csv_file, $base_dir) !== 0 || !file_exists($csv_file)) {
    $csv_file = '../final_web_schedule.csv'; // Fallback if not found or unsafe
}
$data = [];

// Parse CSV if exists
if (file_exists($csv_file)) {
    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        $headers = fgetcsv($handle); // "Course Code","Course Title",...
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Map row to associative array for easier filtering
            // CSV columns: Course Code, Course Title, Credit Hrs, Lecturer Name, Room Name, Day, Time
            $data[] = [
                'code' => $row[0] ?? '',
                'title' => $row[1] ?? '',
                // 'credits' => $row[2] ?? '',
                'lecturer' => $row[3] ?? '',
                'room' => $row[4] ?? '',
                'day' => $row[5] ?? '',
                'time' => $row[6] ?? ''
            ];
        }
        fclose($handle);
    }
}

// Filtering
$filter_lecturer = $_GET['lecturer'] ?? '';
$filter_room = $_GET['room'] ?? '';
$filter_day = $_GET['day'] ?? '';

if ($filter_lecturer) {
    $data = array_filter($data, function($item) use ($filter_lecturer) {
        return stripos($item['lecturer'], $filter_lecturer) !== false;
    });
}
if ($filter_room) {
    $data = array_filter($data, function($item) use ($filter_room) {
        return stripos($item['room'], $filter_room) !== false;
    });
}
if ($filter_day) {
    $data = array_filter($data, function($item) use ($filter_day) {
        return stripos($item['day'], $filter_day) !== false;
    });
}

// Extract unique values for filters
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Pagination
$items_per_page = 20;
$total_items = count($data);
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($total_pages, (int)($_GET['page'] ?? 1)));
$offset = ($current_page - 1) * $items_per_page;

// Sort by Day then Time before slicing
$day_order = array_flip($days);
usort($data, function($a, $b) use ($day_order) {
    $da = $day_order[$a['day']] ?? 99;
    $db = $day_order[$b['day']] ?? 99;
    if ($da != $db) return $da - $db;
    return strcmp($a['time'], $b['time']);
});

$paged_data = array_slice($data, $offset, $items_per_page);
?>

<div style="max-width: 1200px; margin: 0 auto;">
    
    <!-- Header & Controls -->
    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        
        <!-- Filters Card -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 1rem; color: var(--primary-color);">
                <i class="fa-solid fa-filter"></i>
                <h3 style="margin: 0; font-size: 1.1rem;">Filter Schedule</h3>
            </div>
            
            <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <div>
                    <label class="stat-label" style="font-size: 0.8rem;">Lecturer</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-user-tie" style="position: absolute; left: 10px; top: 12px; color: var(--text-muted); font-size: 0.9rem;"></i>
                        <input type="text" name="lecturer" class="glass-input" style="padding-left: 35px;" placeholder="Search Name..." value="<?php echo htmlspecialchars($filter_lecturer); ?>">
                    </div>
                </div>
                <div>
                    <label class="stat-label" style="font-size: 0.8rem;">Room</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-location-dot" style="position: absolute; left: 10px; top: 12px; color: var(--text-muted); font-size: 0.9rem;"></i>
                        <input type="text" name="room" class="glass-input" style="padding-left: 35px;" placeholder="Search Room..." value="<?php echo htmlspecialchars($filter_room); ?>">
                    </div>
                </div>
                <div>
                    <label class="stat-label" style="font-size: 0.8rem;">Day</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-calendar-day" style="position: absolute; left: 10px; top: 12px; color: var(--text-muted); font-size: 0.9rem;"></i>
                        <select name="day" class="glass-input" style="padding-left: 35px; background: rgba(15, 23, 42, 0.8);">
                            <option value="">All Days</option>
                            <?php foreach($days as $d): ?>
                                <option value="<?php echo $d; ?>" <?php if($filter_day == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button type="submit" class="glass-btn primary" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-magnifying-glass"></i> Apply
                    </button>
                </div>
            </form>
        </div>

        <!-- Versions Card -->
        <div class="glass-panel" style="padding: 1.5rem; display: flex; flex-direction: column;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; color: var(--warning);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-code-branch"></i>
                    <h3 style="margin: 0; font-size: 1.1rem;">Versions</h3>
                </div>
                <button onclick="saveVersion()" class="glass-btn small" title="Save Current State"><i class="fa-solid fa-floppy-disk"></i></button>
            </div>
            
            <div style="flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 0.5rem;">
                <label class="stat-label" style="font-size: 0.8rem;">Restore Previous</label>
                <div style="display: flex; gap: 5px;">
                    <select id="versionSelect" class="glass-input" style="padding: 8px; font-size: 0.9rem; background: rgba(0,0,0,0.3);">
                        <option value="">Select Version...</option>
                    </select>
                    <button onclick="loadVersion()" class="glass-btn secondary small"><i class="fa-solid fa-rotate-left"></i></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Schedule Table -->
    <div class="glass-panel" style="padding: 0; overflow: hidden; position: relative;">
        <!-- Gradient Line Top -->
        <div style="height: 3px; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
        
        <div class="table-container" style="max-height: 700px; overflow-y: auto;">
        <?php if (empty($data)): ?>
            <div style="text-align: center; padding: 4rem 2rem; color: var(--text-muted); display: flex; flex-direction: column; align-items: center;">
                <?php if (!file_exists($csv_file)): ?>
                    <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
                        <i class="fa-solid fa-calendar-xmark" style="font-size: 2.5rem; opacity: 0.5;"></i>
                    </div>
                    <h3 style="margin-bottom: 0.5rem;">No Schedule Generated</h3>
                    <p style="margin-bottom: 2rem;">Use the AI Generator to create your first schedule.</p>
                    <a href="generate.php" class="glass-btn"><i class="fa-solid fa-wand-magic-sparkles"></i> Go to Generator</a>
                <?php else: ?>
                    <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
                        <i class="fa-solid fa-filter-circle-xmark" style="font-size: 2.5rem; opacity: 0.5;"></i>
                    </div>
                    <h3>No Classes Found</h3>
                    <p>Try adjusting your search filters.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.95rem;">
                <thead style="background: rgba(0,0,0,0.2); position: sticky; top: 0; z-index: 10; backdrop-filter: blur(5px);">
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-muted); border-bottom: 1px solid rgba(255,255,255,0.05);">Day</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-muted); border-bottom: 1px solid rgba(255,255,255,0.05);">Time</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-muted); border-bottom: 1px solid rgba(255,255,255,0.05);">Course</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-muted); border-bottom: 1px solid rgba(255,255,255,0.05);">Room</th>
                        <th style="padding: 1rem; text-align: left; font-weight: 600; color: var(--text-muted); border-bottom: 1px solid rgba(255,255,255,0.05);">Lecturer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paged_data as $idx => $row): 
                        $bg = $idx % 2 == 0 ? 'rgba(255,255,255,0.01)' : 'transparent';
                    ?>
                        <tr style="background: <?php echo $bg; ?>; border-bottom: 1px solid rgba(255,255,255,0.02); transition: background 0.2s;">
                            <td style="padding: 1rem;">
                                <span style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($row['day']); ?></span>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 20px; font-family: monospace; font-size: 0.85rem; color: var(--warning);">
                                    <i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars($row['time']); ?>
                                </div>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="font-weight: 700; color: var(--primary-color); margin-bottom: 3px;"><?php echo htmlspecialchars($row['code']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['title']); ?></div>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="color: #4ade80; display: flex; align-items: center; gap: 5px;">
                                    <i class="fa-solid fa-door-open" style="font-size: 0.8rem;"></i> <?php echo htmlspecialchars($row['room']); ?>
                                </div>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 25px; height: 25px; background: linear-gradient(45deg, #4f46e5, #ec4899); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: white;">
                                        <?php echo substr($row['lecturer'], 0, 1); ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($row['lecturer']); ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
            <div style="padding: 1rem; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: center; align-items: center; gap: 1rem;">
                <?php 
                    $params = $_GET;
                    function build_query($p, $params) {
                        $params['page'] = $p;
                        return '?' . http_build_query($params);
                    }
                ?>
                <a href="<?php echo build_query(max(1, $current_page - 1), $params); ?>" class="glass-btn secondary small <?php if($current_page <= 1) echo 'disabled'; ?>" style="<?php if($current_page <= 1) echo 'opacity: 0.5; pointer-events: none;'; ?>">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <span style="font-size: 0.9rem; color: var(--text-muted);">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                <a href="<?php echo build_query(min($total_pages, $current_page + 1), $params); ?>" class="glass-btn secondary small <?php if($current_page >= $total_pages) echo 'disabled'; ?>" style="<?php if($current_page >= $total_pages) echo 'opacity: 0.5; pointer-events: none;'; ?>">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
</div>



<script>
async function loadVersionList() {
    const select = document.getElementById('versionSelect');
    try {
        const formData = new FormData();
        formData.append('action', 'list');
        const res = await fetch('api/schedule_versions.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            select.innerHTML = '<option value="">Select Version...</option>';
            data.versions.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.id;
                opt.innerText = `${v.version_name} (${v.created_at.substring(5,16)})`;
                select.appendChild(opt);
            });
        }
    } catch (e) { console.error(e); }
}

async function saveVersion() {
    const name = await customPrompt("Save Version", "Enter a name for this version:");
    if (!name) return;
    
    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('name', name);
    
    try {
        const res = await fetch('api/schedule_versions.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            await customAlert("Version Saved", data.message, "success");
            loadVersionList();
        } else {
            await customAlert("Save Error", data.message, "error");
        }
    } catch (e) { await customAlert("Error", "Check console for details.", "error"); }
}

async function loadVersion() {
    const id = document.getElementById('versionSelect').value;
    if (!id) return;
    
    const confirmed = await customConfirm("Load Version?", "This will overwrite the current schedule displayed on the website. Continue?");
    if (!confirmed) return;

    const formData = new FormData();
    formData.append('action', 'load');
    formData.append('id', id);
    
    try {
        const res = await fetch('api/schedule_versions.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            await customAlert("Version Loaded", data.message, "success");
            location.reload();
        } else {
            await customAlert("Load Error", data.message, "error");
        }
    } catch (e) { await customAlert("Error", "Check console for details.", "error"); }
}

loadVersionList();
</script>

<?php include 'includes/footer.php'; ?>
