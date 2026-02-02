
# main_web.py - Headless AI Scheduler Core
import sys
import os
import pandas as pd
from load_data import load_combined_data
from builder import build_domain, build_exam_domain
from constraints import make_constraints, make_exam_constraints
from csp import CSP
from analyzer import train_model, load_trained_model
from export_data import export_solution

def run_headless(input_file, mode_choice, output_file, ai_preference, course_type="Departmental", dept_choice="1", avail_mode="1", exam_mode=False):
    """
    Non-interactive version of the scheduler for Web/PHP integration.
    """
    history_data = "historical_schedule.csv"
    model_file = "scheduling_model.pkl"
    
    # 1. AI Memory Loading
    print(f"[AI] Loading Intelligence from {model_file}...")
    preference_model = load_trained_model(model_path=model_file)
    
    # Categorize courses (Archive step)
    from categorize_courses import categorize_courses
    try:
        categorized_file = categorize_courses(input_file, course_type)
        print(f"[INFO] Courses categorized as '{course_type}' and appended to '{categorized_file}'")
    except Exception as e:
        print(f"[WARNING] Categorization failed: {e}")
    
    # Determine Room Pool
    rooms_path = "rooms.csv"
    if course_type == "Departmental":
        if dept_choice == "1":
            rooms_path = "computing_science_rooms.csv"
        elif dept_choice == "2":
            rooms_path = "nursing_rooms.csv"
        elif dept_choice == "3":
            rooms_path = "theology_rooms.csv"
        else:
            rooms_path = "rooms.csv"
    
    # 2. General Schedule Dependency (Silently handled)
    blocked_blocks = []
    default_gen_path = "vvu_general_schedule.csv"
    if os.path.exists(default_gen_path) and not exam_mode:
        from load_data import load_general_schedule_blocks
        blocked_blocks = load_general_schedule_blocks(default_gen_path)

    # 3. Load Data
    print(f"[DATA] Processing {input_file} for {course_type} (Dept: {dept_choice})...")
    # For headless web mode, disable interactive prompts
    from types import SimpleNamespace
    # Pass rooms_csv_path and avail_mode
    raw_data = load_combined_data([input_file], interactive=False, rooms_csv_path=rooms_path, availability_mode=avail_mode)
    data = SimpleNamespace(**raw_data)
    
    # --- EXAM MODE LOGIC ---
    if exam_mode:
        print("\n[MODE] === EXAMINATION TIMETABLE GENERATION ===")
        # Sort Constraint 1: General Courses FIRST
        # Heuristic: Start with General courses (e.g. prefix 'GD', 'GN', 'MATH 1', 'ENGL 1')
        def exam_sort_key(sec):
            c_code = sec.course_code.upper()
            is_general = any(c_code.startswith(p) for p in ['GN', 'GD', 'MATH', 'ENGL', 'PEAC'])
            # Tuple sort: (Not General, Level Ascending, Enrollment Descending)
            # False < True, so 'not is_general' puts General (True) first [False comes first? Wait. False < True is 0 < 1. So we want General=True to be FIRST.]
            # We want General first. So key should be 0 for General, 1 for Dept.
            # key = (1 if not is_general else 0, sec.course_level, -sec.enrollment)
            return (0 if is_general else 1, sec.course_level, -sec.enrollment)
            
        data.sections.sort(key=exam_sort_key)
        print("[INFO] Prioritized General Courses for Scheduling.")
    
    # 4. Solve
    print("[AI] Solving CSP Constraints...")
    
    if exam_mode:
        domain = build_exam_domain(raw_data)
        constraints = make_exam_constraints(data.sections, data.rooms)
        # Note: preference_model might be less relevant for exams or needs adaptation.
        # We pass it anyway, but constraints are strict.
    else:
        domain = build_domain(raw_data)
        constraints = make_constraints(data.sections, data.rooms, preference_model, blocked_blocks=blocked_blocks)
    
    # Progress Logging Callback
    import json
    progress_file = "ai_progress.json"
    
    def progress_reporter(msg):
        status = {}
        if "PROGRESS:" in msg:
            # msg format: timestamp [INFO] PROGRESS:Placed/Total|Pct
            try:
                parts = msg.split("PROGRESS:")[1].strip().split("|")
                status = {
                    "status": "running",
                    "placed": parts[0],
                    "percent": float(parts[1])
                }
            except:
                pass
        else:
             status = {"status": "running", "message": msg}
        
        if status:
            try:
                with open(progress_file, 'w') as f:
                    json.dump(status, f)
            except:
                pass

    solver = CSP(data.sections, domain, constraints, data.lecturers, preferences=preference_model, progress_callback=progress_reporter)
    
    # Enable file logging for CSP processes
    LOG_FILE = "csp_log.txt"
    def file_logger(msg, is_error=False):
        prefix = "[ERROR] " if is_error else "[INFO] "
        with open(LOG_FILE, "a") as f:
            f.write(f"{prefix}{msg}\n")
    
    solver.log = file_logger # Override solver logging
    
    solution = solver.solve()
    
    if solution:
        accuracy = solver.calculate_accuracy(solution)
        print(f"[SUCCESS] Solution found. Accuracy: {accuracy:.2f}%. Exporting to {output_file}...")
        export_solution(solution, raw_data, output_file, blocked_blocks=blocked_blocks, exam_mode=exam_mode)
        
        # Log resolution
        file_logger(f"Successfully generated schedule with {accuracy:.2f}% accuracy.")
        
        # Retrain (Only for class schedules, exams might skew historical data if mixed)
        if not exam_mode:
            train_model(history_data=history_data, model_save_path=model_file)
        return True, accuracy
    else:
        print("[FAILURE] No valid schedule found within constraints.")
        file_logger("Failed to find a valid schedule.", is_error=True)
        return False, 0.0

if __name__ == "__main__":
    # Expecting: python3 main_web.py <input.csv> <output.csv> [--exam]
    if len(sys.argv) < 3:
        print("Usage: python3 main_web.py <input.csv> <output.csv> [--exam]")
        sys.exit(1)
        
    in_file = sys.argv[1]
    out_file = sys.argv[2]
    
    is_exam = "--exam" in sys.argv
    
    # Hardcoded defaults for headless mode (Department 1=CS, Avail=1=Standard)
    # Ideally these should be CLI args too if PHP passes them.
    # PHP calls: passthru("$pythonPath main_web.py $inputFile $outputFile");
    # We should stick to that contract or update PHP.
    
    success = run_headless(in_file, 2, out_file, 1, exam_mode=is_exam)
    sys.exit(0 if success else 1)
