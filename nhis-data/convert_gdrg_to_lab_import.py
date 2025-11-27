#!/usr/bin/env python3
"""
Convert NHIS G-DRG Investigation tariffs to Lab Service import format.
Extracts INVESTIGATION category items and formats them for HMS lab service import.
"""

import csv

# Read the G-DRG tariffs and extract INVESTIGATION items
investigations = []

with open('nhis-data/gdrg_tariffs_import.csv', 'r', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        if row.get('mdc_category') == 'INVESTIGATION':
            investigations.append(row)

print(f"Found {len(investigations)} investigation items")

# Categorize lab tests based on name patterns
def categorize_test(name):
    name_lower = name.lower()
    
    # Imaging
    if any(x in name_lower for x in ['x-ray', 'xray', 'ct scan', 'mri', 'ultrasound', 'scan', 'mammogram', 'doppler', 'barium', 'urography', 'venogram', 'myelogram', 'sialogram', 'cystogram', 'fistulogram', 'ductologram', 'hysterosalpingogram', 'urethrogram', 'cholangiography']):
        return 'Imaging'
    
    # Hematology
    if any(x in name_lower for x in ['blood count', 'fbc', 'haemoglobin', 'hemoglobin', 'hematocrit', 'platelet', 'reticulocyte', 'esr', 'bleeding', 'clotting', 'prothrombin', 'factor viii', 'factor ix', 'sickling', 'electrophoresis', 'bone marrow', 'coombs', 'blood grouping', 'grouping', 'rh typing', 'leucocyte', 'wbc', 'aec']):
        return 'Hematology'
    
    # Biochemistry
    if any(x in name_lower for x in ['glucose', 'sugar', 'urea', 'creatinine', 'electrolyte', 'sodium', 'potassium', 'chloride', 'calcium', 'phosphorus', 'magnesium', 'bilirubin', 'protein', 'albumin', 'cholesterol', 'triglyceride', 'lipid', 'hdl', 'ldl', 'vldl', 'lft', 'alt', 'ast', 'ggt', 'alkaline phosphatase', 'ldh', 'amylase', 'uric acid', 'iron', 'ferritin', 'tibc', 'renal function', 'ogtt', 'hba1c', 'glycosylated']):
        return 'Biochemistry'
    
    # Microbiology
    if any(x in name_lower for x in ['c/s', 'culture', 'sensitivity', 'swab', 'stool', 'urine c/', 'csf', 'fungal']):
        return 'Microbiology'
    
    # Serology/Immunology
    if any(x in name_lower for x in ['hiv', 'hepatitis', 'hbsag', 'hbv', 'vdrl', 'widal', 'aso', 'rheumatoid', 'le cell', 'cd4', 'viral serology', 'anti-streptolysin', 'c reactive', 'typhi dot', 'helicobacter']):
        return 'Serology'
    
    # Hormones
    if any(x in name_lower for x in ['hormone', 'fsh', 'lh', 'tsh', 'thyroid', 't3', 't4', 'ft3', 'ft4', 'prolactin', 'testosterone', 'estrogen', 'progesterone', 'cortisol', 'acth', 'dhea', 'hcg', 'beta-human']):
        return 'Hormones'
    
    # Tumor Markers
    if any(x in name_lower for x in ['psa', 'cea', 'afp', 'alpha-fetoprotein', 'cancer antigen', 'ca 19']):
        return 'Tumor Markers'
    
    # Parasitology
    if any(x in name_lower for x in ['malaria', 'parasite', 'trophozoite', 'skin snip', 'skin scrapping']):
        return 'Parasitology'
    
    # Histopathology
    if any(x in name_lower for x in ['histopathology', 'biopsy', 'cytology', 'pap smear', 'fine needle', 'immunostaining']):
        return 'Histopathology'
    
    # Cardiac
    if any(x in name_lower for x in ['ecg', 'troponin', 'ck-mb', 'creatine kinase', 'holter', 'myocardial']):
        return 'Cardiac'
    
    # Special Tests
    if any(x in name_lower for x in ['g6pd', 'heinz', 'guthrie', 'semen', 'pregnancy', 'arterial blood gas', 'abg', 'pulmonary function', 'eeg', 'gonioscopy', 'keratometry', 'a-scan', 'biomicroscopy', 'vitality', 'vct']):
        return 'Special Tests'
    
    # Urine Tests
    if any(x in name_lower for x in ['urine', '24hr', 'bence jones']):
        return 'Urinalysis'
    
    return 'General'

# Determine sample type based on test name
def get_sample_type(name):
    name_lower = name.lower()
    
    if any(x in name_lower for x in ['urine', '24hr urine']):
        return 'Urine'
    if any(x in name_lower for x in ['stool']):
        return 'Stool'
    if any(x in name_lower for x in ['csf', 'cerebrospinal']):
        return 'CSF'
    if any(x in name_lower for x in ['swab', 'hvs', 'high vaginal']):
        return 'Swab'
    if any(x in name_lower for x in ['sputum']):
        return 'Sputum'
    if any(x in name_lower for x in ['biopsy', 'tissue', 'aspirate', 'bone marrow']):
        return 'Tissue'
    if any(x in name_lower for x in ['skin scrapping', 'skin snip']):
        return 'Skin'
    if any(x in name_lower for x in ['semen']):
        return 'Semen'
    if any(x in name_lower for x in ['x-ray', 'xray', 'ct scan', 'mri', 'ultrasound', 'scan', 'mammogram', 'doppler', 'ecg', 'eeg', 'holter']):
        return None  # Imaging - no sample
    if any(x in name_lower for x in ['blood', 'serum', 'plasma', 'haemoglobin', 'fbc', 'glucose', 'sugar', 'urea', 'creatinine', 'electrolyte', 'cholesterol', 'lipid', 'lft', 'hormone', 'hiv', 'hepatitis', 'grouping']):
        return 'Blood'
    
    return 'Blood'  # Default to blood for most lab tests

# Estimate turnaround time based on category
def get_turnaround_time(category, name):
    name_lower = name.lower()
    
    if category == 'Imaging':
        if 'mri' in name_lower or 'ct scan' in name_lower:
            return '24 hours'
        return '2 hours'
    if category == 'Hematology':
        return '2 hours'
    if category == 'Biochemistry':
        if 'ogtt' in name_lower:
            return '4 hours'
        return '4 hours'
    if category == 'Microbiology':
        return '48-72 hours'
    if category == 'Serology':
        return '24 hours'
    if category == 'Hormones':
        return '24 hours'
    if category == 'Tumor Markers':
        return '24-48 hours'
    if category == 'Histopathology':
        return '5-7 days'
    if category == 'Cardiac':
        if 'ecg' in name_lower:
            return '30 minutes'
        return '4 hours'
    
    return '24 hours'

# Write the lab services import CSV
output_file = 'nhis-data/nhis_lab_services_for_import.csv'

with open(output_file, 'w', newline='', encoding='utf-8') as f:
    fieldnames = ['code', 'name', 'price', 'category', 'sample_type', 'turnaround_time', 'nhis_code']
    writer = csv.DictWriter(f, fieldnames=fieldnames)
    writer.writeheader()
    
    for item in investigations:
        code = item['code']
        name = item['name']
        price = item['tariff_price']
        category = categorize_test(name)
        sample_type = get_sample_type(name)
        turnaround_time = get_turnaround_time(category, name)
        
        writer.writerow({
            'code': code,
            'name': name,
            'price': '',  # Leave empty - hospital sets their own prices
            'category': category,
            'sample_type': sample_type if sample_type else '',
            'turnaround_time': turnaround_time,
            'nhis_code': code  # Same as code for auto-mapping
        })

print(f"Created {output_file} with {len(investigations)} lab services")

# Print category breakdown
categories = {}
for item in investigations:
    cat = categorize_test(item['name'])
    categories[cat] = categories.get(cat, 0) + 1

print("\nCategory breakdown:")
for cat, count in sorted(categories.items(), key=lambda x: -x[1]):
    print(f"  {cat}: {count}")
