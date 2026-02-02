import csv
import os
import sys

# Try to import fpdf, print helpful error if missing
try:
    from fpdf import FPDF
    from fpdf.enums import XPos, YPos
except ImportError:
    print("Error: The 'fpdf2' library is required to generate PDFs.")
    print("Please install it by running: pip install fpdf2")
    sys.exit(1)

class TimetablePDF(FPDF):
    def header(self):
        # We handle the header manually inside the table logic to keep it integrated with the grid
        pass

    def footer(self):
        self.set_y(-15)
        self.set_font('Helvetica', 'I', 8)
        self.set_text_color(0, 0, 0) # Black for footer
        self.cell(0, 10, f'Page {self.page_no()}/{{nb}}', border=0, 
                  new_x=XPos.RIGHT, new_y=YPos.TOP, align='C')

def create_pdf(csv_input, pdf_output, custom_headers=None):
    if not os.path.exists(csv_input):
        print(f"Error: {csv_input} not found.")
        return

    # PDF Configuration
    pdf = TimetablePDF(orientation='L')
    pdf.alias_nb_pages()
    pdf.add_page()
    
    # Define Columns and Widths (A4 Landscape ~277mm usable width)
    columns = [
        ("LECTURER", 55),
        ("COURSE CODE & TITLE", 91),
        ("CREDIT HRS", 21),
        ("CLASSROOM", 40),
        ("DAYS", 25),
        ("TIMINGS", 45)
    ]
    total_w = sum(w for _, w in columns)

    mapping = {
        "LECTURER": "Lecturer Name",
        "COURSE CODE & TITLE": "Course Code & Title",
        "CREDIT HRS": "Credit Hrs",
        "CLASSROOM": "Room Name",
        "DAYS": "Day",
        "TIMINGS": "Time"
    }

    # --- TOP HEADER SECTION (Integrated into Grid) ---
    pdf.set_font('Helvetica', 'B', 12)
    pdf.set_text_color(0, 0, 0) # Black for headers
    
    if custom_headers and len(custom_headers) >= 4:
        headers = custom_headers
    else:
        headers = [
            "VALLEY VIEW UNIVERSITY",
            "COMPUTER SCIENCE, INFORMATION TECHNOLOGY, BUSINESS INFORMATION SYSTEMS AND MATHEMATICAL SCIENCES",
            "SECOND SEMESTER - 2025 / 2026 ACADEMIC YEAR",
            "TEACHING TIMETABLE"
        ]
    
    for h_line in headers:
        pdf.cell(total_w, 10, h_line, border=1, align='C', new_x=XPos.LMARGIN, new_y=YPos.NEXT)
    
    # --- COLUMN HEADERS ---
    pdf.set_font('Helvetica', 'B', 10)
    for name, width in columns:
        pdf.cell(width, 10, name, border=1, align='C', new_x=XPos.RIGHT, new_y=YPos.TOP)
    pdf.ln()

    # Data Source Loading
    with open(csv_input, 'r', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f)
        source_rows = list(reader)
        
    # Row Expansion Logic
    expanded_rows = []
    for row in source_rows:
        codes = row.get("Course Code", "").split(" / ")
        titles = row.get("Course Title", "").split(" / ")
        num_parts = max(len(codes), len(titles))
        for i in range(num_parts):
            new_row = row.copy()
            c = codes[i].strip() if i < len(codes) else codes[-1].strip()
            t = titles[i].strip() if i < len(titles) else titles[-1].strip()
            new_row["Course Code & Title"] = f"{c} - {t}"
            expanded_rows.append(new_row)

    # Sort by Lecturer
    expanded_rows.sort(key=lambda x: x.get("Lecturer Name", ""))
    
    pdf.set_font('Helvetica', '', 9)
    current_lecturer = None
    
    for row in expanded_rows:
        lecturer = row.get("Lecturer Name", "")
        
        # Add blank separator row between lecturer groups
        if current_lecturer is not None and lecturer != current_lecturer:
            # Draw blank row with borders
            pdf.set_fill_color(255, 255, 255)
            for _, width in columns:
                pdf.cell(width, 4, "", border=1, new_x=XPos.RIGHT, new_y=YPos.TOP)
            pdf.ln()
            
            # Check for page break
            if pdf.get_y() > 180:
                pdf.add_page()
                # Repeat Column Headers on New Page
                pdf.set_font('Helvetica', 'B', 10)
                pdf.set_text_color(0, 0, 0)
                for name, width in columns:
                    pdf.cell(width, 10, name, border=1, align='C', new_x=XPos.RIGHT, new_y=YPos.TOP)
                pdf.ln()
                pdf.set_font('Helvetica', '', 9)
            
        current_lecturer = lecturer
        row_height = 8

        # --- DATA ROW COLOR: RED ---
        pdf.set_text_color(255, 0, 0) 

        # Replace the data row loop logic with a more robust version
    for col_name, width in columns:
        csv_key = mapping[col_name]
        text = str(row.get(csv_key, ""))
        
        # Get current Y position to ensure all cells in a row are the same height
        start_y = pdf.get_y()
        start_x = pdf.get_x()

        # Use multi_cell for wrapping, or keep cell for single line
        pdf.multi_cell(width, row_height, text, border=1, align=align)
        
        # Move cursor back to the top of the row for the next column
        pdf.set_xy(start_x + width, start_y)

    pdf.ln(row_height) # Move to next line after all columns are drawn
        
    # Reset color for final status
    pdf.set_text_color(0, 0, 0)
    # Output the PDF
    try:
        pdf.output(pdf_output)
        print(f"Success! Timetable converted to {pdf_output}")
    except Exception as e:
        print(f"Error saving PDF: {e}")


if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description='CSV to PDF Timetable Converter')
    parser.add_argument('--input', help='Input CSV file')
    parser.add_argument('--output', help='Output PDF file')
    parser.add_argument('--h1', help='Header line 1')
    parser.add_argument('--h2', help='Header line 2')
    parser.add_argument('--h3', help='Header line 3')
    parser.add_argument('--h4', help='Header line 4')
    
    args = parser.parse_args()
    
    in_csv = args.input if args.input else "vvu_general_schedule.csv"
    out_pdf = args.output if args.output else "vvu_final_timetable.pdf"
    
    custom = None
    if args.h1 and args.h2 and args.h3 and args.h4:
        custom = [args.h1, args.h2, args.h3, args.h4]
        
    if not out_pdf.endswith(".pdf"):
        out_pdf += ".pdf"
        
    create_pdf(in_csv, out_pdf, custom)
