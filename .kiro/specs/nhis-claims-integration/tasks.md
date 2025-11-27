# Implementation Plan

## Phase 1: Database Foundation

- [x] 1. Create NHIS Tariff Master table and model





  - [x] 1.1 Create migration for nhis_tariffs table


    - Include columns: nhis_code (unique), name, category, price, unit, is_active
    - Add indexes on nhis_code, category, name
    - _Requirements: 1.1, 1.2_


  - [x] 1.2 Create NhisTariff model with relationships and scopes
    - Add scopeActive() and scopeSearch() methods
    - Add scopeByCategory() method
    - _Requirements: 1.4, 1.5_
  - [x] 1.3 Create NhisTariffFactory for testing
    - _Requirements: 1.1_
  - [x] 1.4 Write property test for NHIS tariff search filtering
    - **Property 2: NHIS Tariff Search Filtering**
    - **Validates: Requirements 1.4**

- [x] 2. Create NHIS Item Mapping table and model





  - [x] 2.1 Create migration for nhis_item_mappings table


    - Include columns: item_type, item_id, item_code, nhis_tariff_id
    - Add foreign key to nhis_tariffs
    - Add unique constraint on item_type + item_id
    - _Requirements: 2.1, 2.2_
  - [x] 2.2 Create NhisItemMapping model with relationships


    - Add belongsTo relationship for nhis_tariff
    - Add polymorphic relationship for mapped item
    - _Requirements: 2.2, 2.5_
  - [x] 2.3 Create NhisItemMappingFactory for testing


    - _Requirements: 2.1_
  - [x] 2.4 Write property test for mapping persistence


    - **Property 5: NHIS Mapping Persistence**
    - **Validates: Requirements 2.2**


- [x] 3. Create G-DRG Tariff table and model





  - [x] 3.1 Create migration for gdrg_tariffs table


    - Include columns: code (unique), name, mdc_category, tariff_price, age_category, is_active
    - Add indexes on code, mdc_category
    - _Requirements: 3.1, 3.2_
  - [x] 3.2 Create GdrgTariff model with relationships and scopes


    - Add getDisplayNameAttribute() for "Name (Code - GHS Price)" format
    - Add scopeActive() and scopeSearch() methods
    - _Requirements: 3.5, 9.3_
  - [x] 3.3 Create GdrgTariffFactory for testing


    - _Requirements: 3.1_
  - [x] 3.4 Write property test for G-DRG code uniqueness


    - **Property 7: G-DRG Code Uniqueness**
    - **Validates: Requirements 3.2**
  - [x] 3.5 Write property test for G-DRG display format


    - **Property 17: G-DRG Display Format**
    - **Validates: Requirements 9.3**

- [x] 4. Create Claim Batch tables and models





  - [x] 4.1 Create migration for claim_batches table


    - Include columns: batch_number, name, submission_period, status, totals, timestamps
    - Add foreign key for created_by
    - _Requirements: 14.1, 14.3, 14.4_
  - [x] 4.2 Create migration for claim_batch_items table


    - Include columns: claim_batch_id, insurance_claim_id, amounts, status, rejection_reason
    - Add foreign keys and unique constraint
    - _Requirements: 14.2, 17.1, 17.2_


  - [x] 4.3 Create ClaimBatch model with relationships

    - Add hasMany for batch items
    - Add belongsTo for creator




    - Add status helper methods
    - _Requirements: 14.3, 16.1_

  - [x] 4.4 Create ClaimBatchItem model with relationships

    - _Requirements: 14.2_
  - [x] 4.5 Create ClaimBatchFactory and ClaimBatchItemFactory

    - _Requirements: 14.1_

