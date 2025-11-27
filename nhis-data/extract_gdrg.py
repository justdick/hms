from docx import Document
import csv
import re

doc = Document('Private Primary Care Hospital (Catering Exclusive) Tariff JAN 2023-1.docx')

# Collect all G-DRG data
gdrg_data = []
current_mdc = ''

for table in doc.tables:
    for row in table.rows:
        cells = [cell.text.strip() for cell in row.cells]
        if len(cells) >= 3:
            code = cells[0]
            name = cells[1]
            tariff = cells[2]
            
            # Check if this is a header row (contains MDC category)
            if code == 'G-DRG' and 'TARIFF' in tariff:
                current_mdc = name
                continue
            
            # Skip empty or invalid rows
            if not code or not name or not tariff:
                continue
            
            # Clean up tariff value (remove commas, currency symbols)
            tariff_clean = re.sub(r'[^0-9.]', '', tariff.replace(',', ''))
            if not tariff_clean or tariff_clean == '-':
                continue
            
            # Determine age category from code suffix
            age_category = 'all'
            if code.endswith('A'):
                age_category = 'adult'
            elif code.endswith('C'):
                age_category = 'child'
            
            gdrg_data.append({
                'code': code,
                'name': name,
                'mdc_category': current_mdc,
                'tariff_price': tariff_clean,
                'age_category': age_category
            })

# Write to CSV
with open('gdrg_tariffs_import.csv', 'w', newline='', encoding='utf-8') as f:
    writer = csv.DictWriter(f, fieldnames=['code', 'name', 'mdc_category', 'tariff_price', 'age_category'])
    writer.writeheader()
    writer.writerows(gdrg_data)

print(f'Created gdrg_tariffs_import.csv with {len(gdrg_data)} G-DRG tariffs')
print('')
print('MDC Categories found:')
mdcs = set(row['mdc_category'] for row in gdrg_data)
for mdc in sorted(mdcs):
    count = len([r for r in gdrg_data if r['mdc_category'] == mdc])
    print(f'  - {mdc}: {count} tariffs')
