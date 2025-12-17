import csv
import re

# Read the NHIS tariffs
nhis_items = []
import os
script_dir = os.path.dirname(os.path.abspath(__file__))
with open(os.path.join(script_dir, 'nhis_tariffs_import.csv'), 'r', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        nhis_items.append(row)

print(f'Read {len(nhis_items)} NHIS items')

# Convert to drug import format
drugs = []
for item in nhis_items:
    nhis_code = item['nhis_code']
    name = item['name'].strip()
    unit = item.get('unit', '').strip()
    
    # The NHIS CSV has names split across 'name' and 'unit' columns
    # e.g., name="Artemether + Lumefantrine Tablet, 20 mg + 120" unit="mg (24's) 1 Course"
    # We need to combine them to get the full name with pack size
    if unit:
        # Extract pack size info like "(24's)", "(6's)", "(12 tabs)" from unit
        # Note: Handle various apostrophe characters: ' ` and Unicode right single quote
        pack_match = re.search(r"(\(\d+['`\u2019]?s?\)|\(\d+\s*tabs?\))", unit, re.IGNORECASE)
        if pack_match:
            pack_size = pack_match.group(1)
            # Normalize apostrophe to standard single quote
            pack_size = pack_size.replace('\u2019', "'").replace('`', "'")
            # Check if name appears truncated (ends mid-word or with partial strength)
            # If unit starts with continuation of strength (e.g., "mg"), append it
            if unit.startswith('mg'):
                # Name was cut off mid-strength, append the rest
                strength_continuation = re.match(r'^(mg[^(]*)', unit)
                if strength_continuation:
                    name = name + ' ' + strength_continuation.group(1).strip()
            # Append pack size to name
            if pack_size not in name:
                name = name + ' ' + pack_size
    
    # Try to extract form from name
    form = 'other'
    name_lower = name.lower()
    if 'tablet' in name_lower or 'tab' in name_lower:
        form = 'tablet'
    elif 'capsule' in name_lower or 'cap' in name_lower:
        form = 'capsule'
    elif 'syrup' in name_lower or 'syr' in name_lower:
        form = 'syrup'
    elif 'suspension' in name_lower or 'susp' in name_lower:
        form = 'suspension'
    elif 'injection' in name_lower or 'inj' in name_lower:
        form = 'injection'
    elif 'cream' in name_lower:
        form = 'cream'
    elif 'ointment' in name_lower:
        form = 'ointment'
    elif 'drops' in name_lower or 'drop' in name_lower:
        form = 'drops'
    elif 'inhaler' in name_lower:
        form = 'inhaler'
    elif 'patch' in name_lower:
        form = 'patch'
    elif 'powder' in name_lower or 'granul' in name_lower:
        form = 'other'
    elif 'lotion' in name_lower or 'solution' in name_lower:
        form = 'other'
    elif 'suppository' in name_lower or 'supp' in name_lower:
        form = 'other'
    
    # Determine unit_type based on form (affects prescription quantity calculations)
    unit_type = 'piece'  # default for tablets, capsules
    if form in ['syrup', 'suspension']:
        unit_type = 'bottle'
    elif form == 'injection':
        unit_type = 'vial'
    elif form in ['cream', 'ointment']:
        unit_type = 'tube'
    elif form == 'drops':
        unit_type = 'bottle'
    elif form == 'inhaler':
        unit_type = 'piece'  # inhalers are dispensed as units
    elif form == 'patch':
        unit_type = 'box'
    
    # Try to extract generic name (first word usually)
    generic_name = name.split()[0] if name else ''
    # Remove common suffixes
    generic_name = re.sub(r'\s*(Tablet|Capsule|Injection|Syrup|Suspension|Cream|Ointment|Drops|Inhaler).*', '', generic_name, flags=re.IGNORECASE)
    
    # Try to extract strength (concentration like "100 mg/5 mL" or "200 mg/mL")
    strength_match = re.search(r'(\d+\.?\d*\s*(mg|g|mcg|iu|%|microgram)[/\d\s]*(ml|g)?)', name, re.IGNORECASE)
    strength = strength_match.group(1).strip() if strength_match else ''
    
    # Try to extract bottle/vial size (standalone volume at end like "100 mL", "10 mL", "1 mL")
    # This is different from concentration - it's the total container volume
    # Pattern: look for a standalone number + mL/ml at the end, not part of concentration
    bottle_size = None
    # Match patterns like "100 mL" or "10ml" at the end of the name, after the concentration
    bottle_match = re.search(r'(?:^|[,\s])(\d+\.?\d*)\s*ml\s*$', name, re.IGNORECASE)
    if bottle_match:
        bottle_size = int(float(bottle_match.group(1)))
    
    # Determine category based on common drug names
    category = 'other'
    if any(x in name_lower for x in ['amoxicillin', 'ampicillin', 'penicillin', 'cephalosporin', 'cefuroxime', 'ceftriaxone', 'azithromycin', 'erythromycin', 'metronidazole', 'ciprofloxacin', 'gentamicin', 'cloxacillin', 'flucloxacillin', 'doxycycline', 'tetracycline', 'cotrimoxazole', 'chloramphenicol']):
        category = 'antibiotics'
    elif any(x in name_lower for x in ['paracetamol', 'ibuprofen', 'diclofenac', 'aspirin', 'tramadol', 'morphine', 'codeine', 'pethidine', 'acetylsalicylic']):
        category = 'analgesics'
    elif any(x in name_lower for x in ['acyclovir', 'zidovudine', 'lamivudine', 'efavirenz', 'nevirapine', 'tenofovir', 'abacavir']):
        category = 'antivirals'
    elif any(x in name_lower for x in ['fluconazole', 'ketoconazole', 'nystatin', 'clotrimazole', 'miconazole', 'griseofulvin']):
        category = 'antifungals'
    elif any(x in name_lower for x in ['amlodipine', 'atenolol', 'propranolol', 'nifedipine', 'lisinopril', 'enalapril', 'losartan', 'digoxin', 'furosemide', 'hydrochlorothiazide', 'aspirin', 'warfarin', 'heparin', 'clopidogrel']):
        category = 'cardiovascular'
    elif any(x in name_lower for x in ['metformin', 'glibenclamide', 'gliclazide', 'insulin', 'glimepiride']):
        category = 'diabetes'
    elif any(x in name_lower for x in ['salbutamol', 'aminophylline', 'theophylline', 'beclomethasone', 'budesonide', 'prednisolone', 'hydrocortisone']):
        category = 'respiratory'
    elif any(x in name_lower for x in ['omeprazole', 'ranitidine', 'antacid', 'magnesium trisilicate', 'metoclopramide', 'domperidone', 'loperamide', 'oral rehydration']):
        category = 'gastrointestinal'
    elif any(x in name_lower for x in ['diazepam', 'phenytoin', 'carbamazepine', 'phenobarbital', 'valproate', 'levodopa']):
        category = 'neurological'
    elif any(x in name_lower for x in ['amitriptyline', 'fluoxetine', 'haloperidol', 'chlorpromazine', 'risperidone', 'olanzapine']):
        category = 'psychiatric'
    elif any(x in name_lower for x in ['hydrocortisone cream', 'betamethasone', 'calamine', 'benzoyl peroxide', 'permethrin', 'benzyl benzoate']):
        category = 'dermatological'
    elif any(x in name_lower for x in ['vaccine', 'immunoglobulin', 'tetanus', 'hepatitis']):
        category = 'vaccines'
    elif any(x in name_lower for x in ['vitamin', 'folic acid', 'ferrous', 'iron', 'calcium', 'zinc', 'multivitamin']):
        category = 'vitamins'
    
    drugs.append({
        'drug_code': nhis_code,
        'name': name,
        'generic_name': generic_name,
        'form': form,
        'strength': strength,
        'unit_price': '',  # User fills this in
        'unit_type': unit_type,
        'bottle_size': bottle_size if bottle_size else '',  # Volume in ml for bottles/vials
        'category': category,
        'min_stock': '',  # Use default
        'nhis_code': nhis_code,  # Same as drug_code for auto-mapping
    })

# Write to CSV
output_file = os.path.join(script_dir, 'nhis_drugs_for_import.csv')
with open(output_file, 'w', newline='', encoding='utf-8') as f:
    fieldnames = ['drug_code', 'name', 'generic_name', 'form', 'strength', 'unit_price', 'unit_type', 'bottle_size', 'category', 'min_stock', 'nhis_code']
    writer = csv.DictWriter(f, fieldnames=fieldnames)
    writer.writeheader()
    writer.writerows(drugs)

print(f'Created {output_file} with {len(drugs)} drugs')
print('')
print('Categories breakdown:')
from collections import Counter
cats = Counter(d['category'] for d in drugs)
for cat, count in cats.most_common():
    print(f'  {cat}: {count}')
print('')
print('Forms breakdown:')
forms = Counter(d['form'] for d in drugs)
for form, count in forms.most_common():
    print(f'  {form}: {count}')
print('')
print('Unit types breakdown:')
unit_types = Counter(d['unit_type'] for d in drugs)
for ut, count in unit_types.most_common():
    print(f'  {ut}: {count}')