- [x] 5. Create Insurance Claim Diagnoses table and model





  - [x] 5.1 Create migration for insurance_claim_diagnoses table


    - Include columns: insurance_claim_id, diagnosis_id, is_primary
    - Add foreign keys and unique constraint
    - _Requirements: 10.1, 10.2_
  - [x] 5.2 Create InsuranceClaimDiagnosis model with relationships


    - _Requirements: 10.1_
  - [x] 5.3 Create InsuranceClaimDiagnosisFactory


    - _Requirements: 10.1_


- [x] 6. Modify existing models for NHIS support





  - [x] 6.1 Add is_nhis column to insurance_providers table


    - Create migration to add boolean column with default false
    - _Requirements: 4.1, 4.2_
  - [x] 6.2 Update InsuranceProvider model


    - Add is_nhis to fillable and casts
    - Add isNhis() helper method
    - _Requirements: 4.1_
  - [x] 6.3 Add G-DRG and vetting fields to insurance_claims table


    - Create migration to add gdrg_tariff_id, gdrg_amount, vetted_by, vetted_at
    - _Requirements: 9.4, 13.3, 13.4_
  - [x] 6.4 Update InsuranceClaim model


    - Add gdrgTariff() relationship
    - Add claimDiagnoses() relationship
    - Add vettedBy() relationship
    - Add requiresGdrg() and isNhisClaim() helper methods
    - _Requirements: 9.1, 10.1, 13.4_
  - [x] 6.5 Add NHIS fields to insurance_claim_items table


    - Create migration to add nhis_tariff_id, nhis_code, nhis_price
    - _Requirements: 11.2, 13.5_
  - [x] 6.6 Update InsuranceClaimItem model


    - Add nhisTariff() relationship
    - _Requirements: 11.2_
  - [x] 6.7 ~~Add NHIS fields to patients table~~ (REMOVED - NHIS membership tracked via PatientInsurance)
    - Note: NHIS member ID and expiry date are stored in PatientInsurance.membership_id and PatientInsurance.coverage_end_date
    - Migration deleted, Patient model cleaned up
    - _Requirements: 7.1_
  - [x] 6.8 Update Patient model
    - Added activeNhisInsurance() relationship to get NHIS insurance via PatientInsurance
    - Updated hasValidNhis() to check PatientInsurance records
    - Removed nhis_member_id and nhis_expiry_date fields (use PatientInsurance instead)
    - _Requirements: 7.1, 7.3_

- [x] 7. Checkpoint - Ensure all migrations and models work





  - Ensure all tests pass, ask the user if questions arise.

## Phase 2: Core Services

- [x] 8. Create NhisTariffService

  - [x] 8.1 Implement getTariffForItem() method
    - Look up NHIS tariff via item mapping
    - _Requirements: 5.1_

  - [x] 8.2 Implement getTariffPrice() method
    - Return price from Master for mapped item
    - _Requirements: 5.2_

  - [x] 8.3 Implement isItemMapped() method
    - Check if item has NHIS mapping
    - _Requirements: 2.6, 5.4_

  - [x] 8.4 Implement importTariffs() method
    - Handle upsert logic for existing codes
    - _Requirements: 1.2, 1.3_

  - [x] 8.5 Write property test for tariff import upsert
    - **Property 1: NHIS Tariff Import Upsert**
    - **Validates: Requirements 1.2, 1.3**

  - [x] 8.6 Write property test for unmapped items flagged
    - **Property 4: Unmapped Items Flagged**
    - **Validates: Requirements 2.6, 5.4, 12.4**


