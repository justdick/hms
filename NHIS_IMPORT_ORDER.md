# NHIS Data Import Order

Follow this order to correctly set up NHIS tariffs and mappings.

## Step 1: Import Tariff Master Lists

These are the official NHIA price lists. Import them FIRST.

| Import | Menu Location | File |
|--------|---------------|------|
| NHIS Tariffs | Admin → NHIS Tariffs → Import | `nhis-data/nhis_tariffs_import.csv` |
| G-DRG Tariffs | Admin → G-DRG Tariffs → Import | `nhis-data/gdrg_tariffs_import.csv` |

- **NHIS Tariffs** = Medicines pricing from NHIA
- **G-DRG Tariffs** = Labs, procedures, consultations pricing from NHIA

## Step 2: Import Hospital Items (with NHIS codes)

When importing items, include the `nhis_code` column. Mappings are created automatically.

| Import | Menu Location | File |
|--------|---------------|------|
| Drugs | Pharmacy → Drugs → Import | `nhis-data/nhis_drugs_for_import.csv` |
| Lab Services | Lab → Services → Import | `nhis-data/nhis_lab_services_for_import.csv` |
| Procedures | Minor Procedures → Configuration → Import | `nhis-data/nhis_procedures_for_import.csv` |

## Step 3: Verify Mappings

Go to **Admin → NHIS Mappings** to see all linked items.

- Use "Export Unmapped" to find items missing NHIS codes
- Use "Import" to bulk-link existing items to NHIS codes if needed

## Manual Mapping Import Format

If you need to link existing items manually, use this CSV format:

```csv
item_type,item_code,nhis_code
drug,DRG001,MED-PARA-500
lab_service,LAB001,INV-FBC
procedure,PROC001,SUR-WOUND-001
```

## Quick Reference

```
NHIS Tariffs (medicines)     ←── Drugs map here
G-DRG Tariffs (everything)   ←── Labs & Procedures map here
```
