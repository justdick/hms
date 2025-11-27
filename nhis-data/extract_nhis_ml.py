import PyPDF2
import re
import csv

# Read the PDF
medicines = []

with open('2O25 NHIS ML.pdf', 'rb') as f:
    reader = PyPDF2.PdfReader(f)
    print(f'PDF has {len(reader.pages)} pages')
    
    # Extract from page 11 onwards (where medicines list starts)
    for i in range(10, len(reader.pages)):
        text = reader.pages[i].extract_text()
        if not text:
            continue
        
        # Split into lines
        lines = text.split('\n')
        
        current_code = None
        current_name = None
        current_unit = None
        current_price = None
        
        for line in lines:
            line = line.strip()
            
            # Skip header lines
            if 'NHIS Medicines List' in line or 'Page' in line or not line:
                continue
            if line.startswith('CODE') or line.startswith('GENERIC NAME'):
                continue
            
            # Check if line starts with a medicine code (all caps, alphanumeric)
            code_match = re.match(r'^([A-Z]{2,}[A-Z0-9]{2,})\s+(.+)$', line)
            if code_match:
                # Save previous medicine if exists
                if current_code and current_name and current_price:
                    medicines.append({
                        'nhis_code': current_code,
                        'name': current_name.strip(),
                        'category': 'medicine',
                        'price': current_price,
                        'unit': current_unit or ''
                    })
                
                current_code = code_match.group(1)
                current_name = code_match.group(2)
                current_unit = None
                current_price = None
                continue
            
            # Check if line is a unit of pricing
            unit_patterns = ['Tablet', 'Capsule', 'Vial', 'Ampoule', 'mL', 'Inhaler', 'Supp', 'Sachet', 'Course', 'G']
            for pattern in unit_patterns:
                if pattern in line and len(line) < 30:
                    current_unit = line
                    break
            
            # Check if line is a price (number with decimal)
            price_match = re.match(r'^(\d+\.?\d*)\s*([A-Z0-9]*)?$', line)
            if price_match and current_code:
                current_price = price_match.group(1)
                # Next part might be level of prescribing, ignore it

# Don't forget the last medicine
if current_code and current_name and current_price:
    medicines.append({
        'nhis_code': current_code,
        'name': current_name.strip(),
        'category': 'medicine',
        'price': current_price,
        'unit': current_unit or ''
    })

print(f'Extracted {len(medicines)} medicines')

# Write to CSV
with open('nhis_tariffs_import.csv', 'w', newline='', encoding='utf-8') as f:
    writer = csv.DictWriter(f, fieldnames=['nhis_code', 'name', 'category', 'price', 'unit'])
    writer.writeheader()
    writer.writerows(medicines)

print(f'Created nhis_tariffs_import.csv')
print('')
print('Sample medicines:')
for med in medicines[:10]:
    print(f"  {med['nhis_code']} | {med['name'][:50]} | GHS {med['price']}")
