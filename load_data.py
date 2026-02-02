
import pandas as pd
import os
from typing import  Dict, List
from data_model import Lecturer, Room, Course, ClassSection

day_to_index = {"Mon":0, "Tue":1, "Wed":2, "Thu":3, "Fri":4,
                "Monday":0, "Tuesday":1, "Wednesday":2, "Thursday":3, "Friday":4}

def normalize_name(name: str) -> str:
    if not isinstance(name, str):
        return ""
    name = name .replace(".", " ").replace("-", " ")
    return " ".join(name.split()).lower()

def get_department_group(course_code: str) -> str:
    """Categorize course into department groups based on prefix."""
    code = str(course_code).upper()
    # CS/IT/BIS Group
    if any(prefix in code for prefix in ["COSC", "INFT", "BBIS", "CSCD"]):
        return "CS"
    # Nursing Group
    if any(prefix in code for prefix in ["NURS", "RNSG", "MIDW"]):
        return "Nursing"
    # Theology Group
    if any(prefix in code for prefix in ["RELB", "RELT", "PEAC"]):
        return "Theology"
    return "Other"

def guess_semester(course_code: str, title: str) -> str:
    """Guesses if a course belongs to Semester 1 or 2 based on its code/title."""
    code = str(course_code).upper()
    title_upper = str(title).upper()
    
    # 1. Check for explicit "I" or "II" or "1" or "2" in title
    if any(x in title_upper for x in [" II", " 2", "PART 2", "SKILLS II"]):
        return "2"
    if any(x in title_upper for x in [" I", " 1", "PART 1", "SKILLS I"]):
        return "1"
    
    # 2. Check course code digits (heuristic: odd level-digits sometimes mean Sem 1, even Sem 2?? Not reliable)
    # Let's use a more robust hash-based alternation if we can't tell, to balance load.
    # But first, specific VVU patterns if known.
    
    # Default: Alternating based on code to spread load if we have no clue
    return "1" if hash(code) % 2 == 0 else "2"

def load_level_data() -> Dict[str, int]:

    mapping = {}
    for level in [100, 200,300,400]:
        file_path = f"level_{level}.csv"
        if os.path.exists(file_path):
            df = pd.read_csv(file_path)
            for code in df['course_code'].unique():
                mapping[str(code).strip().upper()] = level // 100
    return mapping

