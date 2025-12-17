# Requirements Document

## Introduction

The Unified Pricing Dashboard consolidates all service pricing configuration into a single interface. Currently, pricing is scattered across multiple pages (Lab Configuration, Pharmacy, Billing Configuration, Insurance Coverage Rules), making it difficult to understand and manage pricing for different patient types. This feature provides a centralized dashboard where administrators can view and edit cash prices, insurance tariffs, and copay amounts for all services in one place.

## Glossary

- **Cash Price**: The standard price charged to non-insured (self-pay) patients
- **NHIS**: National Health Insurance Scheme - Ghana's government health insurance
- **NHIS Tariff**: The official price set by NHIS that insurance pays to the facility (imported from NHIS master list)
- **NHIS Copay**: The fixed amount an NHIS patient pays out-of-pocket (set by facility)
- **Private Insurance**: Non-NHIS insurance providers (e.g., Acacia Health, Enterprise Life)
- **Coverage Percentage**: The percentage of the price that private insurance covers
- **Insurance Tariff**: Negotiated price between facility and private insurance provider
- **Coverage Rule**: Database record defining how insurance covers a specific item or category
- **Item-Specific Rule**: Coverage rule that applies to one specific item (e.g., Paracetamol only)
- **Category Default**: Coverage rule that applies to all items in a category (e.g., all drugs)

## Requirements

### Requirement 1

**User Story:** As a billing administrator, I want to view all service pricing in one dashboard, so that I can quickly understand what each patient type pays for any service.

#### Acceptance Criteria

1. WHEN a user navigates to the Pricing Dashboard THEN the System SHALL display a dropdown to select an insurance plan (including NHIS and all private insurance plans)
2. WHEN a user selects an insurance plan THEN the System SHALL display a table showing all billable items with their cash price and insurance-specific pricing
3. WHEN displaying items THEN the System SHALL group items by category (consultation, drugs, lab tests, procedures)
4. WHEN displaying items THEN the System SHALL support searching and filtering by item name, code, or category
5. WHEN displaying the dashboard THEN the System SHALL show pagination for large item lists

### Requirement 2

**User Story:** As a billing administrator, I want to edit cash prices from the dashboard, so that I can update standard pricing without navigating to multiple configuration pages.

#### Acceptance Criteria

1. WHEN a user edits a cash price for a drug THEN the System SHALL update the Drug.unit_price field
2. WHEN a user edits a cash price for a lab service THEN the System SHALL update the LabService.price field
3. WHEN a user edits a cash price for a consultation THEN the System SHALL update the DepartmentBilling.consultation_fee field
4. WHEN a cash price is updated via the dashboard THEN the System SHALL reflect the change on the original configuration page (e.g., /lab/services/configuration)
5. WHEN a user saves a price change THEN the System SHALL validate that the price is a positive number

### Requirement 3

**User Story:** As a billing administrator, I want to view and edit NHIS copay amounts per item, so that I can configure what NHIS patients pay for each service.

#### Acceptance Criteria

1. WHEN NHIS plan is selected THEN the System SHALL display columns for Cash Price, NHIS Tariff (read-only), and Patient Copay (editable)
2. WHEN displaying NHIS Tariff THEN the System SHALL show the price from the NHIS Tariff Master (NhisTariff table) as read-only
3. WHEN a user edits NHIS copay for an item THEN the System SHALL create or update an InsuranceCoverageRule with the patient_copay_amount for that specific item
4. WHEN an item has no NHIS mapping THEN the System SHALL display "Not Mapped" in the NHIS Tariff column and disable copay editing
5. WHEN displaying copay THEN the System SHALL show existing copay from InsuranceCoverageRule or indicate "Not Set" if no rule exists

### Requirement 4

**User Story:** As a billing administrator, I want to view and edit private insurance coverage settings per item, so that I can configure coverage percentages and tariffs for each insurance plan.

#### Acceptance Criteria

1. WHEN a private insurance plan is selected THEN the System SHALL display columns for Cash Price, Insurance Tariff (editable), Coverage Type, Coverage Value, and Patient Copay
2. WHEN a user edits insurance tariff THEN the System SHALL update the InsuranceCoverageRule.tariff_amount or InsuranceTariff record for that item
3. WHEN a user edits coverage percentage THEN the System SHALL update the InsuranceCoverageRule.coverage_value field
4. WHEN a user edits fixed copay THEN the System SHALL update the InsuranceCoverageRule.patient_copay_amount field
5. WHEN displaying coverage THEN the System SHALL show calculated "Patient Pays" amount based on current settings

### Requirement 5

**User Story:** As a billing administrator, I want to bulk edit copay amounts for multiple items, so that I can efficiently update pricing for many items at once.

#### Acceptance Criteria

1. WHEN a user selects multiple items THEN the System SHALL enable a "Bulk Edit" action
2. WHEN a user performs bulk edit on copay THEN the System SHALL apply the same copay amount to all selected items
3. WHEN bulk edit is performed THEN the System SHALL create or update InsuranceCoverageRule records for each selected item
4. WHEN bulk edit completes THEN the System SHALL display a summary of items updated and any errors

### Requirement 6

**User Story:** As a billing administrator, I want to see which items are missing NHIS mappings, so that I can identify items that need to be mapped before NHIS patients can be charged correctly.

#### Acceptance Criteria

1. WHEN displaying items for NHIS THEN the System SHALL visually indicate items without NHIS tariff mappings
2. WHEN a user filters by "Unmapped Items" THEN the System SHALL show only items without NHIS mappings
3. WHEN displaying unmapped items THEN the System SHALL provide a link or action to map the item to an NHIS tariff code

### Requirement 7

**User Story:** As a billing administrator, I want to export pricing data, so that I can review pricing offline or share with management.

#### Acceptance Criteria

1. WHEN a user clicks export THEN the System SHALL generate a CSV file with all visible pricing data
2. WHEN exporting THEN the System SHALL include item name, code, category, cash price, and insurance-specific columns based on selected plan
3. WHEN exporting THEN the System SHALL respect current filters and search criteria

### Requirement 8

**User Story:** As a billing administrator, I want to import pricing data from CSV/Excel, so that I can bulk update prices efficiently.

#### Acceptance Criteria

1. WHEN a user uploads a CSV/Excel file THEN the System SHALL parse the file and validate the data format
2. WHEN importing pricing data THEN the System SHALL match items by code (drug_code, lab service code, etc.)
3. WHEN importing cash prices THEN the System SHALL update the corresponding model's price field
4. WHEN importing copay amounts THEN the System SHALL create or update InsuranceCoverageRule records for the selected insurance plan
5. WHEN import contains invalid data THEN the System SHALL skip invalid rows and report errors without failing the entire import
6. WHEN import completes THEN the System SHALL display a summary showing items updated, skipped, and errors

### Requirement 9

**User Story:** As a billing administrator, I want to see an audit trail of pricing changes, so that I can track who changed prices and when.

#### Acceptance Criteria

1. WHEN a price or copay is changed THEN the System SHALL log the change with user, timestamp, old value, and new value
2. WHEN a user views item history THEN the System SHALL display recent pricing changes for that item
