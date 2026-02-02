import pandas as pd
import os
from typing import List, Dict

def normalize_name(name: str) -> str:
    """Normalize lecturer name for matching"""
    if not isinstance(name, str):
        return ""
    name = name.replace(".", " ").replace("-", " ")
    return " ".join(name.split()).lower()

def prompt_add_lecturer(lecturer_name: str, availability_file: str = "lecturer_availability.csv") -> List[int]:
    """
    Prompt user to add availability for a new lecturer.
    
    Args:
        lecturer_name: Name of the lecturer
        availability_file: Path to lecturer_availability.csv
    
    Returns:
        List of available day indices (0=Mon, 1=Tue, etc.)
    """
    print(f"\n{'='*60}")
    print(f"[WARNING] Lecturer '{lecturer_name}' not found in {availability_file}")
    print(f"{'='*60}")
    print("Would you like to add availability for this lecturer?")
    print("1. Yes - Add availability now")
    print("2. No - Use default (all days available)")
    print(f"{'='*60}")
    
    while True:
        choice = input("Enter choice (1 or 2): ").strip()
        if choice in ["1", "2"]:
            break
        print("[ERROR] Invalid choice. Please enter 1 or 2.")
    
    if choice == "2":
        # Default: all days available
        print(f"[INFO] Using default: {lecturer_name} available all days (Mon-Fri)")
        return [0, 1, 2, 3, 4]
    
    # Prompt for specific days
    print(f"\nSelect available days for {lecturer_name} (separate with commas):")
    print("1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday")
    print("Example: 1,2,3,4,5 for all days, or 1,3,5 for Mon/Wed/Fri")
    
    while True:
        days_input = input("Enter days: ").strip()
        try:
            # Parse input
            day_numbers = [int(d.strip()) for d in days_input.split(",")]
            # Validate range
            if all(1 <= d <= 5 for d in day_numbers):
                # Convert to 0-indexed
                available_days = [d - 1 for d in day_numbers]
                break
            else:
                print("[ERROR] Days must be between 1 and 5. Try again.")
        except ValueError:
            print("[ERROR] Invalid format. Use comma-separated numbers (e.g., 1,2,3)")
    
    # Save to CSV
    save_to_csv(lecturer_name, available_days, availability_file)
    
    day_names = ["Mon", "Tue", "Wed", "Thu", "Fri"]
    selected_names = [day_names[i] for i in available_days]
    print(f"✓ Saved: {lecturer_name} available on {', '.join(selected_names)}")
    
    return available_days

def prompt_expand_availability(lecturer_name: str, current_days: List[int], 
                                availability_file: str = "lecturer_availability.csv") -> List[int]:
    """
    Prompt user to expand availability for a lecturer with limited days.
    
    Args:
        lecturer_name: Name of the lecturer
        current_days: List of currently available day indices
        availability_file: Path to lecturer_availability.csv
    
    Returns:
        List of available day indices (may be unchanged or expanded)
    """
    day_names = ["Mon", "Tue", "Wed", "Thu", "Fri"]
    current_names = [day_names[i] for i in current_days]
    
    print(f"\n{'='*60}")
    print(f"[INFO] Lecturer '{lecturer_name}' has limited availability:")
    print(f"  Currently available: {', '.join(current_names)} ({len(current_days)} days)")
    print(f"{'='*60}")
    print("Would you like to expand availability?")
    print("1. Yes - Add more days")
    print("2. No - AI will work with current availability")
    print(f"{'='*60}")
    
    while True:
        choice = input("Enter choice (1 or 2): ").strip()
        if choice in ["1", "2"]:
            break
        print("[ERROR] Invalid choice. Please enter 1 or 2.")
    
    if choice == "2":
        # Keep current availability - AI will use what's available
        print(f"[INFO] AI will schedule {lecturer_name} on available days: {', '.join(current_names)}")
        return current_days
    
    # Show available days to add
    available_to_add = [i for i in range(5) if i not in current_days]
    if not available_to_add:
        print("[INFO] Lecturer already available all days!")
        return current_days
    
    print(f"\nAdd additional days (current: {', '.join(current_names)}):")
    for i in available_to_add:
        print(f"{i+1}={day_names[i]}")
    print("Enter days to add (comma-separated):")
    
    while True:
        days_input = input("Days to add: ").strip()
        if not days_input:
            print("[INFO] No days added. Keeping current availability.")
            return current_days
        
        try:
            day_numbers = [int(d.strip()) for d in days_input.split(",")]
            # Convert to 0-indexed
            new_days = [d - 1 for d in day_numbers]
            # Validate they're actually available to add
            if all(d in available_to_add for d in new_days):
                # Combine with current days
                updated_days = sorted(set(current_days + new_days))
                break
            else:
                print(f"[ERROR] Can only add: {', '.join([day_names[i] for i in available_to_add])}")
        except ValueError:
            print("[ERROR] Invalid format. Use comma-separated numbers.")
    
    # Save updated availability
    save_to_csv(lecturer_name, updated_days, availability_file)
    
    updated_names = [day_names[i] for i in updated_days]
    print(f"✓ Updated: {lecturer_name} now available on {', '.join(updated_names)}")
    
    return updated_days

