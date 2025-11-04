# Implementation Plan

- [x]   1. Update CoverageExceptionTemplate Export Class
    - [x] 1.1 Add insurancePlanId parameter to constructor
        - Update constructor to accept both category and insurancePlanId
        - Pass insurancePlanId to PrePopulatedDataSheet
        - _Requirements: 1.1, 5.2_

    - [x] 1.2 Create EnhancedInstructionsSheet class
        - Replace existing InstructionsSheet with enhanced version
        - Add detailed coverage type explanations with examples
        - Add warnings about not modifying pre-filled columns
        - Style important sections with bold formatting
        - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

    - [x] 1.3 Create PrePopulatedDataSheet class
        - Implement getItemsForCategory() to query system inventory (Drug, LabTest, Service models)
        - Query existing specific coverage rules for the plan
        - Query general rule for default values
        - Map items to rows with pre-filled data (code, name, price, coverage_type, coverage_value)
        - Sort items alphabetically by name
        - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

    - [x] 1.4 Update headings to include new columns
        - Change from coverage_percentage to coverage_type and coverage_value
        - Add current_price column
        - Maintain backward compatibility by supporting both formats
        - _Requirements: 2.1, 2.2_

- [x]   2. Update InsuranceCoverageImportController
    - [x] 2.1 Add downloadTemplate method
        - Authorize user can manage the insurance plan
        - Validate category parameter
        - Generate filename with category, plan name, and date
        - Return Excel download using CoverageExceptionTemplate
        - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

    - [x] 2.2 Update import method to support all coverage types
        - Detect file format (old vs new) by checking for coverage_type column
        - Convert old format (coverage_percentage) to new format if needed
        - Validate coverage_type is one of: percentage, fixed_amount, full, excluded
        - Call processCoverageType() to get standardized coverage data

        - _Requirements: 2.3, 2.4, 2.5, 2.6, 2.7, 4.1, 4.2, 4.3, 4.4, 7.1, 7.2, 7.3, 7.4, 7.5_

    - [x] 2.3 Create processCoverageType method
        - Handle percentage: coverage_value as-is, copay = 100 - value
        - Handle fixed_amount: coverage_value as-is, copay = 0
        - Handle full: coverage_value = 100, copay = 0
        - Handle excluded: coverage_value = 0, copay = 100, is_covered = false

        - Return standardized array with coverage_value, copay_percentage, is_covered
        - _Requirements: 4.1, 4.2, 4.3, 4.4_

    - [x] 2.4 Enhance error reporting
        - Add specific error message for invalid coverage_type with valid options
        - Include row numbers in all error messages
        - Collect all errors and return in results array
        - _Requirements: 4.5, 6.1, 6.2, 6.3, 6.4, 6.5_

- [x]   3. Update Frontend BulkImportModal Component
    - [x] 3.1 Add template download functionality
        - Add "Download Pre-populated Template" button
        - Implement handleDownloadTemplate() to call API endpoint
        - Show loading state while downloading
        - Trigger file download in browser
        - _Requirements: 8.1, 8.2, 8.3, 8.4_

    - [x] 3.2 Update instructions and UI
        - Add info box explaining pre-populated templates
        - List supported coverage types
        - Update help text to mention new template structure
        - _Requirements: 8.5_

    - [x] 3.3 Enhance results display
        - Show created/updated/skipped counts
        - Display errors with row numbers
        - Add scrollable error list for many errors
        - Show success message when no errors
        - _Requirements: 6.4, 6.5_

- [x]   4. Add Route for Template Download
    - Add GET route: /admin/insurance/plans/{plan}/coverage-rules/template/{category}
    - Map to InsuranceCoverageImportController@downloadTemplate
    - Add route name: insurance.coverage.template
    - _Requirements: 5.1_

- [x]   5. Update Existing Import Route
    - Ensure existing POST route still works: /admin/insurance/plans/{plan}/coverage-rules/import
    - Verify it uses updated import method with new coverage type support
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_

- [x]   6. Write Tests






    - [x] 6.1 Unit tests for CoverageExceptionTemplate


        - Test getItemsForCategory() returns correct items for each category
        - Test pre-fills existing specific rule values
        - Test falls back to general rule values when no specific rule
        - Test uses default values when no rules exist
        - Test items are sorted alphabetically
        - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_
    - [x] 6.2 Unit tests for processCoverageType method


        - Test percentage type returns correct copay calculation
        - Test fixed_amount type returns copay = 0
        - Test full type returns coverage_value = 100, copay = 0
        - Test excluded type returns is_covered = false, copay = 100
        - _Requirements: 4.1, 4.2, 4.3, 4.4_
    - [x] 6.3 Feature test for template download


        - Test authorized user can download template
        - Test template includes all items for category
        - Test template pre-fills existing coverage values
        - Test unauthorized user cannot download
        - Test invalid category returns error
        - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_
    - [x] 6.4 Feature tests for import with all coverage types


        - Test import with percentage coverage type
        - Test import with fixed_amount coverage type
        - Test import with full coverage type
        - Test import with excluded coverage type
        - Test invalid coverage_type returns specific error
        - Test invalid item_code returns error with row number
        - Test import summary shows correct counts
        - _Requirements: 2.3, 2.4, 2.5, 2.6, 2.7, 4.1, 4.2, 4.3, 4.4, 4.5, 6.1, 6.2, 6.3, 6.4, 6.5_
    - [x] 6.5 Feature test for backward compatibility


        - Test old format CSV with coverage_percentage still works
        - Test old format is converted to new format internally
        - Test new format takes precedence when both exist
        - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5_
    - [x] 6.6 Integration test for complete workflow


        - Test download template for plan with existing rules
        - Test template includes pre-filled values
        - Test modify template and upload
        - Test rules are created/updated correctly
        - Test coverage calculations use new rules
        - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 4.1, 5.1, 5.2_

- [x]   7. Update Documentation




    - Create user guide for new bulk import workflow
    - Document all coverage types with examples
    - Add screenshots of template structure
    - Explain difference between old and new format
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_
