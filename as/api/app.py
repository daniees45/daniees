from flask import Flask, request, jsonify
import subprocess
import os
import sys

# Add project root to path to import main_web
sys.path.append(os.path.join(os.path.dirname(__file__), '../../'))

try:
    from main_web import run_headless
except ImportError:
    run_headless = None

from flask_cors import CORS
app = Flask(__name__)
CORS(app) # Enable CORS for all routes

# Config
PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '../../'))
INPUT_FILE = os.path.join(PROJECT_ROOT, 'departmental_courses.csv') # Default input
OUTPUT_FILE = os.path.join(PROJECT_ROOT, 'final_web_schedule.csv')

@app.route('/health', methods=['GET'])
def health():
    return jsonify({"status": "ok", "message": "VVU Scheduler API Ready"})

@app.route('/generate', methods=['POST'])
def generate():
    data = request.json or {}
    
    # 1. Update CSVs from DB (Optional, PHP should have done this)
    # But for now, we assume CSVs are ready
    
    # Handle Input File Selection
    chosen_file = data.get('input_file')
    if chosen_file:
        # Security: prevent directory traversal
        if ".." in chosen_file or "/" in chosen_file or "\\" in chosen_file:
             return jsonify({"status": "error", "message": "Invalid filename security check"}), 400
        
        target_path = os.path.join(PROJECT_ROOT, chosen_file)
        if os.path.exists(target_path):
             INPUT_FILE = target_path
        else:
             return jsonify({"status": "error", "message": f"Input file not found: {chosen_file}"}), 404
             
    # Handle Output File Selection
    chosen_out = data.get('output_file')
    if chosen_out:
        if ".." in chosen_out or "/" in chosen_out or "\\" in chosen_out:
             return jsonify({"status": "error", "message": "Invalid output filename security check"}), 400
        OUTPUT_FILE = os.path.join(PROJECT_ROOT, chosen_out)

    # Optional: Course Type & Dept
    c_type = data.get('course_type', 'Departmental')
    dept = data.get('department', '1')
    avail_mode = data.get('availability_mode', '1') # 1=Auto, 2=Strict
    exam_mode = data.get('exam_mode', False)

    # Check if we have the module
    if not run_headless:
        return jsonify({"status": "error", "message": "Could not import scheduler module"}), 500

    try:
        # Run the scheduler
        # mode_choice=2 (Headless/Web), ai_preference=1 (Load from model)
        success, accuracy = run_headless(INPUT_FILE, 2, OUTPUT_FILE, 1, c_type, dept, avail_mode, exam_mode=exam_mode)
        
        if success:
            return jsonify({
                "status": "success", 
                "message": "Schedule generated successfully",
                "accuracy": f"{accuracy:.2f}%",
                "output_file": OUTPUT_FILE
            })
        else:
            return jsonify({"status": "error", "message": "AI failed to find a valid schedule"}), 400
            
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

if __name__ == '__main__':
    app.run(port=5000, debug=True)
