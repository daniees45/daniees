from typing import Dict, List, Tuple, Any
from data_model import Lecturer, Room, Course, ClassSection, TimeSlot

Domain = Dict[str, List[Any]] # A mapping from entity type to list of entities e.g section_id -> List of (day, slot, room_id)

def build_domain(data: dict) -> Domain:
    config = data['config']
    lecturers : Dict[str, Lecturer] = data['lecturers']
    rooms : Dict[str, Room] = data['rooms']
    courses : Dict[str, Course] = data['courses']
    sections : List[ClassSection] = data['sections']
    
    days = config['days']  # e.g., 5 for Monday to Friday
    slots_per_day = config['slots_per_day']  # e.g., 8 slots
    slots_per_day = config['slots_per_day']  # e.g., 8 slots
    domains : Domain = {}
    
    # Check if strict capacity is enabled in config
    strict_capacity = config.get('strict_capacity', False)
    print(f"[INFO] Strict Capacity Check: {'ENABLED' if strict_capacity else 'DISABLED'}")
    
    special_rooms = data.get('special_rooms', {})
    # Pre-calculate ID set for fast lookup to easily check if a room is reserved
    reserved_room_ids = set()
    for info in special_rooms.values():
         if isinstance(info, dict):
             r_name = info['room']
         else:
             r_name = info
         reserved_room_ids.add(r_name.replace(" ", "_"))

    for sec in sections:
        
        #Pre-schedule locking remains a Hard Constraint
        if sec.fixed_day is not None and sec.fixed_slot is not None:
            req_room_id = sec.requested_room if sec.requested_room else next((iter(rooms)))
            domains[sec.id] = [(sec.fixed_day, sec.fixed_slot, req_room_id)]
            continue
        
        # lecturer = lecturers[sec.lecturer_id]
        # course = courses[sec.course_code]
        
        candidate_rooms = []
        
        # Rule 1: Special Course -> MUST use specific room
        if sec.course_code in special_rooms:
            info = special_rooms[sec.course_code]
            if isinstance(info, dict):
                target_room_name = info['room']
                forced_slot = info.get('slot')
                forced_day = info.get('day')
            else:
                target_room_name = info
                forced_slot = None
                forced_day = None

            target_room_id = target_room_name.replace(" ", "_")
            if target_room_id in rooms:
                # Case 1: Both day AND time are specified (strictest constraint)
                if forced_day is not None and forced_slot is not None:
                    print(f"[INFO] Locking {sec.course_code} to day {forced_day} ({days[forced_day]}) at slot {forced_slot} in {target_room_name}")
                    domains[sec.id] = [(forced_day, forced_slot, target_room_id)]
                    continue  # Skip all other logic
                
                # Case 2: Only time slot specified (any day, specific time)
                elif forced_slot is not None:
                    print(f"[DEBUG] Forcing {sec.course_code} into {target_room_name} at slot {forced_slot}")
                    values = []
                    for day in range(len(days)):
                        values.append((day, forced_slot, target_room_id))
                    domains[sec.id] = values
                    continue  # Skip standard logic
                
                # Case 3: Only room specified (standard special room)
                candidate_rooms = [rooms[target_room_id]]
            else:
                print(f"[ERROR] Special room '{target_room_name}' for {sec.course_code} not found in room DB!")
                candidate_rooms = [] 
        else:
            # Rule 2: Normal Course -> CANNOT use reserved rooms
            # AND: Prioritize departmental rooms
            
            # 1. Start with all non-reserved rooms
            all_available = [r for r in rooms.values() if r.id not in reserved_room_ids]
            
            # 2. Filter by Departmental Priority
            grp = sec.departmental_group
            dept_priority_rooms = []
            
            if grp == "CS":
                dept_priority_rooms = [r for r in all_available if "CS" in r.id.upper() or "LAB" in r.id.upper()]
            elif grp == "Nursing":
                dept_priority_rooms = [r for r in all_available if "CH" in r.id.upper()]
            elif grp == "Theology":
                dept_priority_rooms = [r for r in all_available if "BULLEY" in r.id.upper()]
            
            # 3. If prioritized rooms exist, use them as primary domain. 
            # Otherwise (or if empty), use the general pool.
            if dept_priority_rooms:
                candidate_rooms = dept_priority_rooms
                # We add the general pool as secondary options to ensure we don't fail if dept rooms are full
                # Optimization: CSP explores domains in order.
                other_rooms = [r for r in all_available if r.id not in [dr.id for dr in dept_priority_rooms]]
                candidate_rooms += other_rooms
            else:
                candidate_rooms = all_available

            # --- Handle Requested Room overrides ---
            if sec.requested_room:
                 r_id = sec.requested_room.replace(" ", "_")
                 if r_id in rooms and r_id not in reserved_room_ids:
                      # Put requested room at the very front of the candidate list
                      candidate_rooms = [rooms[r_id]] + [r for r in candidate_rooms if r.id != r_id]
            
            # --- NEW: Optional Capacity Check ---
            if strict_capacity:
                original_count = len(candidate_rooms)
                candidate_rooms = [r for r in candidate_rooms if r.capacity >= sec.enrollment]
                
                if not candidate_rooms and original_count > 0:
                    print(f"[WARNING] No rooms large enough for {sec.course_code} (Size: {sec.enrollment}). Relaxing capacity constraint.")
                    candidate_rooms = sorted(all_available, key=lambda x: x.capacity, reverse=True)[:3]
            # ------------------------------------
            
        values : List[Tuple[int, TimeSlot, str]] = []
        
      #Soft Constraints
      #Instead of filtering by lecturer availability, we iterate over all possible time slots
      #This allows the solver or AI to use  'unavailable' slots as a last resort if no other options exist.
        for day, _ in enumerate(days):
            for slot_start in range(slots_per_day):
                if is_valid_config_slot(day,slot_start, days, slots_per_day):
                    for room in candidate_rooms:
                        values.append((day, slot_start, room.id))
        domains[sec.id] = values
    return domains

