# Implementation Plan

## Phase 1: Database and Models

- [ ] 1. Create G-DRG tariffs table and model
  - [ ] 1.1 Create migration for gdrg_tariffs table
    - Include columns: code (unique), name, mdc_category, tariff_price, age_category, is_active
    - Add indexes on code, name, mdc_category
    - _Requirements: 1.1, 1.2_
  - [ ] 1.2 Create GdrgTariff model with relationships and scopes
    - Add getDisplayNameAttribute() for "Name (Code - GHS Price)" format
    - Add scopeActive() and scopeSearch() methods
    - _Requirements: 1.5, 4.3_
  - [ ] 1.3 Create GdrgTariffFactory for testing
    - _Requirements: 1.1_
  - [ ] 1.4 Write property test for G-DRG display format
    - **Property 3: G-DRG Display Format**
    - **Validates: Requirements 4.3**

- [ ] 2. Create insurance claim diagnoses table and model
  - [ ] 2.1 Create migration for insurance_claim_diagnoses table
    - Include columns: insurance_claim_id, diagnosis_id, is_primary
    - Add foreign keys and unique constraint on claim+diagnosis
    - _Requirements: 5.1, 5.2_
  - [ ] 2.2 Create InsuranceClaimDiagnosis model with relationships
    - Add belongsTo relationships for claim and diagnosis
    - _Requirements: 5.1_
  - [ ] 2.3 Create InsuranceClaimDiagnosisFactory for testing
    - _Requirements: 5.1_

- [ ] 3. Modify existing models for G-DRG support
  - [ ] 3.1 Add requires_gdrg column to insurance_providers table
    - Create migration to add boolean column with default false
    - _Requirements: 2.1, 2.2_
  - [ ] 3.2 Update InsuranceProvider model
    - Add requires_gdrg to fillable and casts
    - _Requirements: 2.1_
  - [ ] 3.3 Add G-DRG fields to insurance_claims table
    - Create migration to add gdrg_tariff_id (FK) and gdrg_amount columns
    - _Requirements: 4.4, 7.1_
  - [ ] 3.4 Update InsuranceClaim model
    - Add gdrgTariff() relationship
    - Add claimDiagnoses() relationship
    - Add requiresGdrg() helper method
    - _Requirements: 4.1, 5.1_
  - [ ] 3.5 Write property test for provider G-DRG workflow selection
    - **Property 5: Provider G-DRG Workflow Selection**
    - **Validates: Requirements 2.2, 2.3, 10.1**

- [ ] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 2: Backend Services and Controllers

- [ ] 5. Create ClaimVettingService
  - [ ] 5.1 Implement calculateNhisTotal() method
    - Calculate: G-DRG tariff + investigations + prescriptions + procedures
    - Return breakdown and grand total
    - _Requirements: 7.1_
  - [ ] 5.2 Implement calculateStandardTotal() method
    - Apply insurance coverage rules (percentage/fixed)
    - _Requirements: 7.2, 10.2_
  - [ ] 5.3 Implement getClaimItems() method
    - Aggregate items from consultation
    - _Requirements: 6.2, 6.3, 6.4_
  - [ ] 5.4 Implement aggregateAdmissionItems() method
    - Aggregate items from initial consultation and all ward rounds
    - _Requirements: 6.6_
  - [ ] 5.5 Write property test for NHIS claim total calculation
    - **Property 8: NHIS Claim Total Calculation**
    - **Validates: Requirements 7.1, 7.3**
  - [ ] 5.6 Write property test for claim items aggregation
    - **Property 12: Claim Items Aggregation**
    - **Validates: Requirements 6.2, 6.3, 6.4, 6.6**

- [ ] 6. Create ClaimExportService
  - [ ] 6.1 Implement generateNhisXml() method
    - Format claims data according to NHIS requirements
    - _Requirements: 9.3_
  - [ ] 6.2 Implement generateExcel() method
    - Include all claim details with appropriate headers
    - _Requirements: 9.4_
  - [ ] 6.3 Write property test for export date range filtering
    - **Property 15: Export Date Range Filtering**
    - **Validates: Requirements 9.2**
  - [ ] 6.4 Write property test for Excel export completeness
    - **Property 17: Excel Export Completeness**
    - **Validates: Requirements 9.4**

