# Implementation Plan

- [ ] 1. Create Insurance Coverage Service
  - Implement coverage rule lookup logic with hierarchy (specific > general > none)
  - Implement coverage calculation method for different coverage types
  - Add caching for coverage rules per insurance plan
  - _Requirements: 1.1, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ] 2. Update InsuranceCoverageRule Model
  - Add scopes for general rules, specific rules, and active rules
  - Add accessor methods for rule type identification
  - Implement cache invalidation on model save/delete
  - _Requirements: 1.1, 1.3, 2.5, 7.1, 7.2_

- [ ] 3. Create Admin Coverage Rules Management Page
  - Build main coverage rules page showing general and specific rules by category
  - Implement add/edit coverage rule modal with rule type selection
  - Add item search component for selecting specific drugs/labs/services
  - Display general rules and item-specific overrides with clear visual distinction
  - _Requirements: 3.1, 3.3, 4.1, 4.2, 4.3, 4.5, 4.6_

- [ ] 4. Implement Item Search Functionality
  - Create searchable dropdown component for drugs, lab tests, and services
  - Query appropriate tables based on coverage category
  - Display item code, name, current price, and existing rule status
  - _Requirements: 3.5, 4.3_

- [ ] 5. Build Bulk Import Feature
  - Create import controller with CSV/Excel file processing
  - Implement item code validation against system inventory
  - Add error handling and reporting for invalid rows
  - Generate import summary with created/updated/skipped counts
  - Provide CSV template download for administrators
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

- [ ] 6. Update Charge Creation Service
  - Integrate InsuranceCoverageService into charge creation flow
  - Update createInsuredCharge method to use new coverage lookup
  - Ensure backward compatibility with existing general rules
  - _Requirements: 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 7.3, 7.4, 7.5_

- [ ] 7. Update Service Point Coverage Display
  - Modify pharmacy, lab, and service point interfaces to show specific coverage
  - Display whether coverage is from specific or general rule
  - Show insurance-covered amount and patient copay clearly
  - Add badges for "Fully Covered", "Not Covered", and coverage percentages
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 8. Create Coverage Rules Reporting
  - Build coverage report page showing all items and their applicable rules
  - Add filters for category, coverage percentage, and rule type
  - Highlight items with no coverage (neither specific nor default)
  - Implement CSV/Excel export functionality
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 9. Add Database Index for Performance
  - Create composite index on (insurance_plan_id, coverage_category, item_code)
  - Test query performance with large datasets
  - _Requirements: 2.1, 2.2_

- [ ] 10. Write Tests
  - [ ] 10.1 Unit tests for InsuranceCoverageService
    - Test specific rule takes precedence over general rule
    - Test general rule fallback when no specific rule exists
    - Test no coverage when no rules exist
    - Test effective date filtering
    - Test coverage calculation for all coverage types
    - _Requirements: 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5_
  
  - [ ] 10.2 Unit tests for InsuranceCoverageRule model
    - Test general() scope returns only rules with null item_code
    - Test specific() scope returns only rules with item_code
    - Test active() scope filters by dates and is_active flag
    - Test forCategory() scope filters by coverage_category
    - _Requirements: 1.1, 1.3_
  
  - [ ] 10.3 Feature tests for admin interface
    - Test creating general coverage rule
    - Test creating item-specific coverage rule
    - Test editing coverage rules
    - Test deleting coverage rules
    - Test viewing rules grouped by category
    - _Requirements: 3.1, 3.3, 4.1, 4.2, 4.4, 4.5_
  
  - [ ] 10.4 Feature tests for bulk import
    - Test successful import with valid CSV file
    - Test validation errors for invalid data
    - Test handling of duplicate item codes
    - Test error reporting for non-existent item codes
    - Test import summary generation
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_
  
  - [ ] 10.5 Integration tests for charge creation
    - Test charge creation uses specific rule when available
    - Test charge creation falls back to general rule
    - Test charge creation with no coverage
    - Test coverage display at service points
    - _Requirements: 1.4, 1.5, 2.1, 2.2, 8.1, 8.2_
  
  - [ ] 10.6 Integration test for complete patient journey
    - Test patient check-in with insurance
    - Test services using specific rules where applicable
    - Test services using general rules as fallback
    - Test billing shows correct copay amounts
    - Test claims include correct coverage information
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 11. Create Documentation and Training Materials
  - Write administrator guide for managing coverage rules
  - Create CSV import template with examples
  - Document the two-tier coverage system workflow
  - Provide examples of general vs specific rule scenarios
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 5.1_