- [x] 9. Modify InsuranceCoverageService for NHIS





  - [x] 9.1 Add isNhisPlan() helper method


    - Check if plan belongs to NHIS provider
    - _Requirements: 4.2_

  - [x] 9.2 Implement calculateNhisCoverage() method

    - Look up price from NHIS Tariff Master via mapping
    - Return insurance_pays = NHIS tariff price
    - Return patient_pays = copay from coverage rule only
    - _Requirements: 5.1, 5.2, 5.3_


  - [x] 9.3 Modify calculateCoverage() to detect NHIS
    - If NHIS plan, delegate to calculateNhisCoverage()
    - Otherwise use existing logic
    - _Requirements: 4.2, 4.4_
  - [x] 9.4 Write property test for NHIS coverage uses Master price


    - **Property 9: NHIS Coverage Uses Master Price**
    - **Validates: Requirements 4.2, 5.1, 5.2**
  - [x] 9.5 Write property test for NHIS patient pays only copay


    - **Property 10: NHIS Patient Pays Only Copay**
    - **Validates: Requirements 5.3**
  - [x] 9.6 Write property test for price propagation


    - **Property 3: NHIS Price Propagation**
    - **Validates: Requirements 1.6, 5.5**

- [x] 10. Create ClaimVettingService



  - [x] 10.1 Implement getVettingData() method


    - Return all data needed for vetting modal
    - Include patient info, attendance details, diagnoses, items
    - _Requirements: 8.2, 8.3, 8.4, 11.1_
  - [x] 10.2 Implement calculateClaimTotal() method

    - Calculate: G-DRG tariff + Investigations + Prescriptions + Procedures
    - Exclude unmapped items from total
    - _Requirements: 12.1, 12.4_

  - [x] 10.3 Implement aggregateAdmissionItems() method
    - Aggregate items from initial consultation and all ward rounds

    - _Requirements: 11.4_
  - [x] 10.4 Implement vetClaim() method
    - Validate G-DRG required for NHIS
    - Store NHIS prices on claim items
    - Update claim status to vetted
    - Record vetted_by and vetted_at

    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5_
  - [x] 10.5 Write property test for claim total calculation

    - **Property 18: NHIS Claim Total Calculation**
    - **Validates: Requirements 12.1, 9.4**
  - [x] 10.6 Write property test for G-DRG required for approval


    - **Property 19: G-DRG Required for NHIS Approval**
    - **Validates: Requirements 9.5, 13.2**
  - [x] 10.7 Write property test for claim items aggregation


    - **Property 21: Claim Items Aggregation for Admissions**
    - **Validates: Requirements 11.4**
  - [x] 10.8 Write property test for approval state transition



    - **Property 22: Approval State Transition**
    - **Validates: Requirements 13.3, 13.4, 13.5**


- [x] 11. Create ClaimBatchService

  - [x] 11.1 Implement createBatch() method
    - Generate batch number, set initial status to draft
    - _Requirements: 14.1, 14.4_

  - [x] 11.2 Implement addClaimsToBatch() method
    - Validate only vetted claims can be added
    - Validate batch is not finalized
    - Update batch totals
    - _Requirements: 14.2_

  - [x] 11.3 Implement removeClaimFromBatch() method
    - Validate batch is not finalized
    - Update batch totals
    - _Requirements: 14.2_

  - [x] 11.4 Implement finalizeBatch() method
    - Set status to finalized, prevent further modifications
    - _Requirements: 14.5_

  - [x] 11.5 Implement markSubmitted() method
    - Update status and record submission date
    - _Requirements: 16.1_

  - [x] 11.6 Implement recordResponse() method
    - Record approved/rejected amounts per claim
    - Update batch totals
    - _Requirements: 17.1, 17.2, 17.3_

  - [x] 11.7 Write property test for batch only accepts vetted claims
    - **Property 23: Batch Only Accepts Vetted Claims**
    - **Validates: Requirements 14.2**

  - [x] 11.8 Write property test for finalized batch immutability
    - **Property 24: Finalized Batch Immutability**
    - **Validates: Requirements 14.5**

  - [x] 11.9 Write property test for batch status history

    - **Property 26: Batch Status History**
    - **Validates: Requirements 16.3**

