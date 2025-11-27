# Drug & Lab Import Feature

## Overview
Enable bulk import of drugs and lab services via CSV, with optional auto-mapping to NHIS tariffs.

## Requirements

### 1. Drug Import
- Import drugs from CSV file
- Required columns: drug_code, name, hospital_price (unit_price)
- Optional columns: generic_name, form, strength, category, nhis_code
- If nhis_code provided and exists in NHIS Tariff Master, auto-create NHIS mapping
- Upsert behavior: update existing drugs by drug_code, create new ones
- Validation: drug_code unique, price numeric

### 2. Lab Service Import
- Import lab services from CSV file
- Required columns: code, name, price
- Optional columns: category, sample_type, turnaround_time, nhis_code
- If nhis_code provided and exists in NHIS Tariff Master, auto-create NHIS mapping
- Upsert behavior: update existing by code, create new ones

### 3. Download Template
- Provide downloadable CSV template with headers and example rows
- Template available in import modal
- Include instructions for filling out

### 4. Export Unmapped Items
- Export unmapped drugs/labs to CSV
- Columns: item_type, item_code, item_name, nhis_code (empty)
- User fills nhis_code in Excel, imports via existing mapping import

## UI Location
- Drugs: Admin → Pharmacy → Drugs → Import button
- Labs: Admin → Laboratory → Services → Import button
- Unmapped Export: Admin → NHIS Mappings → Unmapped → Export button

## Files to Create/Modify

### New Files
- app/Http/Controllers/Admin/DrugImportController.php
- app/Http/Controllers/Admin/LabServiceImportController.php
- app/Imports/DrugImport.php
- app/Imports/LabServiceImport.php
- app/Exports/DrugImportTemplate.php
- app/Exports/LabServiceImportTemplate.php
- app/Exports/UnmappedItemsExport.php
- resources/js/pages/Admin/Pharmacy/Drugs/ImportModal.tsx
- resources/js/pages/Admin/Laboratory/Services/ImportModal.tsx

### Modified Files
- app/Http/Controllers/Admin/NhisMappingController.php (add export)
- resources/js/pages/Admin/Pharmacy/Drugs/Index.tsx (add import button)
- resources/js/pages/Admin/Laboratory/Services/Index.tsx (add import button)
- resources/js/pages/Admin/NhisMappings/Unmapped.tsx (add export button)
- routes/web.php or routes/admin.php (add routes)

## CSV Formats

### Drug Import Template
```csv
drug_code,name,generic_name,form,strength,unit_price,category,nhis_code
DRG001,Amoxicillin 250mg Caps,Amoxicillin,Capsule,250mg,2.50,Antibiotic,AMOXICCA1
DRG002,Paracetamol 500mg Tab,Paracetamol,Tablet,500mg,1.50,Analgesic,PARACETA1
```

### Lab Service Import Template
```csv
code,name,price,category,sample_type,turnaround_time,nhis_code
LAB001,Full Blood Count,50.00,Hematology,Blood,2 hours,INVE51D
LAB002,Fasting Blood Sugar,25.00,Chemistry,Blood,1 hour,INVE46D
```

### Unmapped Export Format
```csv
item_type,item_code,item_name,nhis_code
drug,DRG001,Amoxicillin 250mg Caps,
drug,DRG002,Paracetamol 500mg Tab,
lab_service,LAB001,Full Blood Count,
```
