import pandas as pd
import os
import pickle

TIME_TO_SLOT = {
    "7:00am": 0, "07:00am": 0, "7:00": 0, "7:00 am": 0,
    "10:00am": 1, "10:00": 1, "10:00 am": 1,
    "2:00pm": 2, "14:00": 2, "2:00 pm": 2,
    "5:00pm": 3, "17:00": 3, "5:00 pm": 3
}


day_map = {"Mon":0, "Tue":1, "Wed":2, "Thu":3, "Fri":4,
           "Monday":0, "Tuesday":1, "Wednesday":2, "Thursday":3, "Friday":4}

def train_model(history_data: str, feedback_data: str = "user_feedback.csv",
                model_save_path: str = "scheduling_model.pkl"):
    """
    Trains the AI intelligence model using historical scheduling data and user feedback.
    """
    
    lecturer_weights = {}
    room_weights = {}
    slot_popularity = {}
    custom_conflicts = {}
    
    #1. Load historical scheduling data
    if os.path.exists(history_data):
        history_df = pd.read_csv(history_data, on_bad_lines='skip')
        history_df.columns = [col.strip().lower() for col in history_df.columns]
        
        for _, row in history_df.iterrows():
            lecturer = str(row.get('lecturer_name', '')).strip()
            day_str = str(row.get('day', '')).strip()
            time_str = str(row.get('time', str(row.get('start_time', '')))).split("_")[0].strip().lower()
            room = str(row.get('room_name', '')).strip().replace(" ", "_")
            course = str(row.get('course_code', '')).strip()
            day_idx = day_map.get(day_str)
            slot_idx = TIME_TO_SLOT.get(time_str)
            
            if day_idx is not None and slot_idx is not None:
                if lecturer:
                    key = (lecturer, day_idx, slot_idx)
                    lecturer_weights[key] = lecturer_weights.get(key, 0) + 1
                if room and course:
                    key = (course, room)
                    room_weights[key] = room_weights.get(key, 0) + 1
                s_key = (day_idx, slot_idx)
                slot_popularity[s_key] = slot_popularity.get(s_key, 0) + 1
    #2. Load user feedback data
    if os.path.exists(feedback_data):
        feedback_df = pd.read_csv(feedback_data)
        
        for _, row in feedback_df.iterrows():
            feedback_type = str(row['feedback_type']).upper()
            item1 = str(row['item_1']).strip()
            item2 = str(row['item_2']).strip()
            value = row.get('weight/value', 50) # Default neutral weight
            
            if feedback_type == "CONFLICT":
                #item1 and item2 are course code that shouldn't clash
                custom_conflicts[(item1, item2)] = value
                
            elif feedback_type == "PREFERENCE":
                #item1 could be lecturer or course
                #item2 could be time slot or room
                day_idx = day_map.get(item2)
                if day_idx is not None:
                    #Apply a massive boost to the model weights
                    
                    for s in range(3):
                        lecturer_weights[(item1, day_idx, s)] = lecturer_weights.get((item1, day_idx, s), 0) + value 
                        
    model_data = {
        "lecturer_slots": lecturer_weights,
        "course_rooms": room_weights,
        "global_slots": slot_popularity,
        "custom_conflicts": custom_conflicts
    }
    
    with open(model_save_path, "wb") as f:
        pickle.dump(model_data, f)
    print(f"Model trained and saved to {model_save_path}")
    return model_data

def load_trained_model(model_path: str = "scheduling_model.pkl"):
    """
    Loads the trained AI intelligence model from disk.
    """
    if os.path.exists(model_path):
        with open(model_path, "rb") as f:
            model_data = pickle.load(f)
        print(f"Model loaded from {model_path}")
        return model_data
    return {}