- [x] 12. Create NhisXmlExportService





  - [x] 12.1 Implement generateXml() method


    - Create NHIA-compliant XML structure
    - Include facility code, batch details
    - _Requirements: 15.1, 15.2_

  - [x] 12.2 Implement generateClaimElement() method

    - Include patient NHIS ID, G-DRG code, diagnoses

    - _Requirements: 15.3_

  - [x] 12.3 Implement generateItemElement() method
    - Include item NHIS codes and prices
    - _Requirements: 15.3_
  - [x] 12.4 Write property test for XML export round trip


    - **Property 25: XML Export Round Trip**
    - **Validates: Requirements 15.1, 15.2, 15.3**

- [x] 13. Checkpoint - Ensure all services work





  - Ensure all tests pass, ask the user if questions arise.

## Phase 3: Controllers and Routes

- [x] 14. Create NhisTariffController





  - [x] 14.1 Create controller with index, store, update, destroy methods


    - _Requirements: 1.1, 1.2_

  - [x] 14.2 Create StoreNhisTariffRequest with validation rules

    - Validate unique nhis_code, required fields
    - _Requirements: 1.2_

  - [x] 14.3 Create UpdateNhisTariffRequest with validation rules

    - _Requirements: 1.2_

  - [x] 14.4 Implement import() method for bulk import

    - _Requirements: 1.2, 1.3_

  - [x] 14.5 Create ImportNhisTariffRequest with file validation

    - _Requirements: 1.2_

  - [x] 14.6 Implement search() endpoint for dropdown (JSON response)

    - _Requirements: 1.4_

  - [x] 14.7 Create NhisTariffResource for responses

    - _Requirements: 1.1_

  - [x] 14.8 Create NhisTariffPolicy for authorization

    - _Requirements: 1.1_
  - [x] 14.9 Write feature test for NHIS tariff CRUD


    - _Requirements: 1.1, 1.2, 1.4_


- [x] 15. Create NhisMappingController

  - [x] 15.1 Create controller with index, store, destroy methods
    - _Requirements: 2.1, 2.2_
  - [x] 15.2 Create StoreNhisMappingRequest with validation rules
    - Validate item not already mapped
    - _Requirements: 2.2_



  - [x] 15.3 Implement import() method for bulk mapping
    - _Requirements: 2.4_

  - [x] 15.4 Create ImportNhisMappingRequest with file validation
    - _Requirements: 2.4_
  - [x] 15.5 Implement unmapped() method to list unmapped items
    - _Requirements: 2.3_
  - [x] 15.6 Create NhisMappingResource for responses
    - _Requirements: 2.1, 2.5_
  - [x] 15.7 Create NhisMappingPolicy for authorization
    - _Requirements: 2.1_
  - [x] 15.8 Write property test for unmapped items filter
    - **Property 6: Unmapped Items Filter**
    - **Validates: Requirements 2.3**
  - [x] 15.9 Write feature test for NHIS mapping CRUD
    - _Requirements: 2.1, 2.2, 2.3_

- [x] 16. Create GdrgTariffController

  - [x] 16.1 Create controller with index, store, update, destroy methods
    - _Requirements: 3.1, 3.2_
  - [x] 16.2 Create StoreGdrgTariffRequest with validation rules
    - Validate unique code, required fields
    - _Requirements: 3.2_
  - [x] 16.3 Create UpdateGdrgTariffRequest with validation rules
    - _Requirements: 3.4_
  - [x] 16.4 Implement import() method for bulk import
    - _Requirements: 3.3_
  - [x] 16.5 Create ImportGdrgTariffRequest with file validation
    - _Requirements: 3.3_
  - [x] 16.6 Implement search() endpoint for dropdown (JSON response)
    - _Requirements: 3.5, 9.2_
  - [x] 16.7 Create GdrgTariffResource for responses
    - _Requirements: 3.1_
  - [x] 16.8 Create GdrgTariffPolicy for authorization
    - _Requirements: 3.1_
  - [x] 16.9 Write property test for G-DRG search filtering
    - **Property 16: G-DRG Search Filtering**
    - **Validates: Requirements 9.2, 3.5**
  - [x] 16.10 Write property test for G-DRG price isolation
    - **Property 8: G-DRG Price Isolation**
    - **Validates: Requirements 3.4**

  - [x] 16.11 Write feature test for G-DRG tariff CRUD


    - _Requirements: 3.1, 3.2, 3.4_