def save_to_csv(lecturer_name: str, available_days: List[int], 
                availability_file: str = "lecturer_availability.csv"):
    """
    Save or update lecturer availability in CSV file.
    
    Args:
        lecturer_name: Name of the lecturer
        available_days: List of available day indices (0=Mon, 1=Tue, etc.)
        availability_file: Path to CSV file
    """
    # Create availability row
    availability_row = {
        'lecturer_name': lecturer_name,
        'Mon': 1 if 0 in available_days else 0,
        'Tue': 1 if 1 in available_days else 0,
        'Wed': 1 if 2 in available_days else 0,
        'Thu': 1 if 3 in available_days else 0,
        'Fri': 1 if 4 in available_days else 0
    }
    
    # Load existing CSV or create new
    if os.path.exists(availability_file):
        df = pd.read_csv(availability_file)
        
        # Check if lecturer already exists
        norm_name = normalize_name(lecturer_name)
        existing_idx = None
        for idx, row in df.iterrows():
            if normalize_name(row['lecturer_name']) == norm_name:
                existing_idx = idx
                break
        
        if existing_idx is not None:
            # Update existing row
            for col in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']:
                df.at[existing_idx, col] = availability_row[col]
        else:
            # Append new row
            df = pd.concat([df, pd.DataFrame([availability_row])], ignore_index=True)
    else:
        # Create new file
        df = pd.DataFrame([availability_row])
    
    # Save to CSV
    df.to_csv(availability_file, index=False)

