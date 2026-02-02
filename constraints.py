from typing import List, Dict, Tuple, Any
from data_model import ClassSection

Constraint = Dict[str, Any]  # A mapping from constraint type to its parameters

def no_lecturer_conflict(assignment: Dict[str, Any], 
                         var_id: str,
                         value : Any,
                         sections: Dict[str, ClassSection]) -> bool:
    
    day, slot, _ = value
    this_lect = sections[var_id].lecturer_id
    for other_id, other_val in assignment.items():
        if other_id == var_id:
            continue
        other_day, other_slot, _ = other_val
        if this_lect == sections[other_id].lecturer_id and day == other_day and slot == other_slot:
            return False
    return True

def no_room_conflict(assignment: Dict[str, Any], 
                     var_id: str,
                     value : Any) -> bool:
    day, slot, room_id = value
    for other_id, other_val in assignment.items():
        if other_id == var_id:
            continue
        other_day, other_slot, other_room_id = other_val
        if room_id == other_room_id and day == other_day and slot == other_slot:
            return False
    return True


def no_student_cohort_conflict(assignment: Dict[str, Any], 
                                var_id: str,
                                value : Any,
                                sections: Dict[str, ClassSection]) -> bool:
    
    
    """
    This ensures that no classes sharing the same student program and level
    are scheduled at the same time.
    """
    day, slot, _ = value
    this_sec = sections[var_id]
    this_cohorts = this_sec.cohorts
    
    for other_id, other_val in assignment.items():
        if other_id == var_id:
            continue
        other_day, other_slot, _ = other_val
        
        if day == other_day and slot == other_slot:
            other_sec = sections[other_id]
            other_cohorts = other_sec.cohorts
            
            # Intersection check: Is there any overlap in student groups?
            common = this_cohorts.intersection(other_cohorts)
            
            # SEMESTER CHECK:
            # Even if cohorts match (e.g. "General_100"), if they are explicitly for different semesters,
            # they do NOT conflict.
            this_sem = sections[var_id].semester
            other_sem = sections[other_id].semester
            
            # If both have semesters defined and they are DIFFERENT, then NO CONFLICT.
            if this_sem and other_sem and this_sem != other_sem:
                 return True # Safe, different semesters
            
            if common:
                # OPTIMIZATION:
                # If one is General and one is Departmental, this is a CRITICAL conflict.
                # If both are Departmental, it might be an elective clash which is sometimes unavoidable.
                # But for now, we treat all cohort clashes as invalid to ensure clean schedules.
                return False

    return True

def make_constraints(sections: list,  rooms: dict, preference_model: dict = None):
    sections_by_id = {sec.id: sec for sec in sections}

    def lecturer_conflict_wrapper(assignment, var_id, value):
        return no_lecturer_conflict(assignment, var_id, value, sections_by_id)
    
    def cohort_conflict_wrapper(assignment, var_id, value):
        return no_student_cohort_conflict(assignment, var_id, value, sections_by_id)
    

def no_blocked_slot_conflict(assignment: Dict[str, Any], 
                              var_id: str,
                              value : Any,
                              sections: Dict[str, ClassSection],
                              blocked_blocks: List[dict],
                              rooms: Dict[int, Any]) -> bool:
    """
    Ensures that a Departmental course isn't scheduled during a time slot
    where its students are busy OR the room is already occupied by a General course.
    """
    if not blocked_blocks:
        return True
        
    day, slot, room_id = value
    this_room_name = rooms[room_id].name if room_id in rooms else None
    sec = sections[var_id]
    
    for block in blocked_blocks:
        # 1. Room Conflict Check
        if block.get('room') and this_room_name:
            if day == block['day'] and slot == block['slot'] and block['room'] == this_room_name:
                return False
                
        # 2. Student Cohort Conflict Check
        if block['level'] == str(sec.course_level) and \
           (block['semester'] is None or sec.semester is None or block['semester'] == str(sec.semester)):
               if day == block['day'] and slot == block['slot']:
                   return False
                   
    return True

def make_constraints(sections: list,  rooms: dict, preference_model: dict = None, blocked_blocks: list = None):
    sections_by_id = {sec.id: sec for sec in sections}

    def lecturer_conflict_wrapper(assignment, var_id, value):
        return no_lecturer_conflict(assignment, var_id, value, sections_by_id)
    
    def cohort_conflict_wrapper(assignment, var_id, value):
        return no_student_cohort_conflict(assignment, var_id, value, sections_by_id)
        
    def blocked_slot_wrapper(assignment, var_id, value):
        if not blocked_blocks: return True
        return no_blocked_slot_conflict(assignment, var_id, value, sections_by_id, blocked_blocks, rooms)
    
    base_constraints = [
        lecturer_conflict_wrapper,
        no_room_conflict,
        cohort_conflict_wrapper
    ]
    
    if blocked_blocks:
        base_constraints.append(blocked_slot_wrapper)
        
    return base_constraints



def no_exam_level_clash(assignment: Dict[str, Any], 
                        var_id: str,
                        value : Any,
                        sections: Dict[str, ClassSection]) -> bool:
    """
    STRICT EXAM RULE:
    Courses of the SAME LEVEL and SAME SEMESTER cannot be scheduled at the same time.
    This allows students to take all exams for their level.
    """
    day, slot, _ = value
    this_sec = sections[var_id]
    
    for other_id, other_val in assignment.items():
        if other_id == var_id: continue
        
        other_day, other_slot, _ = other_val
        
        if day == other_day and slot == other_slot:
            other_sec = sections[other_id]
            
            # Check Level & Semester Match
            # If Levels match AND Semesters match (or are null/wildcard)
            if str(this_sec.course_level) == str(other_sec.course_level):
                if str(this_sec.semester) == str(other_sec.semester):
                    return False
    return True

def make_exam_constraints(sections: list, rooms: dict):
    sections_by_id = {sec.id: sec for sec in sections}

    def lecturer_conflict_wrapper(assignment, var_id, value):
        return no_lecturer_conflict(assignment, var_id, value, sections_by_id)
    
    def exam_level_conflict_wrapper(assignment, var_id, value):
        return no_exam_level_clash(assignment, var_id, value, sections_by_id)
    
    return [
        lecturer_conflict_wrapper,
        no_room_conflict,
        exam_level_conflict_wrapper
    ]