- [x] 17. Enhance InsuranceClaimController for vetting





  - [x] 17.1 Implement getVettingData() method


    - Return all data needed for vetting modal
    - _Requirements: 8.1, 8.2, 8.3, 8.4_
  - [x] 17.2 Implement vet() method for claim approval


    - Validate G-DRG required for NHIS
    - Call ClaimVettingService.vetClaim()
    - _Requirements: 13.1, 13.2, 13.3_
  - [x] 17.3 Create VetClaimRequest with validation


    - Require gdrg_tariff_id for NHIS claims
    - _Requirements: 9.5, 13.2_
  - [x] 17.4 Implement updateDiagnoses() method


    - Add/remove diagnoses on claim without affecting consultation
    - _Requirements: 10.2, 10.3_
  - [x] 17.5 Write property test for claim diagnosis isolation


    - **Property 20: Claim Diagnosis Isolation**
    - **Validates: Requirements 10.2, 10.3**
  - [x] 17.6 Write property test for modal close without save


    - **Property 15: Modal Close Without Save**
    - **Validates: Requirements 8.5**
  - [x] 17.7 Write feature test for claim vetting workflow


    - _Requirements: 8.1, 9.4, 13.3_


- [x] 18. Create ClaimBatchController
  - [x] 18.1 Create controller with index, store, show methods
    - _Requirements: 14.1, 14.3_
  - [x] 18.2 Create StoreClaimBatchRequest with validation rules
    - Require batch name and submission period
    - _Requirements: 14.1_
  - [x] 18.3 Implement addClaims() method
    - _Requirements: 14.2_
  - [x] 18.4 Create AddClaimsToBatchRequest with validation
    - _Requirements: 14.2_
  - [x] 18.5 Implement removeClaim() method
    - _Requirements: 14.2_
  - [x] 18.6 Implement finalize() method
    - _Requirements: 14.5_
  - [x] 18.7 Implement markSubmitted() method
    - _Requirements: 16.1_
  - [x] 18.8 Create MarkBatchSubmittedRequest with validation
    - _Requirements: 16.1_
  - [x] 18.9 Implement recordResponse() method
    - _Requirements: 17.1, 17.2, 17.3_
  - [x] 18.10 Create RecordBatchResponseRequest with validation
    - _Requirements: 17.1, 17.2_
  - [x] 18.11 Create ClaimBatchResource for responses
    - _Requirements: 14.3_
  - [x] 18.12 Create ClaimBatchPolicy for authorization
    - _Requirements: 14.1_
  - [x] 18.13 Write feature test for batch management

    - _Requirements: 14.1, 14.2, 14.5, 16.1_

- [x] 19. Create ClaimExportController

  - [x] 19.1 Implement exportXml() method
    - Generate XML using NhisXmlExportService
    - Record export timestamp
    - _Requirements: 15.1, 15.4, 15.5_
  - [x] 19.2 Write feature test for XML export
    - _Requirements: 15.1, 15.2, 15.3_

- [x] 20. Add routes for all new endpoints

  - [x] 20.1 Add NHIS tariff management routes
    - CRUD routes under /admin/nhis-tariffs
    - Search route returning JSON
    - _Requirements: 1.1_
  - [x] 20.2 Add NHIS mapping routes
    - CRUD routes under /admin/nhis-mappings
    - Unmapped items route
    - _Requirements: 2.1_
  - [x] 20.3 Add G-DRG tariff management routes
    - CRUD routes under /admin/gdrg-tariffs
    - Search route returning JSON
    - _Requirements: 3.1_
  - [x] 20.4 Add claim vetting routes
    - GET /claims/{id}/vetting-data
    - POST /claims/{id}/vet
    - POST /claims/{id}/diagnoses
    - _Requirements: 8.1, 13.1, 10.2_
  - [x] 20.5 Add batch management routes
    - CRUD routes under /admin/insurance/batches
    - Finalize, submit, response routes
    - Export route
    - _Requirements: 14.1, 15.1, 16.1_

