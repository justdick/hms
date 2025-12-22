# Requirements Document

## Introduction

This feature extends the Unified Pricing Dashboard to become the single source of truth for all pricing in the HMS. Currently, prices can be edited in multiple places (Drug forms, Lab Service configuration, Billing configuration, and the Pricing Dashboard), leading to confusion and potential inconsistencies. This enhancement removes price fields from individual configuration forms, adds support for NHIS copay on unmapped items (flexible copay), and updates billing logic to correctly handle unmapped NHIS items.

## Glossary

- **Cash Price**: The standard price charged to non-insured (self-pay) patients
- **NHIS**: National Health Insurance Scheme - Ghana's government health insurance
- **NHIS Tariff**: The official price set by NHIS that insurance pays to the facility (from NHIS master list)
- **NHIS Copay**: The fixed amount an NHIS patient pays out-of-pocket (set by facility)
- **Flexible Copay**: NHIS copay applied to items that are not mapped to NHIS tariffs (facility charges full cash price, patient pays copay)
- **Unmapped Item**: An item (drug, lab test, procedure) that does not have a corresponding NHIS tariff code
- **Unpriced Item**: An item that has no cash price set (price is null or zero)
- **Unified Pricing Dashboard**: The centralized interface for viewing and managing all service pricing
- **Coverage Rule**: Database record defining how insurance covers a specific item or category

## Requirements

### Requirement 1

**User Story:** As a system administrator, I want price fields removed from individual configuration forms, so that all pricing is managed exclusively through the Unified Pricing Dashboard.

#### Acceptance Criteria

1. WHEN a user views the Drug creation/edit form THEN the System SHALL NOT display the unit_price field
2. WHEN a user views the Lab Service configuration form THEN the System SHALL NOT display the price field
3. WHEN a user views the Department Billing configuration form THEN the System SHALL NOT display the consultation_fee field
4. WHEN a user creates a new Drug THEN the System SHALL set unit_price to null (unpriced)
5. WHEN a user creates a new Lab Service THEN the System SHALL set price to null (unpriced)
6. WHEN displaying items in configuration lists THEN the System SHALL show a "Set Price" link that navigates to the Pricing Dashboard with the item pre-filtered

### Requirement 2

**User Story:** As a billing administrator, I want to filter items by pricing status, so that I can identify items that need pricing configuration.

#### Acceptance Criteria

1. WHEN a user selects "Unpriced Items" filter THEN the System SHALL display only items with null or zero cash price
2. WHEN a user selects "Priced Items" filter THEN the System SHALL display only items with a positive cash price
3. WHEN displaying unpriced items THEN the System SHALL visually highlight them as requiring attention
4. WHEN a new item is created THEN the System SHALL include it in the "Unpriced Items" filter until a price is set

### Requirement 3

**User Story:** As a billing administrator, I want to set NHIS copay for unmapped items, so that NHIS patients can still receive services not covered by the NHIS tariff list.

#### Acceptance Criteria

1. WHEN NHIS plan is selected and an item is unmapped THEN the System SHALL allow editing the patient copay amount
2. WHEN a user sets copay for an unmapped item THEN the System SHALL create an InsuranceCoverageRule with the patient_copay_amount and a special "unmapped" indicator
3. WHEN displaying unmapped items with copay THEN the System SHALL show "Flexible Copay" status instead of "Not Mapped"
4. WHEN an unmapped item has no copay set THEN the System SHALL display "Not Mapped - No Copay" status
5. WHEN a user clears the copay for an unmapped item THEN the System SHALL remove the InsuranceCoverageRule or set copay to null

### Requirement 4

**User Story:** As a billing clerk, I want the billing system to correctly charge NHIS patients for unmapped items with flexible copay, so that patients pay the configured copay amount.

#### Acceptance Criteria

1. WHEN an NHIS patient receives an unmapped service with flexible copay THEN the System SHALL charge the patient the configured copay amount
2. WHEN an NHIS patient receives an unmapped service without copay configured THEN the System SHALL charge the patient the full cash price
3. WHEN creating a charge for an unmapped NHIS item with copay THEN the System SHALL record the insurance_amount as zero and patient_amount as the copay
4. WHEN creating a charge for an unmapped NHIS item without copay THEN the System SHALL record the insurance_amount as zero and patient_amount as the cash price
5. WHEN generating an insurance claim THEN the System SHALL include unmapped items with zero insurance amount for NHIS auditing purposes

### Requirement 5

**User Story:** As a billing administrator, I want clear visibility of pricing status across all items, so that I can ensure all services are properly configured before use.

#### Acceptance Criteria

1. WHEN displaying items in the Pricing Dashboard THEN the System SHALL show a status indicator (Priced, Unpriced, NHIS Mapped, Flexible Copay, Not Mapped)
2. WHEN displaying dashboard summary THEN the System SHALL show counts of unpriced items, unmapped NHIS items, and items with flexible copay

### Requirement 6

**User Story:** As a doctor, I want to prescribe unpriced drugs with automatic external dispensing, so that patients can still receive medications not yet configured in the system.

#### Acceptance Criteria

1. WHEN a doctor prescribes an unpriced drug THEN the System SHALL automatically set the dispensing source to "external"
2. WHEN an unpriced drug is prescribed THEN the System SHALL display a visual indicator that the drug will be dispensed externally
3. WHEN displaying the prescription THEN the System SHALL show a note explaining the drug is unpriced and marked for external dispensing
4. WHEN the pharmacy views an unpriced prescription THEN the System SHALL skip it in the dispensing queue (already marked external)

### Requirement 7

**User Story:** As a doctor, I want to order unpriced lab tests with an alert, so that patients can be referred externally while maintaining complete consultation records.

#### Acceptance Criteria

1. WHEN a doctor orders an unpriced lab test THEN the System SHALL display an alert indicating the test must be done externally
2. WHEN an unpriced lab test is ordered THEN the System SHALL automatically set the status to "external_referral"
3. WHEN displaying the lab order in the consultation THEN the System SHALL show a visual indicator that the test is for external referral
4. WHEN the lab views their work queue THEN the System SHALL exclude external referral orders (they are not processed by the lab)

### Requirement 8

**User Story:** As a system administrator, I want coverage rule and tariff management consolidated into the Pricing Dashboard, so that there is a single place to manage all pricing configuration.

#### Acceptance Criteria

1. WHEN a user views the Insurance Plan Show page THEN the System SHALL display a "Manage Pricing" link instead of Coverage Rules and Tariffs tables
2. WHEN a user clicks "Manage Pricing" THEN the System SHALL navigate to the Pricing Dashboard with the insurance plan pre-selected
3. WHEN a user attempts to access the old coverage rule management pages THEN the System SHALL redirect to the Pricing Dashboard
4. WHEN a user edits an Insurance Plan THEN the System SHALL allow setting category default percentages (consultation, drugs, labs, procedures)
5. WHEN no item-specific rule exists THEN the System SHALL use the category default percentage from the Insurance Plan

