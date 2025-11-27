#!/usr/bin/env python3
"""
Convert NHIS G-DRG tariffs to Procedure Type import format.
Extracts surgical/procedural items (excluding INVESTIGATION and medical management).
"""

import csv

# Categories that are procedures (not investigations or medical management)
PROCEDURE_CATEGORIES = [
    'ADULT SURGERY',
    'DENTAL',
    'EAR NOSE AND THROAT',
    'OBSTETRICS AND GYNAECOLOGY',
    'OPTHALMOLOGY',
    'ORTHOPAEDIC',
    'PAEDIATRIC SURGERY',
    'RECONSTRUCTIVE SURGERY',
    'ZOOM',  # Minor procedures like circumcision, dressing changes
]

# Categories to exclude (medical management, not procedures)
EXCLUDE_CATEGORIES = [
    'INVESTIGATION',
    'ADULT MEDICINE',
    'PAEDIATRICS',
    'OUT PATIENT',
]

# Read the G-DRG tariffs and extract procedure items
procedures = []
seen_codes = set()

with open('nhis-data/gdrg_tariffs_import.csv', 'r', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        category = row.get('mdc_category', '')
        code = row.get('code', '')
        
        # Skip if already seen (duplicates in file)
        if code in seen_codes:
            continue
        seen_codes.add(code)
        
        # Include if in procedure categories
        if category in PROCEDURE_CATEGORIES:
            procedures.append(row)

print(f"Found {len(procedures)} procedure items")

# Determine if procedure is minor or major based on price and name
def get_procedure_type(name, price):
    name_lower = name.lower()
    price_val = float(price) if price else 0
    
    # Minor procedures (typically < 500 GHS or specific keywords)
    minor_keywords = [
        'extraction', 'filling', 'scaling', 'polishing', 'dressing', 
        'catheter', 'circumcision', 'bandaging', 'cast', 'pop',
        'incision and drainage', 'i & d', 'nail avulsion', 'removal of foreign body',
        'examination', 'biopsy', 'excision biopsy', 'detention', 'observation',
        'opd procedure', 'manual reduction', 'pessary insertion'
    ]
    
    if any(kw in name_lower for kw in minor_keywords):
        return 'minor'
    
    if price_val < 400:
        return 'minor'
    
    return 'major'

# Map G-DRG category to simplified category
def simplify_category(mdc_category):
    mapping = {
        'ADULT SURGERY': 'General Surgery',
        'DENTAL': 'Dental',
        'EAR NOSE AND THROAT': 'ENT',
        'OBSTETRICS AND GYNAECOLOGY': 'Obstetrics & Gynaecology',
        'OPTHALMOLOGY': 'Ophthalmology',
        'ORTHOPAEDIC': 'Orthopaedic',
        'PAEDIATRIC SURGERY': 'Paediatric Surgery',
        'RECONSTRUCTIVE SURGERY': 'Reconstructive Surgery',
        'ZOOM': 'Minor Procedures',
    }
    return mapping.get(mdc_category, mdc_category)

# Write the procedures import CSV
output_file = 'nhis-data/nhis_procedures_for_import.csv'

with open(output_file, 'w', newline='', encoding='utf-8') as f:
    fieldnames = ['code', 'name', 'category', 'type', 'price', 'description', 'nhis_code']
    writer = csv.DictWriter(f, fieldnames=fieldnames)
    writer.writeheader()
    
    for item in procedures:
        code = item['code']
        name = item['name']
        price = item['tariff_price']
        mdc_category = item['mdc_category']
        age_category = item.get('age_category', '')
        
        proc_type = get_procedure_type(name, price)
        category = simplify_category(mdc_category)
        
        # Add age info to description if present
        description = f"Age category: {age_category}" if age_category else ''
        
        writer.writerow({
            'code': code,
            'name': name,
            'category': category,
            'type': proc_type,
            'price': '',  # Leave empty - hospital sets their own prices
            'description': description,
            'nhis_code': code  # Same as code for auto-mapping
        })

print(f"Created {output_file} with {len(procedures)} procedures")

# Print category breakdown
categories = {}
types = {'minor': 0, 'major': 0}
for item in procedures:
    cat = simplify_category(item['mdc_category'])
    categories[cat] = categories.get(cat, 0) + 1
    
    proc_type = get_procedure_type(item['name'], item['tariff_price'])
    types[proc_type] += 1

print("\nCategory breakdown:")
for cat, count in sorted(categories.items(), key=lambda x: -x[1]):
    print(f"  {cat}: {count}")

print(f"\nType breakdown:")
print(f"  Minor: {types['minor']}")
print(f"  Major: {types['major']}")