- [x] 21. Checkpoint - Ensure all controllers work





  - Ensure all tests pass, ask the user if questions arise.


## Phase 4: NHIS Coverage CSV and Patient Updates

- [x] 22. Implement NHIS Coverage CSV Export/Import





  - [x] 22.1 Create NhisCoverageTemplate export class


    - Include item_code, item_name, hospital_price, nhis_tariff_price, copay_amount
    - Pre-fill nhis_tariff_price from Master for mapped items
    - _Requirements: 6.1, 6.2_
  - [x] 22.2 Create NhisCoverageImport class


    - Only save copay_amount to coverage rule
    - Ignore tariff values in CSV
    - _Requirements: 6.3, 6.4_
  - [x] 22.3 Add export/import endpoints to coverage controller


    - _Requirements: 6.1, 6.3_
  - [x] 22.4 Write property test for CSV export contains Master prices


    - **Property 11: NHIS CSV Export Contains Master Prices**
    - **Validates: Requirements 6.1, 6.2**
  - [x] 22.5 Write property test for CSV import saves only copay


    - **Property 12: NHIS CSV Import Saves Only Copay**
    - **Validates: Requirements 6.3, 6.4**
  - [x] 22.6 Write feature test for NHIS coverage CSV workflow


    - _Requirements: 6.1, 6.2, 6.3, 6.4_

- [x] 23. Update check-in flow for NHIS support
  - Note: NHIS is handled through the existing PatientInsurance system. NHIS is just another insurance provider marked with `is_nhis = true`. No separate NHIS fields needed on Patient model.

  - [x] 23.1 Update check-in flow to display insurance info with NHIS detection
    - Show insurance membership ID and expiry status
    - Display warning if coverage expired
    - Detect if provider is NHIS via `is_nhis` flag
    - _Requirements: 7.2, 7.3_
  - [x] 23.2 Update check-in payment type selection
    - Allow insurance selection only if patient has valid (non-expired) coverage
    - Disable insurance option if coverage expired
    - _Requirements: 7.4_
  - [x] 23.3 Flag NHIS visits for claim generation
    - Insurance claims are created when patient checks in with insurance
    - `isNhisClaim()` method detects NHIS claims via provider's `is_nhis` flag
    - _Requirements: 7.5_

- [x] 24. Update Insurance Provider form



  - [x] 24.1 Add "Is NHIS Provider" toggle to provider form


    - _Requirements: 4.1_

  - [x] 24.2 Write feature test for NHIS provider configuration

    - _Requirements: 4.1, 4.2_

- [x] 25. Checkpoint - Ensure CSV and patient updates work





  - Ensure all tests pass, ask the user if questions arise.

## Phase 5: Frontend Components

- [x] 26. Create NHIS Tariff Management Page





  - [x] 26.1 Create NhisTariffs/Index.tsx page


    - Display tariffs table with search/filter
    - Add create/edit/delete functionality
    - Add import button
    - _Requirements: 1.1, 1.4, 1.5_

  - [x] 26.2 Create NhisTariffForm component

    - Form for creating/editing tariffs
    - _Requirements: 1.2_

  - [x] 26.3 Create ImportNhisTariffModal component

    - File upload for bulk import
    - _Requirements: 1.2, 1.3_