- [ ] 7. Create GdrgTariffController
  - [ ] 7.1 Create controller with index, store, update, destroy methods
    - _Requirements: 1.1, 1.2, 1.4_
  - [ ] 7.2 Create StoreGdrgTariffRequest with validation rules
    - Validate unique code, required fields
    - _Requirements: 1.2_
  - [ ] 7.3 Create UpdateGdrgTariffRequest with validation rules
    - _Requirements: 1.4_
  - [ ] 7.4 Implement import() method for bulk import
    - _Requirements: 1.3_
  - [ ] 7.5 Create ImportGdrgTariffRequest with file validation
    - _Requirements: 1.3_
  - [ ] 7.6 Implement search() API endpoint for dropdown
    - Return formatted options for searchable dropdown
    - _Requirements: 1.5, 4.2_
  - [ ] 7.7 Create GdrgTariffResource for API responses
    - _Requirements: 1.1_
  - [ ] 7.8 Create GdrgTariffPolicy for authorization
    - _Requirements: 1.1_
  - [ ] 7.9 Write property test for G-DRG code uniqueness
    - **Property 1: G-DRG Code Uniqueness**
    - **Validates: Requirements 1.2**
  - [ ] 7.10 Write property test for G-DRG search filtering
    - **Property 2: G-DRG Search Filtering**
    - **Validates: Requirements 1.5, 4.2**

- [ ] 8. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 9. Enhance InsuranceClaimController for modal vetting
  - [ ] 9.1 Implement getVettingData() method
    - Return all data needed for vetting modal
    - Include patient info, attendance details, diagnoses, items
    - _Requirements: 3.2, 3.3, 3.4, 6.1_
  - [ ] 9.2 Implement vetNhis() method for NHIS claim approval
    - Validate G-DRG selection required
    - Save G-DRG tariff and amount
    - Update claim status to vetted
    - _Requirements: 4.4, 4.5, 8.1, 8.2, 8.3, 8.4_
  - [ ] 9.3 Create VetNhisClaimRequest with validation
    - Require gdrg_tariff_id for NHIS claims
    - _Requirements: 4.5, 8.2_
  - [ ] 9.4 Implement updateClaimDiagnoses() method
    - Add/remove diagnoses on claim without affecting consultation
    - _Requirements: 5.2, 5.3_
  - [ ] 9.5 Write property test for NHIS approval requires G-DRG
    - **Property 13: NHIS Approval Requires G-DRG**
    - **Validates: Requirements 4.5, 8.2**
  - [ ] 9.6 Write property test for approval state transition
    - **Property 14: Approval State Transition**
    - **Validates: Requirements 8.3, 8.4**
  - [ ] 9.7 Write property test for claim diagnosis isolation
    - **Property 10: Claim Diagnosis Isolation**
    - **Validates: Requirements 5.2, 5.3**

- [ ] 10. Create ClaimExportController
  - [ ] 10.1 Implement exportXml() method
    - Filter by date range, generate XML
    - _Requirements: 9.1, 9.2, 9.3_
  - [ ] 10.2 Implement exportExcel() method
    - Filter by date range, generate Excel
    - _Requirements: 9.1, 9.2, 9.4_
  - [ ] 10.3 Create ExportClaimsRequest with date validation
    - _Requirements: 9.1_
  - [ ] 10.4 Write feature test for export functionality
    - Test XML and Excel export with date range
    - _Requirements: 9.2, 9.3, 9.4_

- [ ] 11. Add routes for new endpoints
  - [ ] 11.1 Add G-DRG tariff management routes
    - CRUD routes under /admin/gdrg-tariffs
    - _Requirements: 1.1_
  - [ ] 11.2 Add claim vetting API routes
    - GET /claims/{id}/vetting-data
    - POST /claims/{id}/vet-nhis
    - POST /claims/{id}/diagnoses
    - _Requirements: 3.1, 4.4, 5.2_
  - [ ] 11.3 Add export routes
    - GET /claims/export/xml
    - GET /claims/export/excel
    - _Requirements: 9.1_

- [ ] 12. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 3: Frontend Components

- [ ] 13. Create G-DRG Tariff Management Page
  - [ ] 13.1 Create GdrgTariffs/Index.tsx page
    - Display tariffs table with search/filter
    - Add create/edit/delete functionality
    - Add import button
    - _Requirements: 1.1, 1.5_
  - [ ] 13.2 Create GdrgTariffForm component
    - Form for creating/editing tariffs
    - _Requirements: 1.2, 1.4_
  - [ ] 13.3 Create ImportGdrgModal component
    - File upload for bulk import
    - _Requirements: 1.3_

