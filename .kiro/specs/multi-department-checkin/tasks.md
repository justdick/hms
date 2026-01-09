# Implementation Plan: Multi-Department Same-Day Check-in

## Overview

This implementation adds support for same-day multi-department check-ins, improves error messaging, and enhances the claims vetting interface with CCC grouping and date filtering.

## Tasks

- [x] 1. Database migration for admission tracking
  - [x] 1.1 Create migration to add `created_during_admission` column to `patient_checkins` table
    - Add boolean column with default false
    - _Requirements: 2.4_

- [x] 2. Update CheckinController validation logic
  - [x] 2.1 Modify store() method to allow different-department same-day check-ins
    - Remove or modify the existing incomplete check-in block
    - Add same-department same-day validation
    - Return specific error message with department name
    - _Requirements: 1.1, 1.2, 1.3, 3.1_
  - [x] 2.2 Add admission warning flow
    - Check for active admission
    - Return admission_warning error with admission_details in session
    - Handle confirm_admission_override parameter
    - Set created_during_admission flag when confirmed
    - _Requirements: 2.1, 2.2, 2.3, 2.4_
  - [x] 2.3 Improve all error messages to be specific
    - Ensure no generic "failed to check in" messages
    - Each validation failure returns actionable message
    - _Requirements: 3.2, 3.3, 3.4, 3.5_
  - [x] 2.4 Write property test for same-department blocking
    - **Property 1: Same-Department Same-Day Block**
    - **Validates: Requirements 1.2, 1.3**
  - [x] 2.5 Write property test for different-department allowing
    - **Property 2: Different-Department Same-Day Allow**
    - **Validates: Requirements 1.1, 1.4**
  - [x] 2.6 Write property test for admission warning flow
    - **Property 3: Admission Warning Flow**
    - **Validates: Requirements 2.1, 2.3, 2.4**

- [x] 3. Create AdmissionWarningDialog component
  - [x] 3.1 Create AdmissionWarningDialog.tsx component
    - Display admission details (number, ward, date)
    - Provide Cancel and "Proceed with Check-in" buttons
    - _Requirements: 2.1, 2.2_

- [x] 4. Update CheckinModal error handling
  - [x] 4.1 Add admission warning state and dialog integration
    - Handle admission_warning error from backend
    - Show AdmissionWarningDialog when triggered
    - Resubmit with confirm_admission_override on confirm
    - _Requirements: 2.1, 2.2, 2.3_
  - [x] 4.2 Improve error toast messages
    - Display specific error messages from backend
    - Remove generic "Failed to check in patient" fallback
    - Show field-specific errors (department_id, claim_check_code, patient_id)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_
  - [x] 4.3 Write property test for specific error messages
    - **Property 4: Specific Error Messages**
    - **Validates: Requirements 3.4, 3.5**

- [x] 5. Checkpoint - Check-in functionality
  - Ensure all check-in tests pass, ask the user if questions arise.

- [x] 6. Create DateFilterPresets component
  - [x] 6.1 Create date-filter-presets.tsx component
    - Implement preset options (Today, Yesterday, This Week, Last Week, This Month, Last Month)
    - Implement custom date range with from/to pickers
    - Implement clear filter button
    - Implement calculateDateRange helper function
    - _Requirements: 5.1, 5.2, 5.3, 5.6_
  - [x] 6.2 Write unit tests for calculateDateRange function
    - Test all preset calculations
    - _Requirements: 5.4, 5.5_

- [x] 7. Update Claims list with CCC grouping and date filter
  - [x] 7.1 Update InsuranceClaimController sorting
    - Add orderBy claim_check_code as primary sort
    - Keep date_of_attendance as secondary sort
    - _Requirements: 4.1, 4.2_
  - [x] 7.2 Update ClaimsDataTable with visual CCC grouping
    - Add row className logic to detect same-CCC neighbors
    - Apply subtle blue highlight to grouped rows
    - _Requirements: 4.3, 4.4_
  - [x] 7.3 Integrate DateFilterPresets into ClaimsDataTable
    - Add date filter to filter bar
    - Connect to router for server-side filtering
    - _Requirements: 5.1, 5.4, 5.5, 5.7_
  - [x] 7.4 Write property test for CCC grouping
    - **Property 5: Claims CCC Grouping**
    - **Validates: Requirements 4.1, 4.2**
  - [x] 7.5 Write property test for date filter accuracy
    - **Property 6: Date Filter Accuracy**
    - **Validates: Requirements 5.4, 5.5, 5.7**

- [x] 8. Implement CCC sharing for same-day check-ins
  - [x] 8.1 Add CCC lookup for same-day check-ins
    - When checking in, look for existing same-day CCC
    - Auto-populate CCC field if found
    - _Requirements: 6.1, 6.2_
  - [x] 8.2 Add CCC mismatch warning
    - If user enters different CCC than existing same-day CCC
    - Show warning dialog with option to override
    - _Requirements: 6.3, 6.4_
  - [x] 8.3 Write property test for CCC sharing
    - **Property 7: CCC Sharing for Same-Day Check-ins**
    - **Validates: Requirements 6.1, 6.3, 6.4**

- [x] 9. Final checkpoint
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Implement Consultation Queue Date Filter (Permission-Based)
  - [x] 10.1 Create permission and add to seeder
    - Add `consultations.filter-by-date` permission to PermissionSeeder
    - _Requirements: 7.1_
  - [x] 10.2 Update ConsultationController to support date filtering
    - Accept `date_from` and `date_to` query parameters
    - Check if user has `consultations.filter-by-date` permission
    - If no permission, force filter to today only
    - If permission, apply date range filter to awaiting, active, and completed queries
    - Pass `canFilterByDate` flag to frontend
    - _Requirements: 7.2, 7.3, 7.5, 7.6, 7.7_
  - [x] 10.3 Integrate DateFilterPresets into Consultation Index page
    - Show DateFilterPresets only when user has permission
    - Default to "Today" preset
    - Connect to router for server-side filtering
    - _Requirements: 7.2, 7.4, 7.6_
  - [x] 10.4 Write unit test for permission-based date filtering
    - Test that users without permission only see today's data
    - Test that users with permission can filter by date range
    - _Requirements: 7.2, 7.3, 7.5_

- [x] 11. Final checkpoint - Consultation date filter
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- All tasks are required for comprehensive implementation
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