- [x] 27. Create NHIS Mapping Page

  - [x] 27.1 Create NhisMappings/Index.tsx page
    - Display mappings with item and NHIS tariff details
    - Filter for unmapped items
    - _Requirements: 2.1, 2.3, 2.5_

  - [x] 27.2 Create MappingForm component
    - Searchable NHIS tariff dropdown
    - _Requirements: 2.2_

  - [x] 27.3 Create ImportMappingModal component

    - File upload for bulk mapping
    - _Requirements: 2.4_





- [x] 28. Create G-DRG Tariff Management Page
  - [x] 28.1 Create GdrgTariffs/Index.tsx page
    - Display tariffs table with search/filter
    - Add create/edit/delete functionality
    - Add import button
    - _Requirements: 3.1, 3.5_
  - [x] 28.2 Create GdrgTariffForm component
    - Form for creating/editing tariffs
    - _Requirements: 3.2, 3.4_
  - [x] 28.3 Create ImportGdrgModal component

    - File upload for bulk import
    - _Requirements: 3.3_

- [x] 29. Create Vetting Modal Components





  - [x] 29.1 Create VettingModal.tsx main component


    - Modal container with sections
    - Handle open/close state
    - _Requirements: 8.1, 8.5_

  - [x] 29.2 Create PatientInfoSection component

    - Display patient demographics and NHIS member ID
    - _Requirements: 8.2_

  - [x] 29.3 Create AttendanceDetailsSection component

    - Display attendance and service details
    - _Requirements: 8.3, 8.4_
  - [x] 29.4 Create GdrgSelector.tsx component


    - Searchable dropdown with formatted options
    - Only show for NHIS claims
    - _Requirements: 9.1, 9.2, 9.3_

  - [x] 29.5 Create DiagnosesManager.tsx component

    - Display pre-populated diagnoses
    - Add/remove functionality with search
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

  - [x] 29.6 Create ClaimItemsTabs.tsx component

    - Tabs for Investigations, Prescriptions, Procedures
    - Display items with NHIS codes and prices
    - Show "Not Covered" for unmapped items
    - _Requirements: 11.1, 11.2, 11.3, 11.5_

  - [x] 29.7 Create ClaimTotalDisplay component

    - Show grand total with breakdown
    - Update on G-DRG change
    - _Requirements: 12.1, 12.2, 12.3_

- [x] 30. Create Batch Management Pages





  - [x] 30.1 Create Batches/Index.tsx page


    - Display batches table with status filter
    - Add create batch button
    - _Requirements: 14.1, 14.3, 16.4_
  - [x] 30.2 Create Batches/Show.tsx page


    - Display batch details and claims list
    - Add/remove claims functionality
    - Finalize, submit, export buttons
    - _Requirements: 14.2, 14.3, 14.5, 15.4, 16.1_
  - [x] 30.3 Create CreateBatchModal component


    - Form for batch name and submission period
    - _Requirements: 14.1_
  - [x] 30.4 Create AddClaimsModal component


    - Select vetted claims to add to batch
    - _Requirements: 14.2_
  - [x] 30.5 Create RecordResponseModal component


    - Form for recording NHIA response
    - _Requirements: 17.1, 17.2, 17.3_

- [x] 31. Integrate Modal into Claims Index Page






  - [x] 31.1 Modify Claims/Index.tsx to include VettingModal

    - Add modal state management
    - Pass selected claim to modal
    - _Requirements: 8.1_

  - [x] 31.2 Update "Vet Claim" button to open modal

    - Fetch vetting data on click
    - _Requirements: 8.1_

  - [x] 31.3 Handle modal approval callback

    - Refresh claims list after approval
    - _Requirements: 13.3_

- [x] 32. Checkpoint - Ensure all frontend components work





  - Ensure all tests pass, ask the user if questions arise.


## Phase 6: Reports and Resubmission

