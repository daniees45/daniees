<?php
$page_title = 'Dashboard';
include 'includes/header.php';
require_once 'api/db.php';

// Quick stats
try {
    $course_count = $conn->query("SELECT COUNT(*) FROM courses")->fetch_row()[0];
    $room_count = $conn->query("SELECT COUNT(*) FROM rooms")->fetch_row()[0];
    $lecturer_count = $conn->query("SELECT COUNT(*) FROM lecturers")->fetch_row()[0];
    // Use try-catch for tables that might not handle init yet
} catch (Exception $e) {
    $course_count = 0;
    $room_count = 0;
    $lecturer_count = 0;
}
?>

<!-- Stats Row -->
<div class="stats-grid">
    <div class="glass-panel stat-card">
        <span class="stat-value"><?php echo $course_count; ?></span>
        <span class="stat-label">Total Courses</span>
    </div>
    
    <div class="glass-panel stat-card">
        <span class="stat-value"><?php echo $lecturer_count; ?></span>
        <span class="stat-label">Lecturers</span>
    </div>
    
    <div class="glass-panel stat-card">
        <span class="stat-value"><?php echo $room_count; ?></span>
        <span class="stat-label">Rooms Available</span>
    </div>
    
    <div class="glass-panel stat-card" style="border-right: 4px solid var(--warning);">
        <?php
        // Calculate dynamic efficiency
        $efficiency = 0;
        $csv_file = '../final_web_schedule.csv';
        if (file_exists($csv_file)) {
            $rows = file($csv_file);
            $total_classes = max(0, count($rows) - 1);
            $max_capacity = max(1, $room_count * 20); // 20 slots per room/week
            $efficiency = min(100, round(($total_classes / $max_capacity) * 100));
        }
        ?>
        <span class="stat-value"><?php echo $efficiency; ?>%</span>
        <span class="stat-label">AI Efficiency</span>
        <div style="font-size: 0.7rem; color: var(--text-muted);">Based on Room Utilization</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
    <!-- Main Chart -->
    <div class="glass-panel" style="padding: 1.5rem;">
        <h3 style="margin-bottom: 1rem;">Room Utilization</h3>
        <canvas id="roomChart" height="200"></canvas>
    </div>
    
    <!-- Quick Actions -->
    <div class="glass-panel" style="padding: 1.5rem;">
        <h3 style="margin-bottom: 1rem;">Quick Actions</h3>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <a href="generate.php" class="glass-btn" style="text-align: center; text-decoration: none;">
                <i class="fa-solid fa-play"></i> Generate Schedule
            </a>
            
            <a href="courses.php" class="glass-btn secondary" style="text-align: center; text-decoration: none;">
                Manage Courses
            </a>
            
            <a href="users.php" class="glass-btn secondary" style="text-align: center; text-decoration: none;">
                System Users
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('roomChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['CS Labs', 'General', 'Nursing', 'Theology'],
            datasets: [{
                label: 'Occupancy Rate (%)',
                data: [85, 60, 40, 30],
                backgroundColor: [
                    'rgba(79, 70, 229, 0.6)',
                    'rgba(236, 72, 153, 0.6)',
                    'rgba(16, 185, 129, 0.6)',
                    'rgba(245, 158, 11, 0.6)'
                ],
                borderColor: [
                    '#4f46e5', '#ec4899', '#10b981', '#f59e0b'
                ],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.1)' },
                    ticks: { color: '#94a3b8' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8' }
                }
            },
            plugins: {
                legend: { labels: { color: '#f8fafc' } }
            },
            responsive: true
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
