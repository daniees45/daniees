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

def create_pdf(csv_input, pdf_output):
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

        for col_name, width in columns:
            csv_key = mapping[col_name]
            text = str(row.get(csv_key, ""))
            
            # Aligment Logic: Left for text, Center for others
            align = 'L' if col_name in ["LECTURER", "COURSE CODE & TITLE"] else 'C'
            
            # Padding for Left Alignment
            display_text = f" {text}" if align == 'L' else text
            
            # Truncate
            max_chars = int(width * 0.9)
            if len(display_text) > max_chars:
                display_text = display_text[:max_chars-3] + "..."
            
            pdf.cell(width, row_height, display_text, border=1, 
                        new_x=XPos.RIGHT, new_y=YPos.TOP, align=align)
        
        pdf.ln()
    
    # Reset color for final status
    pdf.set_text_color(0, 0, 0)
    # Output the PDF
    try:
        pdf.output(pdf_output)
        print(f"Success! Timetable converted to {pdf_output}")
    except Exception as e:
        print(f"Error saving PDF: {e}")


if __name__ == "__main__":
    default_input = "vvu_final_4.csv"
    default_output = "vvu_final_timetable.pdf"
    
    print("CSV to PDF Converter")
    if len(sys.argv) > 1:
        in_csv = sys.argv[1]
    else:
        in_csv = input(f"Enter input CSV [{default_input}]: ").strip() or default_input
        
    if len(sys.argv) > 2:
        out_pdf = sys.argv[2]
    else:
        out_pdf = input(f"Enter output PDF [{default_output}]: ").strip() or default_output
    
    if not out_pdf.endswith(".pdf"):
        out_pdf += ".pdf"
        
    create_pdf(in_csv, out_pdf)