- [x] 33. Implement NHIS Claims Reports







  - [x] 33.1 Create claims summary report endpoint


    - Show totals by period: claimed, approved, rejected, paid
    - _Requirements: 18.1_

  - [x] 33.2 Create outstanding claims report endpoint

    - Show unpaid approved claims with aging
    - _Requirements: 18.2_

  - [x] 33.3 Create rejection analysis report endpoint

    - Show rejected claims grouped by reason
    - _Requirements: 18.3_

  - [x] 33.4 Create tariff coverage report endpoint

    - Show percentage of items mapped to NHIS
    - _Requirements: 18.4_

  - [x] 33.5 Create ClaimsReportExport class for Excel export

    - _Requirements: 18.5_

  - [x] 33.6 Write property test for claims summary accuracy

    - **Property 28: Claims Summary Report Accuracy**
    - **Validates: Requirements 18.1**

  - [x] 33.7 Write property test for outstanding report accuracy


    - **Property 29: Outstanding Report Accuracy**

    - **Validates: Requirements 18.2**

  - [x] 33.8 Write property test for tariff coverage accuracy


    - **Property 30: Tariff Coverage Report Accuracy**

    - **Validates: Requirements 18.4**
  - [x] 33.9 Write feature test for reports


    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5_

- [x] 34. Create Reports Frontend Pages





  - [x] 34.1 Create Reports/Summary.tsx page


    - Display claims summary with date range filter
    - _Requirements: 18.1_

  - [x] 34.2 Create Reports/Outstanding.tsx page

    - Display outstanding claims with aging
    - _Requirements: 18.2_

  - [x] 34.3 Create Reports/Rejections.tsx page

    - Display rejections grouped by reason
    - _Requirements: 18.3_
  - [x] 34.4 Create Reports/Coverage.tsx page


    - Display tariff coverage statistics
    - _Requirements: 18.4_

  - [x] 34.5 Add export buttons to all report pages

    - _Requirements: 18.5_




- [x] 35. Implement Rejected Claim Resubmission


  - [x] 35.1 Add ability to edit rejected claims



    - Allow correction of claim data
    - _Requirements: 17.5_

  - [x] 35.2 Add ability to add rejected claim to new batch

    - Reset claim status for resubmission
    - _Requirements: 17.5_

  - [x] 35.3 Write property test for rejected claim resubmission

    - **Property 27: Rejected Claim Resubmission**
    - **Validates: Requirements 17.5**
  - [x] 35.4 Write feature test for resubmission workflow


    - _Requirements: 17.5_

- [x] 36. Checkpoint - Ensure reports and resubmission work





  - Ensure all tests pass, ask the user if questions arise.

## Phase 7: Integration and Polish

- [x] 37. Add navigation and permissions


  - [x] 37.1 Add NHIS menu items to admin navigation


    - NHIS Tariffs, NHIS Mappings, G-DRG Tariffs, Batches, Reports
    - _Requirements: 1.1, 2.1, 3.1, 14.1, 18.1_
  - [x] 37.2 Create permissions for NHIS features


    - nhis-tariffs.manage, nhis-mappings.manage, gdrg-tariffs.manage
    - claim-batches.manage, nhis-reports.view
    - _Requirements: 1.1, 2.1, 3.1, 14.1, 18.1_
  - [x] 37.3 Assign permissions to appropriate roles


    - _Requirements: 1.1_










- [x] 38. Final integration testing



  - [ ] 38.1 Write end-to-end test for complete NHIS workflow
    - Import tariffs → Map items → Check-in patient → Create charges → Vet claim → Batch → Export
    - _Requirements: All_
  - [ ] 38.2 Write end-to-end test for coverage calculation
    - Verify NHIS patient gets Master price + copay only
    - _Requirements: 5.1, 5.2, 5.3_
  - [ ] 38.3 Write end-to-end test for batch submission workflow
    - Create batch → Add claims → Finalize → Export → Submit → Record response
    - _Requirements: 14.1, 14.2, 14.5, 15.1, 16.1, 17.1_

- [ ] 39. Final Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