- [ ] 14. Create Vetting Modal Components
  - [ ] 14.1 Create VettingModal.tsx main component
    - Modal container with sections
    - Handle open/close state
    - _Requirements: 3.1, 3.5_
  - [ ] 14.2 Create PatientInfoSection component
    - Display patient demographics
    - _Requirements: 3.2_
  - [ ] 14.3 Create AttendanceDetailsSection component
    - Display attendance and service details
    - _Requirements: 3.3, 3.4_
  - [ ] 14.4 Create GdrgSelector.tsx component
    - Searchable dropdown with formatted options
    - Only show for NHIS claims
    - _Requirements: 4.1, 4.2, 4.3, 10.1_
  - [ ] 14.5 Create DiagnosesManager.tsx component
    - Display pre-populated diagnoses
    - Add/remove functionality with search
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_
  - [ ] 14.6 Create ClaimItemsTabs.tsx component
    - Tabs for Investigations, Prescriptions, Procedures
    - Display items with prices
    - _Requirements: 6.1, 6.5_
  - [ ] 14.7 Create ClaimTotalDisplay component
    - Show grand total with breakdown
    - Update on G-DRG change
    - _Requirements: 7.1, 7.4_
  - [ ] 14.8 Write property test for modal data completeness
    - **Property 6: Modal Data Completeness**
    - **Validates: Requirements 3.2, 3.3, 3.4**

- [ ] 15. Create Export Modal
  - [ ] 15.1 Create ExportModal.tsx component
    - Date range picker
    - Format selection (XML/Excel)
    - _Requirements: 9.1_
  - [ ] 15.2 Implement export download handling
    - Trigger file download on submit
    - Handle empty results message
    - _Requirements: 9.2, 9.5_

- [ ] 16. Integrate Modal into Claims Index Page
  - [ ] 16.1 Modify Claims/Index.tsx to include VettingModal
    - Add modal state management
    - Pass selected claim to modal
    - _Requirements: 3.1_
  - [ ] 16.2 Update "Click to Vet" button to open modal
    - Fetch vetting data on click
    - _Requirements: 3.1_
  - [ ] 16.3 Add Export button to claims page
    - Open ExportModal on click
    - _Requirements: 9.1_
  - [ ] 16.4 Handle modal approval callback
    - Refresh claims list after approval
    - _Requirements: 8.5_

- [ ] 17. Update Insurance Provider Form
  - [ ] 17.1 Add "Requires G-DRG" toggle to provider form
    - _Requirements: 2.1_

- [ ] 18. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Phase 4: Integration and Polish

- [ ] 19. Add tariff update isolation
  - [ ] 19.1 Ensure gdrg_amount is saved on claim approval
    - Store the tariff price at time of vetting
    - _Requirements: 1.4_
  - [ ] 19.2 Write property test for tariff update isolation
    - **Property 4: Tariff Update Isolation**
    - **Validates: Requirements 1.4**

- [ ] 20. Add diagnosis search functionality
  - [ ] 20.1 Create diagnosis search API endpoint
    - Search by name or ICD-10 code
    - _Requirements: 5.5_
  - [ ] 20.2 Write property test for diagnosis search filtering
    - **Property 11: Diagnosis Search Filtering**
    - **Validates: Requirements 5.5**

- [ ] 21. Final integration testing
  - [ ] 21.1 Write feature test for complete NHIS vetting workflow
    - Open modal, select G-DRG, manage diagnoses, approve
    - _Requirements: 3.1, 4.4, 5.2, 8.3_
  - [ ] 21.2 Write feature test for non-NHIS vetting workflow
    - Verify G-DRG not shown, standard calculation used
    - _Requirements: 10.1, 10.2_
  - [ ] 21.3 Write property test for modal close without save
    - **Property 7: Modal Close Without Save**
    - **Validates: Requirements 3.5**
  - [ ] 21.4 Write property test for non-NHIS claim total calculation
    - **Property 9: Non-NHIS Claim Total Calculation**
    - **Validates: Requirements 7.2, 10.2, 10.3**

- [ ] 22. Final Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