def is_valid_config_slot(day, slot_start, total_days,slots_per_day):
    day_name = total_days[day]
    
    if day_name == "Fri" or day_name =="Friday":
        return slot_start < 2  # Max 2 sections on Fridays as per user requirement
    return True  # All slots available on other days




def build_exam_domain(data: dict) -> Domain:
    """
    Builds domain specifically for Examinations.
    - 2 Slots per day: Morning (0) and Afternoon (1)
    - Duration: 14 Days (default)
    """
    config = data['config']
    rooms : Dict[str, Room] = data['rooms']
    sections : List[ClassSection] = data['sections']
    
    # Exam Configuration
    exam_days = 14 # Default exam period length
    slots_per_day = 2 # Morning and Afternoon
    
    domains : Domain = {}
    
    # Pre-calculate reserved rooms
    special_rooms = data.get('special_rooms', {})
    reserved_room_ids = set()
    for info in special_rooms.values():
         if isinstance(info, dict): r_name = info['room']
         else: r_name = info
         reserved_room_ids.add(r_name.replace(" ", "_"))

    for sec in sections:
        candidate_rooms = []
        
        # Rule 1: Special Request Rule (Same as normal class)
        if sec.course_code in special_rooms:
             # ... (Simplified for exams: usually exams happen in larger halls, but we respect special assignments if any)
             info = special_rooms[sec.course_code]
             r_name = info['room'] if isinstance(info, dict) else info
             r_id = r_name.replace(" ", "_")
             if r_id in rooms:
                 candidate_rooms = [rooms[r_id]]
             else:
                 candidate_rooms = []
        else:
            # Rule 2: Use All Available Rooms (Exams often use all halls)
            # Prioritize larger rooms? For now, standard logic.
            # strict_capacity is CRITICAL for exams.
            all_available = [r for r in rooms.values() if r.id not in reserved_room_ids]
            
            # Filter by capacity (Strict for exams to avoid overcrowding)
            candidate_rooms = [r for r in all_available if r.capacity >= sec.enrollment]
            
            # If no room big enough, find largest available
            if not candidate_rooms:
                candidate_rooms = sorted(all_available, key=lambda x: x.capacity, reverse=True)[:3]

        values = []
        for day in range(exam_days):
            for slot in range(slots_per_day):
                # Day 5 is Saturday? Day 6 Sunday? 
                # Let's assume standard Mon-Fri logic or just continuous days.
                # If we exclude weekends, we need a calendar mapping. 
                # For now, we assume continuous exam days excluding Sundays if needed.
                # Let's simple skip every 7th day (Sunday) if starting Mon.
                if (day % 7) == 6: continue # Skip Sundays

                for room in candidate_rooms:
                    values.append((day, slot, room.id))
        
        domains[sec.id] = values
        
    return domains
