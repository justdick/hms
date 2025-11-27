# Implementation Tasks

## Phase 1: Drug Import

- [x] 1.1 Create DrugImport class
  - Handle CSV parsing
  - Validate required fields (drug_code, name, unit_price)
  - Upsert drugs by drug_code
  - Auto-create NHIS mapping if nhis_code provided

- [x] 1.2 Create DrugImportTemplate export
  - Excel/CSV with headers
  - 2 example rows
  - Instructions sheet

- [x] 1.3 Create DrugImportController
  - downloadTemplate() - returns template file
  - import() - processes uploaded CSV

- [x] 1.4 Add routes for drug import
  - GET /pharmacy/drugs-import/template
  - POST /pharmacy/drugs-import

- [x] 1.5 Create ImportModal component for Drugs page
  - Download template button
  - File upload
  - Import button
  - Show results (created, updated, errors)

- [x] 1.6 Add Import button to Inventory Index page (drugs redirect there)

- [x] 1.7 Test drug import with auto-mapping

## Phase 2: Lab Service Import

- [x] 2.1 Create LabServiceImport class
- [x] 2.2 Create LabServiceImportTemplate export
- [x] 2.3 Create LabServiceImportController
- [x] 2.4 Add routes for lab import
- [x] 2.5 Create ImportModal component for Lab Services page
- [x] 2.6 Add Import button to Lab Configuration Index page
- [ ] 2.7 Test lab import with auto-mapping

## Phase 3: Export Unmapped

- [x] 3.1 Create UnmappedItemsExport class
  - Export by item_type (drug, lab_service, procedure)
  - Columns: item_type, item_code, item_name, nhis_code (empty)

- [x] 3.2 Add exportUnmapped() method to NhisMappingController

- [x] 3.3 Add route for export
  - GET /admin/nhis-mappings/unmapped/export

- [x] 3.4 Add Export button to Unmapped page

- [ ] 3.5 Test export and re-import workflow

## Phase 4: Testing

- [ ] 4.1 Write feature test for drug import
- [ ] 4.2 Write feature test for lab import
- [ ] 4.3 Write feature test for unmapped export
- [ ] 4.4 Test full workflow: import drugs → auto-map → verify NHIS coverage works

## Notes

- Use existing NhisItemMapping model for auto-mapping
- Use Maatwebsite/Excel for imports/exports (already installed)
- Follow existing patterns from NhisTariffController for import handling
