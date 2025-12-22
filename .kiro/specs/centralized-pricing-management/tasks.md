# Implementation Plan

- [x] 1. Database migrations and model updates





  - [x] 1.1 Add is_unmapped column to insurance_coverage_rules table


    - Create migration to add `is_unmapped` boolean column with default false
    - _Requirements: 3.2_

  - [x] 1.2 Add is_unpriced column to prescriptions table

    - Create migration to add `is_unpriced` boolean column with default false
    - _Requirements: 6.1_

  - [x] 1.3 Add is_unpriced and external_referral status to lab_orders table

    - Create migration to add `is_unpriced` boolean column
    - Update status enum to include 'external_referral'
    - _Requirements: 7.2_

  - [x] 1.4 Add category default fields to insurance_plans table

    - Create migration to add category default percentage fields (consultation_default, drugs_default, labs_default, procedures_default)
    - _Requirements: 8.4, 8.5_
  - [x] 1.5 Update InsuranceCoverageRule model


    - Add `is_unmapped` to fillable array
    - Add scope for unmapped rules
    - _Requirements: 3.2_

  - [x] 1.6 Update Prescription model

    - Add `is_unpriced` to fillable array
    - _Requirements: 6.1_

  - [x] 1.7 Update LabOrder model

    - Add `is_unpriced` to fillable array
    - Add 'external_referral' to status constants
    - _Requirements: 7.2_

  - [x] 1.8 Update InsurancePlan model

    - Add category default fields to fillable array
    - _Requirements: 8.4_

- [x] 2. Implement flexible copay service logic







  - [x] 2.1 Add updateFlexibleCopay method to PricingDashboardService


    - Create or update InsuranceCoverageRule with is_unmapped = true


    - Handle clearing copay (delete or nullify rule)


    - _Requirements: 3.2, 3.5_


  - [x] 2.2 Write property test for flexible copay creation
    - **Property 4: Flexible copay creates coverage rule with unmapped flag**
    - **Validates: Requirements 3.2**
  - [x] 2.3 Add getPricingStatus method to PricingDashboardService
    - Determine status: priced, unpriced, nhis_mapped, flexible_copay, not_mapped
    - _Requirements: 5.1_
  - [x] 2.4 Write property test for pricing status determination
    - **Property 5: Pricing status correctly determined**
    - **Validates: Requirements 3.3, 3.4, 5.1**
  - [x] 2.5 Write property test for clearing flexible copay
    - **Property 6: Clearing flexible copay removes or nullifies rule**
    - **Validates: Requirements 3.5**

- [x] 3. Update billing logic for unmapped NHIS items






  - [x] 3.1 Update InsuranceCoverageService.calculateCoverage method

    - Handle unmapped items with flexible copay (insurance_amount = 0, patient_amount = copay)
    - Handle unmapped items without copay (insurance_amount = 0, patient_amount = cash_price)
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

  - [x] 3.2 Write property test for unmapped billing with copay

    - **Property 7: Unmapped NHIS billing with flexible copay**
    - **Validates: Requirements 4.1, 4.3**

  - [x] 3.3 Write property test for unmapped billing without copay

    - **Property 8: Unmapped NHIS billing without copay**


    - **Validates: Requirements 4.2, 4.4**



  - [ ] 3.4 Update InsuranceClaimService to include unmapped items
    - Include unmapped items in claims with insurance_amount = 0

    - _Requirements: 4.5_
  - [x] 3.5 Write property test for claims including unmapped items

    - **Property 9: Insurance claims include unmapped items**
    - **Validates: Requirements 4.5**

- [x] 4. Checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Implement unpriced item handling






  - [x] 5.1 Create PrescriptionObserver for unpriced drugs

    - Auto-set dispensing_source to 'external' for unpriced drugs
    - Set is_unpriced flag
    - _Requirements: 6.1_

  - [x] 5.2 Write property test for unpriced drug auto-external

    - **Property 11: Unpriced drugs auto-set to external**
    - **Validates: Requirements 6.1**
  - [x] 5.3 Update pharmacy dispensing query to exclude external prescriptions


    - Filter out prescriptions with dispensing_source = 'external'
    - _Requirements: 6.4_

  - [x] 5.4 Write property test for dispensing queue exclusion

    - **Property 12: External prescriptions excluded from dispensing queue**
    - **Validates: Requirements 6.4**
  - [x] 5.5 Create LabOrderObserver for unpriced labs


    - Auto-set status to 'external_referral' for unpriced labs
    - Set is_unpriced flag
    - _Requirements: 7.2_

  - [x] 5.6 Write property test for unpriced lab auto-external-referral

    - **Property 13: Unpriced labs auto-set to external referral**
    - **Validates: Requirements 7.2**
  - [x] 5.7 Update lab work queue query to exclude external referrals


    - Filter out lab orders with status = 'external_referral'
    - _Requirements: 7.4_

  - [x] 5.8 Write property test for lab queue exclusion

    - **Property 14: External referral orders excluded from lab queue**
    - **Validates: Requirements 7.4**