def load_combined_data(paths: List[str],
                       availability_path: str = "lecturer_availability.csv",
                       special_rooms_path: str = "special_rooms.csv",
                       rooms_csv_path: str = "rooms.csv",
                       curriculum_path: str = "curriculum.csv",
                       override_course_type: str = None,
                       interactive: bool = True,
                       availability_mode: str = "1") :
    from analyzer import TIME_TO_SLOT
    
    dfs = []
    for path in paths:
        if os.path.exists(path):
            dfs.append(pd.read_csv(path))
    combined_df = pd.concat(dfs, ignore_index=True)
    
    # Normalize Column Names (handle spaces and case)
    combined_df.columns = [c.strip().replace(' ', '_').lower() for c in combined_df.columns]
    
    # Check for required columns after normalization
    required = ['course_code', 'lecturer_name']
    if not all(c in combined_df.columns for c in required):
        raise ValueError(f"Missing required columns. Found: {list(combined_df.columns)}")
        
    combined_df = combined_df.dropna(subset=['course_code', 'lecturer_name'])
    
    level_map = load_level_data()
    
    #Load curriculum data and identify cohorts
    course_cohorts = {}
    all_programs = set()
    if os.path.exists(curriculum_path):
        curriculum_df = pd.read_csv(curriculum_path)
        for _, row in curriculum_df.iterrows():
            course_code = str(row['course_code']).strip().upper()
            program = str(row['program']).strip()
            level = str(row['level']).strip()
            cohort_id = f"{program}_{level}"
            all_programs.add(program)
            if course_code not in course_cohorts:
                course_cohorts[course_code] = set()
            course_cohorts[course_code].add(cohort_id)
            
    
    
    #Build lecturers with interactive availability management
    from manage_availability import check_and_prompt_availability
    
    # Get unique lecturer names from input
    unique_lecturers = combined_df['lecturer_name'].unique()
    lecturer_names_list = [str(name).strip() for name in unique_lecturers]
    
    # Check availability and prompt if needed
    lecturer_availability_map = {}
    
    # If interactive=False, we still want to use the check logic if availability_mode is passed
    # availability_mode: "1" = Auto (AI logic), "2" = Strict (Manual/File logic without prompt)
    
    if interactive:
        print("\n[INFO] Checking lecturer availability...")
        lecturer_availability_map = check_and_prompt_availability(
            lecturers_in_input=lecturer_names_list,
            availability_file=availability_path,
            min_days_threshold=3
        )
    else:
        # Headless Mode
        # We manually call a new function or modify check_and_prompt_availability to take an override
        # For simplicity, let's reuse check_and_prompt_availability with a secret override flag
        print(f"\n[INFO] Applying Availability Strategy: {'Auto-Expand' if availability_mode == '1' else 'Strict (File-Based)'}")
        lecturer_availability_map = check_and_prompt_availability(
            lecturers_in_input=lecturer_names_list,
            availability_file=availability_path,
            min_days_threshold=3,
            mode_override=availability_mode
        )
        if availability_mode == "1":
            from manage_availability import update_availability_file
            update_availability_file(lecturer_availability_map, availability_path)
    
    lecturers: Dict[str, Lecturer] = {}
    for name in combined_df['lecturer_name'].unique():
        name = str(name).strip()
        norm_name = normalize_name(name)
        
        # Get availability from the map (already prompted if needed)
        available_days = lecturer_availability_map.get(name, list(range(5)))
        available_time_slots = [(d, s) for d in available_days for s in range(3)]  #Assuming 3 slots per day
        lecturers[name.replace(" ", "_")] = Lecturer(id=name.replace(" ", "_"), name=name, available_time_slots=available_time_slots)

        
    #Build rooms
    room_db = {}
    if os.path.exists(rooms_csv_path):
        rooms_df = pd.read_csv(rooms_csv_path)
        room_db = dict(zip(rooms_df["room_name"], rooms_df["capacity"]))
        
    rooms : Dict[str, Room] = {}
    
    # Logic: If using the general 'rooms.csv', we can be flexible.
    # If using a specific departmental pool, ONLY use rooms from that file to avoid leakage.
    is_custom_pool = os.path.basename(rooms_csv_path) != "rooms.csv"
    
    if is_custom_pool:
        all_rooms = list(room_db.keys())
    else:
        input_rooms = set(combined_df['room_name'].dropna().unique()) if 'room_name' in combined_df.columns else set()
        all_rooms = list(input_rooms | set(room_db.keys()))

    for room_name in all_rooms:
        room_id = room_name.replace(" ", "_")
        capacity = int(room_db.get(room_name, 30))  #Default capacity
        rooms[room_id] = Room(id=room_id, name=room_name, capacity=capacity, room_type="lecture", available_time_slots=[(d, s) for d in range(5) for s in range(3)])
        
    #Build Sections
    courses : Dict[str, Course] = {}
    sections : List[ClassSection] = []
    seen = set()
    for idx, row in combined_df.iterrows():
        course_code = str(row['course_code']).strip().upper()
        lecturer_name = str(row['lecturer_name']).strip()
        
        # Use user override if provided, otherwise check CSV, otherwise default to Departmental
        if override_course_type:
            section_type = override_course_type
        else:
            section_type = str(row.get("source_type", "Departmental")).strip()
            
        level = level_map.get(course_code, int(row.get('course_level', 0))) 
        
        if course_code not in courses:
            # Map credit hours to lessons (handle both 'credit_hours' and 'credit_hrs')
            cred_str = str(row.get('credit_hours', row.get('credit_hrs', 'NC'))).strip().upper()
            if cred_str == 'NC' or str(cred_str).lower() == 'nan':
                lessons = 1
            else:
                try:
                    # Handle decimals like 3.0
                    lessons = int(float(cred_str))
                except:
                    lessons = 3 # Standard default
            
            courses[course_code] = Course(code=course_code, title=str(row.get('course_title', '')).strip(),
                                          credit_hours=cred_str,
                                          required_room_type="lecture",
                                          required_lessons=lessons)



        #smart locking for general courses
        fixed_day, fixed_slot = None, None
        if section_type == "General" and "day" in combined_df.columns and "start_time" in combined_df.columns:
            day = str(row.get("day", "")).strip()
            start_time = str(row.get("start_time", str(row.get("time", "")))).split("-")[0].strip().lower()
            if day in day_to_index and start_time in TIME_TO_SLOT:
                fixed_day = day_to_index[day]
                fixed_slot = TIME_TO_SLOT[start_time]

        #Build cohorts: General courses belong to all programs of that level
        cohorts = course_cohorts.get(course_code, set())
        
        # Semester parsing
        semester = str(row.get('Semester', '')).strip()
        if not semester or semester.lower() == 'nan':
             # Try to guess semester to avoid capacity bottleneck
             semester = guess_semester(course_code, str(row.get("course_title", "")))

        if section_type == "General":
            base_cohort = f"General_{level*100}_Sem{semester}"
            cohorts.add(base_cohort)
            
            # Also add for specific programs if needed
            for program in all_programs:
                prog_cohort = f"{program}_{level*100}_Sem{semester}"
                cohorts.add(prog_cohort)

                
        # DEPT COURSE: Check if it conflicts with GENERAL schedule
        # If 'blocked_slots' is passed (from General Schedule CSV), we add constraints
        # Logic: If this is a Dept course for Level 100 Sem 1, it must not clash with General Level 100 Sem 1
        
        # (This logic is usually handled in the solver constraints, so we just pass cohorts here)
        
        sec_id = f"{course_code}_{idx}"
        sections.append(ClassSection(
            id=sec_id,
            course_code=course_code,
            lecturer_id=lecturer_name.replace(" ", "_"),
            section_title=str(row.get("course_title", course_code)).strip(),
            course_type=section_type,
            course_level=str(level*100),
            enrollment=int(row.get('enrollment', 30)),
            cohorts=cohorts,
            fixed_day=fixed_day,
            fixed_slot=fixed_slot,
            semester=semester,
            departmental_group=get_department_group(course_code)
        ))

    # Load Configuration
    config = {
        "days": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
        "slots_per_day": 4, 
        "strict_capacity": False
    }


    if os.path.exists("config.json"):
        import json
        try:
            with open("config.json", "r") as f:
                loaded_conf = json.load(f)
                config.update(loaded_conf)
        except:
            pass
            
    # Load special rooms mapping
    special_rooms = {}
    if os.path.exists(special_rooms_path):
        sr_df = pd.read_csv(special_rooms_path)
        # Normalize columns
        sr_df.columns = [c.strip().lower() for c in sr_df.columns]
        
        if 'course_code' in sr_df.columns and 'room_name' in sr_df.columns:
            for _, row in sr_df.iterrows():
                c_code = str(row['course_code']).strip().upper()
                r_name = str(row['room_name']).strip()
                
                # Parse fixed day if specified
                fixed_day_idx = None
                if 'fixed_day' in sr_df.columns:
                    day_str = str(row['fixed_day']).strip()
                    if day_str and day_str != 'nan':
                        fixed_day_idx = day_to_index.get(day_str)
                
                # Parse fixed time if specified
                slot_idx = None
                if 'fixed_time' in sr_df.columns:
                    t_str = str(row['fixed_time']).strip().lower()
                    if t_str and t_str != 'nan':
                        slot_idx = TIME_TO_SLOT.get(t_str)
                
                # Store as dict with metadata
                special_rooms[c_code] = {
                    "room": r_name,
                    "day": fixed_day_idx,  # None if not specified
                    "slot": slot_idx        # None if not specified
                }

    return {
        "sections": sections,
        "lecturers": lecturers,
        "rooms": rooms,
        "course_cohorts": course_cohorts,
        "courses": courses,
        "special_rooms": special_rooms,
        "config": config
    }

