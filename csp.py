from typing import List, Dict, Tuple, Any, Callable, Optional
from data_model import ClassSection, Lecturer
import random
from datetime import datetime
import time

Assignment = Dict[str, Any]  # A mapping from variable id to assigned value
Domain = Dict[str, List[Any]]  # A mapping from variable id to list of possible values
Constraint = Callable[[Assignment, str, Any], bool]

class CSP:
    def __init__(self, 
                 variables: List[ClassSection],
                    domains: Domain,
                    constraints: List[Constraint],
                    lecturers: Dict[str, Lecturer],
                    preferences: Dict = None,
                    progress_callback: Optional[Callable[[str], None]] = None,
                    log_file : str = "csp_log.txt",
                    timeout_seconds: int = 30):
        self.variables = variables
        self.domains = domains
        self.constraints = constraints
        self.lecturers = lecturers
        self.preferences = preferences or {}
        self.progress_callback = progress_callback
        self.log_file = log_file
        self.timeout_seconds = timeout_seconds
        self.start_time = None
        
        # Optimization: map IDs to sections for O(1) lookup
        self.vars_by_id = {var.id: var for var in variables}
        
        self.iteration_count = 0
        
        #Initialize logging
        with open(self.log_file, 'w') as f:
            f.write(f"----AI Solver Session Started at {datetime.now()}----\n")
    
    def log(self, message: str, is_error: bool = False):
        timestamp = datetime.now().strftime("%H:%M:%S")
        prefix = "[ERROR]" if is_error else "[INFO]"
        log_message = f"{timestamp} {prefix} {message}\n"
        
        with open(self.log_file, 'a') as f:
            f.write(log_message + "\n")
        
        if self.progress_callback:
            self.progress_callback(log_message)
    
    def get_value_score(self, var_id: str, value: Any) -> float:
        """
        Calculate a score for a given value based on lecturer preferences.
        Higher scores indicate more preferred values.
        """
        day_id, slot_id, room_id = value
        sec = self.vars_by_id[var_id]
        lecturer = self.lecturers.get(sec.lecturer_id)
        
        score = 0.0
        if lecturer and (day_id, slot_id) in lecturer.available_time_slots:
            score += 100.0 
        
        # Additional scoring based on preferences can be added here
        if self.preferences:
            l_key = (sec.lecturer_id.replace("_", " "), day_id, slot_id)
            score += self.preferences.get("lecturer_time_preferences", {}).get(l_key, 0) * 5.0
            r_key = (sec.course_code, room_id)
            score += self.preferences.get("course_room_preferences", {}).get(r_key, 0) * 2.0
        return score
    
    def is_consistent(self, assignment: Assignment, var_id: str, value: Any) -> bool:
        # Check timeout
        if time.time() - self.start_time > self.timeout_seconds:
            self.log(f"Timeout exceeded ({self.timeout_seconds}s). Stopping search.", is_error=True)
            raise TimeoutError(f"CSP solver timed out after {self.timeout_seconds} seconds")
        
        # We don't actually add it to the assignment dict here because 
        # the constraints expect the *current* state of the world plus the candidate value.
        # But wait, the constraints.py implementation iterates over assignment.items().
        # So we SHOULD temporarily add it to check.
        assignment[var_id] = value
        for constraint in self.constraints:
            if not constraint(assignment, var_id, value):
                del assignment[var_id]
                return False
        del assignment[var_id]
        return True
    
    def select_unassigned_variable(self, assignment: Assignment) -> Optional[str]:
        unassigned_vars = [var.id for var in self.variables if var.id not in assignment]
        if not unassigned_vars:
            return None
        best_var = None
        best_score = float('inf')
        
        for var_id in unassigned_vars:
            legal_count = 0
            for value in self.domains[var_id]:
                if self.is_consistent(assignment, var_id, value):
                    legal_count += 1
            
            if legal_count < best_score:
                best_score = legal_count
                best_var = var_id
            
            if best_score == 0:
                self.log(f"Variable {var_id} has no legal values left.", is_error=True)
                return var_id
        return best_var if best_var else unassigned_vars[0]
    
    
    def backtrack(self, assignment: Assignment) -> Assignment | None:
        if len(assignment) == len(self.variables):
            return assignment


        
        self.iteration_count += 1
        if self.iteration_count % 50 == 0:
             pct = (len(assignment) / len(self.variables)) * 100
             self.log(f"PROGRESS:{len(assignment)}/{len(self.variables)}|{pct:.1f}")

        var_id = self.select_unassigned_variable(assignment)
        if var_id is None:
            return None
        
        sec = self.vars_by_id[var_id]
        domain_values = self.domains[var_id]
        domain_values.sort(key=lambda val: self.get_value_score(var_id, val) + random.uniform(0, 0.1), reverse=True) 
        for value in domain_values:
            if self.is_consistent(assignment, var_id, value):
                assignment[var_id] = value
                result = self.backtrack(assignment)
                if result is not None:
                    return result
                del assignment[var_id]
        self.log(f"REJECTED: Could not place {sec.section_title}. All {len(domain_values)} attempted slots caused conflicts.", is_error=True)
        return None
    
    def solve(self) -> Assignment | None:
        self.start_time = time.time()
        self.log("Starting CSP solver...")
        assignment: Assignment = {}
        try:
            result = self.backtrack(assignment)
            if result is not None:
                self.log("CSP solver found a solution.")
            else:
                self.log("CSP solver could not find a solution.", is_error=True)
                self.run_diagnosis()
            return result
        except TimeoutError as e:
            self.log(str(e), is_error=True)
            self.run_diagnosis()
            return None

    def run_diagnosis(self):
        """
        Runs a diagnostic pass to explain WHY the schedule failed.
        """
        from diagnostics import generate_conflict_heatmap
        
        print("\n" + "="*60)
        print("DIAGNOSTIC REPORT: SCHEDULING FAILURE ANALYSIS")
        print("="*60)
        self.log("Running failure diagnosis...")
        
        day_names = ["Mon", "Tue", "Wed", "Thu", "Fri"]
        generate_conflict_heatmap(self.variables, self.domains, day_names)

        # 1. Check Global Capacity
        total_slots = sum(len(lect.available_time_slots) for lect in self.lecturers.values())
        total_required = len(self.variables)
        print(f"\n[Analysis] Total Sections to Schedule: {total_required}")

        # Note: This checks lecturer capacity, but room capacity is also a factor.
        
        # 2. Greedy Attempt to identify blocker
        assignment: Assignment = {}
        unassigned = self.variables[:]
        
        # Sort by most constrained (heuristic: fewest domain values)
        unassigned.sort(key=lambda v: len(self.domains[v.id]))
        
        for var in unassigned:
            var_id = var.id
            valid_values = []
            conflict_reasons = {} # Map constraint_name -> count
            
            # Try to find a valid assignment
            for value in self.domains[var_id]:
                # Custom consistent check that records failures
                temp_assignment = assignment.copy()
                temp_assignment[var_id] = value
                
                is_valid = True
                for constraint in self.constraints:
                    if not constraint(temp_assignment, var_id, value):
                        is_valid = False
                        name = getattr(constraint, "__name__", str(constraint))
                        conflict_reasons[name] = conflict_reasons.get(name, 0) + 1
                        # Continue checking other constraints? No, usually one is enough to block.
                        # But to get full stats, maybe we want to see ALL blockers?
                        # For now, break on first failure to mimic solver behavior, 
                        # but recording the specific constraint is key.
                        break 
                
                if is_valid:
                    valid_values.append(value)
            
            if valid_values:
                # Assign the first valid one (Greedy)
                # Ideally we'd pick the "least constraining" one, but simple greedy is fine for diagnosis placement
                assignment[var_id] = valid_values[0]
            else:
                # FAILURE FOUND
                print(f"\n[CRITICAL FAILURE] Could not schedule: {var.section_title} ({var.course_code})")
                print(f"  Lecturer: {var.lecturer_id.replace('_', ' ')}")
                
                total_domain_size = len(self.domains[var_id])
                print(f"  Total possible slots allowed by domain: {total_domain_size}")
                
                print("\n  REASON FOR BLOCKAGE:")
                for reason, count in conflict_reasons.items():
                    pct = (count / total_domain_size) * 100
                    
                    reason_human = reason
                    if "lecturer" in reason: reason_human = "Lecturer Availability/Conflict"
                    elif "room" in reason: reason_human = "Room Occupied"
                    elif "cohort" in reason: reason_human = "Student Cohort Conflict"
                    
                    print(f"  - {reason_human}: Blocked {count} slots ({pct:.1f}%)")
                    
                # specific check for PEAC 100 or special rooms
                if total_domain_size == 1:
                    print("\n  [TIP] This course has a very restricted domain (only 1 option).")
                    print("  Check 'special_rooms.csv' or if it's forced to a specific day/time.")
                
                # Check if lecturer is the bottleneck
                lecturer = self.lecturers.get(var.lecturer_id)
                if lecturer:
                   avail_count = len(lecturer.available_time_slots)
                   print(f"\n  [INFO] Lecturer {lecturer.name} has {avail_count} available slots total.")
                   if avail_count < 3:
                       print("  -> REVIEW: Lecturer has very limited availability. Consider expanding in 'lecturer_availability.csv'.")
                
                print("="*60 + "\n")
                return # Stop after reporting the first major blocker
        
        print("\n[INFO] Diagnosis check passed in greedy mode??")
        print("This implies the failure is due to complex deep interaction or backtracking limits, not a simple bottleneck.")
        print("Try increasing the timeout or checking for circular resource contention.")
        print("="*60 + "\n")


    def calculate_accuracy(self, assignment: Assignment) -> float:
        """
        Calculates the accuracy score of the schedule using sklearn.metrics.accuracy_score if available.
        Accuracy is defined as the percentage of classes scheduled during a lecturer's explicitly available hours.
        """
        total = len(assignment)
        if total == 0: return 0.0
        
        # Safe import for sklearn
        try:
            from sklearn.metrics import accuracy_score
            has_sklearn = True
        except ImportError:
            has_sklearn = False
            
        y_true = []
        y_pred = []
        
        matches = 0 # Keep manual counter for fallback/speed
        
        for var_id, value in assignment.items():
            day, slot, _ = value
            sec = self.vars_by_id[var_id]
            lecturer = self.lecturers.get(sec.lecturer_id)
            
            is_match = 0
            if lecturer and (day, slot) in lecturer.available_time_slots:
                is_match = 1
                matches += 1
            
            if has_sklearn:
                y_true.append(1) # We always DESIRE a match (ideal state)
                y_pred.append(is_match) # Actual state
        
        if has_sklearn:
            return accuracy_score(y_true, y_pred) * 100.0
        else:
            return (matches / total) * 100.0