- [x] 6. Checkpoint - Ensure all tests pass

  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Add pricing status filter to dashboard





  - [x] 7.1 Update PricingDashboardService.getPricingData to support pricing status filter


    - Add pricingStatus parameter ('unpriced', 'priced', null)
    - Filter items by cash price null/zero or positive
    - _Requirements: 2.1, 2.2_

  - [x] 7.2 Write property test for unpriced filter

    - **Property 2: Unpriced filter returns only unpriced items**
    - **Validates: Requirements 2.1**
  - [x] 7.3 Write property test for priced filter


    - **Property 3: Priced filter returns only priced items**
    - **Validates: Requirements 2.2**
  - [x] 7.4 Add getPricingStatusSummary method to PricingDashboardService


    - Return counts of unpriced, priced, nhis_mapped, nhis_unmapped, flexible_copay items
    - _Requirements: 5.2_

  - [x] 7.5 Write property test for summary counts

    - **Property 10: Pricing summary counts are accurate**
    - **Validates: Requirements 5.2**

- [x] 8. Update frontend - Pricing Dashboard enhancements





  - [x] 8.1 Add PricingStatusFilter component


    - Dropdown with All, Unpriced, Priced options
    - _Requirements: 2.1, 2.2_
  - [x] 8.2 Add PricingStatusBadge component


    - Badge showing status with appropriate colors (Priced=green, Unpriced=red, etc.)
    - _Requirements: 2.3, 5.1_
  - [x] 8.3 Add PricingSummaryCards component


    - Cards showing counts of items in each status category
    - _Requirements: 5.2_
  - [x] 8.4 Update PricingTable to show status badges


    - Display pricing status for each item
    - _Requirements: 5.1_
  - [x] 8.5 Enable copay editing for unmapped NHIS items


    - Allow editing copay even when item is not mapped
    - Show "Flexible Copay" status after setting
    - _Requirements: 3.1, 3.3_
  - [x] 8.6 Add flexible copay API endpoint


    - PUT /admin/pricing-dashboard/flexible-copay
    - _Requirements: 3.2_

- [x] 9. Remove price fields from configuration forms






  - [x] 9.1 Update Drug Create/Edit forms

    - Remove unit_price field
    - Add "Set Price" link to Pricing Dashboard
    - _Requirements: 1.1, 1.6_

  - [x] 9.2 Update Lab Service configuration forms

    - Remove price field
    - Add "Set Price" link to Pricing Dashboard
    - _Requirements: 1.2, 1.6_

  - [x] 9.3 Update Department Billing configuration forms

    - Remove consultation_fee field
    - Add "Set Price" link to Pricing Dashboard
    - _Requirements: 1.3, 1.6_

  - [x] 9.4 Update Drug/LabService creation to default price to null

    - Ensure new items are created with null price
    - _Requirements: 1.4, 1.5_

  - [x] 9.5 Write property test for new items defaulting to unpriced

    - **Property 1: New items default to unpriced**
    - **Validates: Requirements 1.4, 1.5**


- [ ] 10. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 11. Update frontend - Unpriced item indicators





  - [x] 11.1 Add visual indicator for unpriced drugs in prescription form


    - Show warning/badge when prescribing unpriced drug
    - Display note about external dispensing
    - _Requirements: 6.2, 6.3_

  - [x] 11.2 Add visual indicator for unpriced labs in lab order form

    - Show alert when ordering unpriced lab
    - Display note about external referral
    - _Requirements: 7.1, 7.3_

  - [x] 11.3 Update pharmacy dispensing queue UI

    - Ensure external prescriptions are not shown
    - _Requirements: 6.4_
  - [x] 11.4 Update lab work queue UI


    - Ensure external referral orders are not shown
    - _Requirements: 7.4_

- [x] 12. Simplify Insurance Plan UI





  - [x] 12.1 Update Insurance Plan Show page


    - Remove Coverage Rules table
    - Remove Tariffs table
    - Add "Manage Pricing" link to Pricing Dashboard with plan pre-selected
    - _Requirements: 8.1, 8.2_

  - [x] 12.2 Update Insurance Plan Edit page

    - Add category default percentage fields
    - _Requirements: 8.4_

  - [x] 12.3 Add redirects for old coverage rule pages

    - Redirect /admin/insurance/plans/{plan}/coverage to Pricing Dashboard
    - Redirect /admin/insurance/coverage-rules/* to Pricing Dashboard
    - _Requirements: 8.3_

  - [x] 12.4 Update InsuranceCoverageService to use category defaults

    - Fall back to plan's category default when no item-specific rule exists
    - _Requirements: 8.5_

  - [x] 12.5 Write property test for category defaults fallback

    - **Property 15: Category defaults used when no item rule exists**
    - **Validates: Requirements 8.5**



- [x] 13. Final Checkpoint - Ensure all tests pass



  - Ensure all tests pass, ask the user if questions arise.