def check_and_prompt_availability(lecturers_in_input: List[str], 
                                   availability_file: str = "lecturer_availability.csv",
                                   min_days_threshold: int = 3,
                                   mode_override: str = None) -> Dict[str, List[int]]:
    """
    Check lecturer availability and prompt for missing or limited entries.
    mode_override: "1" = Auto (AI Auto-Expand), "2" = Manual (Prompt/Strict), but without prompt in headless.
    """
    availability_map = {}
    
    # 1. Ask High-Level Preference: AI vs Manual
    ai_decides = False
    
    if mode_override:
        # Headless selection
        ai_decides = (mode_override == "1")
    else:
        # Interactive Prompt
        print("\n" + "="*60)
        print("AVAILABILITY MANAGEMENT PREFERENCE")
        print("="*60)
        print("How should the AI handle lecturer availability gaps?")
        print("1. AI Automatic - AI will intelligently expand days to find a solution")
        print("2. Manual Control - Prompt me for every missing or limited lecturer")
        print("="*60)
        
        while True:
            mode_choice = input("Enter choice (1 or 2): ").strip()
            if mode_choice in ["1", "2"]: break
            print("[ERROR] Invalid choice.")
        
        ai_decides = (mode_choice == "1")

    # Load existing availability

    # Load existing availability
    existing_availability = {}
    if os.path.exists(availability_file):
        df = pd.read_csv(availability_file)
        for _, row in df.iterrows():
            if 'lecturer_name' in row:
                name_col = 'lecturer_name'
            elif 'Name' in row:
                name_col = 'Name'
            elif 'name' in row:
                name_col = 'name'
            else:
                # Default to first column if nothing matches and df has columns
                if not df.empty and len(df.columns) > 0:
                    name_col = df.columns[0]
                else:
                    # If no columns or empty df, skip this row
                    continue
                 
            name = str(row[name_col]).strip()
            norm_name = normalize_name(name)
            days = []
            for i, day in enumerate(['Mon', 'Tue', 'Wed', 'Thu', 'Fri']):
                if str(row.get(day, 0)) == "1":
                    days.append(i)
            existing_availability[norm_name] = (name, days)
    
    # Check each lecturer
    for lecturer in lecturers_in_input:
        norm_name = normalize_name(lecturer)
        
        if norm_name in existing_availability:
            original_name, days = existing_availability[norm_name]
            
            # Check if availability is limited
            if len(days) < min_days_threshold:
                if ai_decides:
                    # AI expands automatically
                    days = [0, 1, 2, 3, 4]
                elif mode_override:
                    # Strict Headless Mode: Use existing days (do not expand, do not prompt)
                    pass 
                else:
                    # Interactive Manual prompt
                    days = prompt_expand_availability_refined(original_name, days, availability_file)
            
            availability_map[lecturer] = days
        else:
            # Lecturer not found
            if ai_decides:
                # Default to all days for new lecturers
                days = [0, 1, 2, 3, 4]
            elif mode_override:
                 # Strict Headless Mode: Lecturer has NO availability defined.
                 # Default to all days? Or 0? Usually default to all days to avoid unschedulable error.
                 # Let's default to all days but maybe log a warning.
                 days = [0, 1, 2, 3, 4] 
            else:
                days = prompt_add_lecturer(lecturer, availability_file)
            availability_map[lecturer] = days
    
    return availability_map

def prompt_expand_availability_refined(lecturer_name: str, current_days: List[int], 
                                        availability_file: str = "lecturer_availability.csv") -> List[int]:
    """Refined manual prompt for < 3 days choice."""
    day_names = ["Mon", "Tue", "Wed", "Thu", "Fri"]
    current_names = [day_names[i] for i in current_days]
    
    print(f"\n{'='*60}")
    print(f"[INFO] Lecturer '{lecturer_name}' has limited availability (< 3 days):")
    print(f"  Available: {', '.join(current_names)}")
    print(f"{'='*60}")
    print("Choose an option:")
    print("1. Expand - Add more days manually")
    print("2. Proceed with Limited - AI only uses these specific days")
    print(f"{'='*60}")
    
    while True:
        choice = input("Enter choice (1 or 2): ").strip()
        if choice in ["1", "2"]: break
    
    if choice == "2":
        return current_days
    
    # If choice is 1, reuse the original expansion logic
    return prompt_expand_availability(lecturer_name, current_days, availability_file)

def update_availability_file(availability_map: Dict[str, List[int]], availability_file: str):
    """
    Saves the availability map back to the CSV file.
    """
    day_names = ["Mon", "Tue", "Wed", "Thu", "Fri"]
    rows = []
    for lecturer, days in availability_map.items():
        row = {"lecturer_name": lecturer}
        for i, day in enumerate(day_names):
            row[day] = 1 if i in days else 0
        rows.append(row)
    
    df = pd.DataFrame(rows)
    # Ensure correct column order
    cols = ["lecturer_name"] + day_names
    df = df[cols]
    df.to_csv(availability_file, index=False)
    print(f"[AI] Availability updated in {availability_file}")

if __name__ == "__main__":
    # Test the system
    print("Testing Lecturer Availability Management")
    print("="*60)
    
    test_lecturers = ["Dr. Test Lecturer", "Prof. Limited Days"]
    result = check_and_prompt_availability(test_lecturers)
    
    print("\n" + "="*60)
    print("Final Availability:")
    for lect, days in result.items():
        day_names = ["Mon", "Tue", "Wed", "Thu", "Fri"]
        print(f"  {lect}: {', '.join([day_names[i] for i in days])}")