def load_general_schedule_blocks(general_csv_path: str) -> List[dict]:
    """
    Parses a General Schedule CSV to identify BUSY slots for cohorts and rooms.
    Returns a list of blocked time slots with room and cohort metadata.
    """
    blocks = []
    if not os.path.exists(general_csv_path):
        return blocks
        
    from analyzer import TIME_TO_SLOT
    day_to_index = {"Monday": 0, "Tuesday": 1, "Wednesday": 2, "Thursday": 3, "Friday": 4, 
                    "Mon": 0, "Tue": 1, "Wed": 2, "Thu": 3, "Fri": 4}
    import pandas as pd
    import re
    
    try:
        df = pd.read_csv(general_csv_path)
        # Normalize columns
        df.columns = [c.strip().lower().replace(' ', '_') for c in df.columns]
        
        # Mapping variations
        col_map = {
            'level': ['course_level', 'level'],
            'day': ['day'],
            'time': ['start_time', 'time', 'time_slot'],
            'room': ['room_name', 'room'],
            'code': ['course_code', 'code']
        }
        
        def find_col(keys):
            for k in keys:
                if k in df.columns: return k
            return None

        c_level = find_col(col_map['level'])
        c_day = find_col(col_map['day'])
        c_time = find_col(col_map['time'])
        c_room = find_col(col_map['room'])
        c_code = find_col(col_map['code'])

        if not all([c_day, c_time]):
            return blocks
            
        for _, row in df.iterrows():
            try:
                # 1. Level extraction
                level = '100'
                if c_level and str(row[c_level]) != 'nan':
                    level = str(int(float(row[c_level])))
                elif c_code and str(row[c_code]) != 'nan':
                    # Extract level from code (e.g. COSC 110 -> 100)
                    match = re.search(r'(\d)', str(row[c_code]))
                    if match: level = match.group(1) + "00"

                sem = str(row.get('semester', '')).strip()
                if not sem or sem.lower() == 'nan': sem = None 
                
                day_str = str(row[c_day]).strip().title()
                # Handle "7:00 AM - 9:30 AM" or "7:00 AM"
                time_str = str(row[c_time]).split('-')[0].strip().lower()
                
                room_name = str(row[c_room]).strip() if c_room else None
                
                if day_str in day_to_index and time_str in TIME_TO_SLOT:
                    day_idx = day_to_index[day_str]
                    slot_obj = TIME_TO_SLOT[time_str]
                    
                    blocks.append({
                        'code': str(row[c_code]).strip().upper() if c_code else None,
                        'level': level,
                        'semester': sem,
                        'day': day_idx,
                        'slot': slot_obj,
                        'room': room_name,
                        'raw_row': row.to_dict() # For merging later
                    })
            except:
                continue
    except Exception as e:
        print(f"[WARNING] Could not load general schedule: {e}")
            
    return blocks
