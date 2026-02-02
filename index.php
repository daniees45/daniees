<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VVU AI Scheduler - Central Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="as/assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #a855f7;
            --bg-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
        }
        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-dark);
            color: #f8fafc;
            line-height: 1.6;
            overflow-x: hidden;
        }
        .hero-section {
            min-height: 70vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 2rem;
            background: radial-gradient(circle at center, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
            position: relative;
        }
        .hero-section h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }
        @media (max-width: 768px) {
            .hero-section h1 { font-size: 2.5rem; }
        }
        .hero-subtitle {
            font-size: 1.25rem;
            color: #94a3b8;
            max-width: 700px;
            margin-bottom: 3rem;
        }
        .main-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: -100px auto 100px;
            padding: 0 2rem;
        }
        .feature-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 2.5rem;
            text-align: left;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            text-decoration: none;
            color: inherit;
        }
        .feature-card:hover {
            transform: translateY(-10px);
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            background: rgba(30, 41, 59, 0.9);
        }
        .icon {
            width: 60px;
            height: 60px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #818cf8;
        }
        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }
        .feature-card p {
            color: #94a3b8;
            font-size: 0.95rem;
            margin: 0;
        }
        .nav-header {
            width: 100%;
            padding: 1.5rem 0;
            display: flex;
            justify-content: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }
        .btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.4);
        }
        .footer {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
            font-size: 0.9rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(99, 102, 241, 0.1);
            color: #818cf8;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<div class="nav-header">
    <div style="font-weight: 800; font-size: 1.2rem; color: #f8fafc;">
        <i class="fa-solid fa-brain" style="color: #818cf8; margin-right: 8px;"></i> VVU AI SCHEDULER
    </div>
</div>

<header class="hero-section">
    <div class="badge">Next Generation Timetabling</div>
    <h1>Effortless Scheduling,<br>Powered by Intelligence.</h1>
    <p class="hero-subtitle">
        Automate complex academic scheduling with our advanced CSP-based AI engine. 
        Optimize lecturer assignments, room utilization, and student paths in seconds.
    </p>
    <a href="as/login.php" class="btn-primary">Access Dashboard <i class="fa-solid fa-arrow-right" style="margin-left: 10px;"></i></a>
</header>

<main class="main-grid">
    <!-- Public/Common Features -->
    <a href="as/view_schedule.php" class="feature-card">
        <div class="icon"><i class="fa-solid fa-calendar-days"></i></div>
        <h3>View Schedule</h3>
        <p>Check the latest finalized course timetables for the current semester.</p>
        <div style="color: #818cf8; font-weight: 600; font-size: 0.85rem;">Browse Now <i class="fa-solid fa-chevron-right" style="margin-left: 5px;"></i></div>
    </a>

    <a href="as/student_view.php" class="feature-card">
        <div class="icon"><i class="fa-solid fa-graduation-cap"></i></div>
        <h3>Student Portal</h3>
        <p>Login to view your personalized schedule based on your enrolled courses.</p>
        <div style="color: #818cf8; font-weight: 600; font-size: 0.85rem;">Personal View <i class="fa-solid fa-chevron-right" style="margin-left: 5px;"></i></div>
    </a>

    <!-- Admin/Lecturer Features -->
    <a href="as/generate.php" class="feature-card">
        <div class="icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <h3>AI Generation</h3>
        <p>Configure and run the AI engine to generate optimized conflict-free schedules.</p>
        <div style="color: #818cf8; font-weight: 600; font-size: 0.85rem;">Admin Only <i class="fa-solid fa-lock" style="margin-left: 5px;"></i></div>
    </a>

    <a href="as/lecturers.php" class="feature-card">
        <div class="icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
        <h3>Lecturer Availability</h3>
        <p>Manage your teaching preferences and available time slots for the semester.</p>
        <div style="color: #818cf8; font-weight: 600; font-size: 0.85rem;">Update Prefs <i class="fa-solid fa-chevron-right" style="margin-left: 5px;"></i></div>
    </a>

    <a href="as/import_data.php" class="feature-card">
        <div class="icon"><i class="fa-solid fa-database"></i></div>
        <h3>Data Management</h3>
        <p>Import CSV datasets for rooms, courses, and lecturers into the central database.</p>
        <div style="color: #818cf8; font-weight: 600; font-size: 0.85rem;">Bulk Tools <i class="fa-solid fa-chevron-right" style="margin-left: 5px;"></i></div>
    </a>

    <a href="as/courses.php" class="feature-card">
        <div class="icon"><i class="fa-solid fa-book-open"></i></div>
        <h3>Curriculum Control</h3>
        <p>Define course levels, credit hours, and departmental specializations.</p>
        <div style="color: #818cf8; font-weight: 600; font-size: 0.85rem;">Manage Courses <i class="fa-solid fa-chevron-right" style="margin-left: 5px;"></i></div>
    </a>
</main>

<div class="footer">
    <p>&copy; <?php echo date('Y'); ?> Valley View University AI Scheduling Team.</p>
    <p style="font-size: 0.75rem; margin-top: 10px;">Engineering excellence for smarter education.</p>
</div>

</body>
</html>
