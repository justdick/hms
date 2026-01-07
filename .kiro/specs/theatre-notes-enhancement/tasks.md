# Implementation Plan: Theatre Notes Enhancement

## Overview

Enhance the Theatre Procedures tab with comprehensive operative documentation including procedure-specific templates with inline dropdown selections. Implementation covers database changes, backend API, and frontend components.

## Tasks

- [x] 1. Database schema updates
  - [x] 1.1 Update consultation_procedures migration with new fields
    - Add indication, estimated_gestational_age, parity, procedure_subtype, template_selections columns
    - Rename post_op_plan to plan
    - _Requirements: 1.1-1.8, 4.2-4.4_
  - [x] 1.2 Create procedure_templates table migration
    - Fields: minor_procedure_type_id, procedure_code, name, template_text, variables (JSON), is_active
    - _Requirements: 6.1-6.4_
  - [x] 1.3 Run migrations
    - _Requirements: 1.1-1.8, 6.1-6.4_

- [x] 2. Backend models and relationships
  - [x] 2.1 Update ConsultationProcedure model
    - Add new fields to fillable array
    - Add template_selections cast to array
    - Rename post_op_plan to plan in fillable
    - _Requirements: 1.1-1.8, 3.4_
  - [x] 2.2 Create ProcedureTemplate model
    - Define fillable, casts, relationships
    - Add getForProcedure() static method
    - _Requirements: 6.1-6.4_

- [x] 3. Backend API endpoints
  - [x] 3.1 Create procedure search endpoint
    - GET /api/procedures/search with query parameter
    - Search by name and code, exclude pricing from response
    - _Requirements: 2.1-2.3_
  - [x] 3.2 Create procedure template endpoint
    - GET /api/procedures/{id}/template
    - Return template with variables if exists
    - _Requirements: 3.1, 3.2_
  - [x] 3.3 Update ConsultationProcedureController store method
    - Add validation for new fields
    - Handle template_selections JSON
    - _Requirements: 1.1-1.8, 3.4, 4.2-4.4_

- [x] 4. Seed C-Section template
  - [x] 4.1 Create ProcedureTemplateSeeder
    - Define C-Section template text with {{variable}} placeholders
    - Define all variables: incision_type, bladder_flap, delivery_method, placenta_removal, uterine_layers, uterine_suture, fascia_suture, subcutaneous_suture, skin_suture
    - Link to Caesarean Section procedure type
    - _Requirements: 5.1-5.7_

- [x] 5. Frontend - Searchable procedure selection
  - [x] 5.1 Create AsyncProcedureSearch component
    - Debounced search input with async API call
    - Display procedure name, code, and template badge
    - No pricing displayed
    - _Requirements: 2.1-2.4_

- [x] 6. Frontend - Template renderer
  - [x] 6.1 Create TemplateRenderer component
    - Parse template text for {{variable}} placeholders
    - Render inline Select components for each variable
    - Maintain selection state and generate composed text
    - _Requirements: 3.2, 3.3_

- [x] 7. Frontend - Update TheatreProceduresTab
  - [x] 7.1 Replace procedure dropdown with AsyncProcedureSearch
    - _Requirements: 2.1-2.4_
  - [x] 7.2 Add indication field
    - _Requirements: 1.1_
  - [x] 7.3 Add ObstetricFields component (conditional)
    - Show gestational_age, parity, procedure_subtype for C-Section
    - Procedure subtype dropdown with all C/S variants
    - _Requirements: 4.1-4.4_
  - [x] 7.4 Integrate TemplateRenderer for procedure_steps
    - Fetch template when procedure selected
    - Show template with inline dropdowns if available
    - Fall back to plain textarea if no template
    - _Requirements: 3.1-3.4_
  - [x] 7.5 Rename post_op_plan to plan in form
    - _Requirements: 1.7_
  - [x] 7.6 Update form submission to include new fields
    - _Requirements: 1.1-1.8, 3.4, 4.2-4.4_
  - [x] 7.7 Update procedure list view dialog
    - Display all new fields in view modal
    - _Requirements: 1.1-1.8_

- [ ] 8. Checkpoint - Test basic functionality
  - Ensure all tests pass, ask the user if questions arise.

- [ ]* 9. Testing
  - [ ]* 9.1 Write feature test for procedure search
    - Test search returns matching results
    - Test search excludes pricing
    - **Property 1: Procedure Search Returns Matching Results**
    - **Validates: Requirements 2.1, 2.2**
  - [ ]* 9.2 Write feature test for procedure with template
    - Test template retrieval
    - Test saving with template_selections
    - **Property 3: Template Selection Persistence**
    - **Validates: Requirements 3.4**
  - [ ]* 9.3 Write feature test for C-Section specific fields
    - Test obstetric fields saved correctly
    - **Validates: Requirements 4.1-4.4**

- [ ] 10. Final checkpoint
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- The existing migration file will be modified since we're in dev mode
- C-Section template is the only template seeded initially; more can be added later
- Template system is extensible for future procedure types
