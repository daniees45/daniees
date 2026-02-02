import csv

SLOT_TIME = {
    0: ("7:00 AM", "9:30 AM"),
    1: ("10:00 AM", "12:30 PM"),
    2: ("2:00 PM", "4:30 PM"),
    3: ("5:00 PM", "6:00 PM")
}





EXAM_SLOT_TIME = {
    0: ("9:00 AM", "11:30 AM"),
    1: ("2:00 PM", "4:30 PM")
}

def export_solution(solution, data, out_path : str, blocked_blocks: list = None, exam_mode: bool = False):
    """
    Exports the scheduling solution to a CSV file.
    """
    days = data["config"]["days"]
    sections_by_id = {sec.id: sec for sec in data["sections"]}
    courses = data["courses"]
    lecturers = data["lecturers"]
    rooms = data["rooms"]
    
    rows = []
    
    headers = ["Course Code", "Course Title", "Credit Hrs", "Lecturer Name", "Room Name", "Day", "Time"]
    rows.append(headers)
    
    #1. Merge Blocked Blocks (Pre-existing/General Schedule)
    if blocked_blocks:
        for block in blocked_blocks:
            row_dict = block.get('raw_row', {})
            # Map raw_row back to canonical headers
            # Note: vvu_general_schedule might have different casing, but we'll try best fit
            
            def get_val(keys, default=""):
                for k in keys:
                    if k in row_dict: return row_dict[k]
                return default

            rows.append([
                get_val(['course_code', 'code']),
                get_val(['course_title', 'title']),
                get_val(['credit_hrs', 'credits', 'credit_hours']),
                get_val(['lecturer_name', 'lecturer']),
                get_val(['room_name', 'room']),
                get_val(['day']),
                get_val(['time', 'time_slot'])
            ])

    # Sort solution by (Day, Slot, Room)
    sorted_assignments = sorted(solution.items(), key=lambda item: (item[1][0], item[1][1], item[1][2]))
    
    #2. Iterate over the solution and build rows
    for sec_id, (day_idx, slot_idx, room_id) in sorted_assignments:
        sec = sections_by_id[sec_id]
        course = courses[sec.course_code]
        lecturer = lecturers[sec.lecturer_id]
        room = rooms[room_id]
        
        # Handle Day Name
        if day_idx < len(days):
             day_name = days[day_idx]
        else:
             # Fallback for extended exam period
             day_name = f"Day {day_idx + 1}"
        
        if exam_mode:
            start_time, end_time = EXAM_SLOT_TIME.get(slot_idx, ("?", "?"))
        else:
            start_time, end_time = SLOT_TIME.get(slot_idx, ("?", "?"))
        
        rows.append([
            course.code,
            sec.section_title,
            course.credit_hours,
            lecturer.name,
            room.name,
            day_name,
            f"{start_time} - {end_time}"
        ])

        
    #Write to CSV
    with open(out_path, "w", newline='', encoding='utf-8') as f:
        writer = csv.writer(f)
        writer.writerows(rows)
    print(f"Schedule exported to {out_path}")