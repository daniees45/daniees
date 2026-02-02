
from load_data import load_combined_data
from builder import build_domain
from constraints import make_constraints
from csp import CSP
from analyzer import train_model, load_trained_model
from export_data import export_solution
import os
import pandas as pd

def main():
    
    history_data = "historical_schedule.csv"
    model_file = "scheduling_model.pkl"
    
    current_input = input("Enter the path to the current scheduling data CSV file: ")
    if not current_input.endswith(".csv") : current_input += ".csv"
    
    # Ask user for course type
    print("\n" + "="*60)
    print("COURSE TYPE CATEGORIZATION")
    print("="*60)
    print("Is this file for General or Departmental courses?")
    print("1. General Courses (e.g., MATH 121, ENGL 111, PEAC 100)")
    print("2. Departmental Courses (e.g., CS 301, BIOL 405)")
    print("="*60)
    
    while True:
        choice = input("Enter choice (1 or 2): ").strip()
        if choice in ["1", "2"]:
            break
        print("[ERROR] Invalid choice. Please enter 1 or 2.")
    
    course_type = "General" if choice == "1" else "Departmental"
    
    # Categorize courses into appropriate file
    from categorize_courses import categorize_courses
    try:
        categorized_file = categorize_courses(current_input, course_type)
        print(f"\n[INFO] Courses categorized as '{course_type}' and appended to '{categorized_file}' (duplicates skipped)")
    except Exception as e:
        print(f"[WARNING] Categorization failed: {e}")
        print("[INFO] Continuing with original file...")
        
    # Departmental Dependency Check
    blocked_blocks = []
    rooms_path = "rooms.csv"
    if course_type == "Departmental":
        # 1. Department Room Selection
        print("\n" + "="*60)
        print("DEPARTMENT-SPECIFIC ROOM SELECTION")
        print("="*60)
        print("Which department are you scheduling for?")
        print("1. Computer Science (CS/IT/BIS)")
        print("2. Nursing & Midwifery")
        print("3. Theology")
        print("4. Others / General Pool")
        print("="*60)
        
        while True:
            dept_choice = input("Enter choice (1-4): ").strip()
            if dept_choice in ["1", "2", "3", "4"]: break
            print("[ERROR] Invalid choice. Please enter 1-4.")
            
        if dept_choice == "1":
            rooms_path = "computing_science_rooms.csv"
        elif dept_choice == "2":
            rooms_path = "nursing_rooms.csv"
        elif dept_choice == "3":
            rooms_path = "theology_rooms.csv"
        else:
            rooms_path = "rooms.csv"
            
        print(f"[INFO] Selected Room Pool: {rooms_path}")

        # 2. General Schedule Dependency (Auto-detection)
        default_gen_path = "vvu_general_schedule.csv"
        if os.path.exists(default_gen_path):
             from load_data import load_general_schedule_blocks
             blocked_blocks = load_general_schedule_blocks(default_gen_path)
             print(f"\n[INFO] Auto-detected '{default_gen_path}'. Loaded {len(blocked_blocks)} constraints.")
        else:
            print("\n" + "="*60)
            print("GENERAL SCHEDULE DEPENDENCY")
            print("="*60)
            print("Departmental schedules must not conflict with General Courses.")
            print(f"Could not find '{default_gen_path}' automatically.")
            print("Do you have a finalized General Schedule CSV?")
            print("1. Yes - Load it manually")
            print("2. No - Continue (Risk of clashes!)")
            
            while True:
                dep_choice = input("Enter choice (1 or 2): ").strip()
                if dep_choice in ["1", "2"]: break
                
            if dep_choice == "1":
                gen_csv = input("Enter path to General Schedule CSV: ").strip()
                if os.path.exists(gen_csv):
                    from load_data import load_general_schedule_blocks
                    blocked_blocks = load_general_schedule_blocks(gen_csv)
                    print(f"[INFO] Loaded {len(blocked_blocks)} blocked slots from General Schedule.")
                else:
                    print(f"[ERROR] File {gen_csv} not found. Proceeding without blocks.")


    final_output = input("\nEnter the desired output CSV file path for the schedule: ")
    if not final_output.endswith(".csv") : final_output += ".csv"
    
    #1. AI Memory Training/Loading
    if not os.path.exists(model_file) and os.path.exists(history_data):
        print("Training AI model from historical data...")
        preference_model = train_model(history_data=history_data, model_save_path=model_file)
    else:
        print("Loading trained AI model...")
        preference_model = load_trained_model(model_path=model_file)
        
    #2. Load current data
    print("Loading current scheduling data...")
    try:
        data = load_combined_data([current_input], rooms_csv_path=rooms_path)
    except FileNotFoundError :
        print(f"Error: {current_input} not found. ")
        return
        
        
    #3. Build Constraints and Domains Mapping
    print("Building constraints and domains...")
    domain = build_domain(data)
    constraints = make_constraints(data["sections"], data["rooms"], preference_model, blocked_blocks)
    
    #4 Solver execution
    print("Initializing CSP solver...")
    solver = CSP(variables=data["sections"], 
              domains=domain,
              constraints=constraints,
              lecturers=data["lecturers"],
              preferences=preference_model)
    
    print("Solving the scheduling problem...")
    solution = solver.solve()
    if solution is None:
        print("\n[Failed] No valid timetable found")
        return
    
    #5. Export solution
    print("Exporting the solution...")
    export_solution(solution, data, out_path=final_output, blocked_blocks=blocked_blocks)

    #6. Calculate and display accuracy
    accuracy = solver.calculate_accuracy(solution)
    print(f"\n[AI Evaluation] Schedule Accuracy: {accuracy:.2f}% (Lecturer Preference Match)")
    
    
    print("Self-Learning: Archiving this success into history...")
    #Append current solution to historical data for future learning
    new_results = pd.read_csv(final_output)
    if os.path.exists(history_data):
        new_results.to_csv(history_data, mode='a', header=False, index=False)
    else:
        new_results.to_csv(history_data, index=False)
    
    #Retrain the model with the new data
    train_model(history_data=history_data, model_save_path=model_file)
    print(f"\nDONE! Timetable saved to {final_output}. AI intelligence improved.")
if __name__ == "__main__":
    main()