# Requirements Document

## Introduction

This feature implements comprehensive NHIS (National Health Insurance Scheme) claims integration for the Hospital Management System. It includes NHIS tariff master management, item-to-NHIS code mapping, modified coverage calculation for NHIS, claim generation with G-DRG selection, batch management for monthly submissions, XML export for NHIA portal upload, and reimbursement tracking. The system maintains compatibility with existing insurance coverage rules while providing NHIS-specific workflows.

## Glossary

- **NHIS (National Health Insurance Scheme)**: Ghana's national health insurance provider with specific claim submission requirements.
- **NHIA (National Health Insurance Authority)**: The governing body that manages NHIS, receives claim submissions via their portal.
- **G-DRG (Ghana Diagnosis Related Groups)**: A classification system used by NHIS to categorize patient consultations/attendances. Each G-DRG code has an associated tariff price.
- **NHIS Tariff Master**: The official NHIS price list containing all covered items (medicines, labs, procedures) with their NHIS codes and reimbursement prices.
- **NHIS Code**: Unique identifier assigned by NHIS to each covered item (e.g., "MED-001" for a specific medicine).
- **Item Mapping**: The link between hospital's internal item codes and NHIS tariff codes.
- **Claim Vetting**: The process of reviewing and validating insurance claims before submission.
- **Claims Officer**: A user responsible for reviewing, vetting, and submitting insurance claims.
- **Claim Batch**: A group of vetted claims bundled together for monthly submission to NHIA.
- **MDC (Major Diagnostic Category)**: A broad classification of diagnoses (e.g., Out Patient, In Patient).
- **Copay**: The fixed amount a patient pays in addition to what NHIS covers.

## Requirements

### Requirement 1: NHIS Tariff Master Management

**User Story:** As an administrator, I want to import and manage the official NHIS tariff price list, so that the system has accurate NHIS reimbursement prices for all covered items.

#### Acceptance Criteria

1. WHEN an administrator accesses the NHIS Tariff Master page THEN the System SHALL display a list of all NHIS tariffs with their codes, names, categories, and prices.
2. WHEN an administrator imports an NHIS tariff file THEN the System SHALL validate the file format and create or update tariff records accordingly.
3. WHEN an administrator imports tariffs with existing codes THEN the System SHALL update the prices for those codes without creating duplicates.
4. WHEN an administrator searches for an NHIS tariff THEN the System SHALL filter results by code, name, or category.
5. WHEN an administrator views tariff details THEN the System SHALL display the NHIS code, name, category (medicine/lab/procedure/consultation), and current price.
6. WHEN the NHIS tariff master is updated THEN the System SHALL use the new prices for all subsequent coverage calculations and claims.

### Requirement 2: NHIS Item Mapping

**User Story:** As an administrator, I want to map hospital items (drugs, labs, procedures) to their corresponding NHIS tariff codes, so that the system can automatically determine NHIS prices and codes for claims.

#### Acceptance Criteria

1. WHEN an administrator accesses the NHIS mapping page THEN the System SHALL display hospital items with their current NHIS mapping status.
2. WHEN an administrator maps a hospital item to an NHIS tariff THEN the System SHALL save the link between the item and the NHIS tariff code.
3. WHEN an administrator searches for unmapped items THEN the System SHALL filter to show only items without NHIS mappings.
4. WHEN an administrator bulk-maps items via CSV import THEN the System SHALL match hospital item codes to NHIS codes and create mappings.
5. WHEN displaying a mapped item THEN the System SHALL show both the hospital item details and the linked NHIS tariff details.
6. WHEN an item is not mapped to an NHIS tariff THEN the System SHALL flag the item as "NHIS Not Covered" during claim generation.

### Requirement 3: G-DRG Tariff Management

**User Story:** As an administrator, I want to manage G-DRG tariff codes and prices, so that claims officers can select appropriate consultation tariffs during NHIS claim vetting.

#### Acceptance Criteria

1. WHEN an administrator accesses the G-DRG tariff management page THEN the System SHALL display a list of all G-DRG codes with their names, codes, MDC category, and tariff prices.
2. WHEN an administrator creates a new G-DRG tariff THEN the System SHALL require a unique code, name, MDC category, and tariff price in GHS.
3. WHEN an administrator imports G-DRG tariffs from a file THEN the System SHALL validate the data format and create or update tariff records accordingly.
4. WHEN an administrator updates a G-DRG tariff price THEN the System SHALL save the new price without affecting previously vetted claims.
5. WHEN an administrator searches for a G-DRG tariff THEN the System SHALL filter results by code, name, or MDC category.

### Requirement 4: NHIS Provider Configuration

**User Story:** As an administrator, I want to configure NHIS as a special insurance provider, so that the system applies NHIS-specific rules for coverage calculation and claims.

#### Acceptance Criteria

1. WHEN an administrator edits an insurance provider THEN the System SHALL display an "Is NHIS Provider" toggle option.
2. WHEN the "Is NHIS Provider" option is enabled THEN the System SHALL use NHIS Tariff Master prices for coverage calculations instead of coverage rule tariffs.
3. WHEN the "Is NHIS Provider" option is enabled THEN the System SHALL require G-DRG selection during claim vetting.
4. WHEN the "Is NHIS Provider" option is disabled THEN the System SHALL use standard coverage rule calculations.

### Requirement 5: NHIS Coverage Calculation

**User Story:** As a system user, I want NHIS coverage to be calculated using the NHIS Tariff Master prices, so that charges and claims reflect accurate NHIS reimbursement amounts.

#### Acceptance Criteria

1. WHEN calculating coverage for an NHIS patient THEN the System SHALL look up the item's price from the NHIS Tariff Master via the item mapping.
2. WHEN an item is mapped to an NHIS tariff THEN the System SHALL use the NHIS tariff price as the insurance-covered amount.
3. WHEN calculating patient payment for NHIS THEN the System SHALL use only the copay amount from the coverage rule, not percentage calculations.
4. WHEN an item is not mapped to an NHIS tariff THEN the System SHALL mark the item as not covered by NHIS.
5. WHEN the NHIS Tariff Master price changes THEN the System SHALL use the new price for all subsequent coverage calculations.

### Requirement 6: NHIS Coverage CSV Export/Import

**User Story:** As an administrator, I want to download and upload NHIS coverage rules via CSV, so that I can efficiently set copay amounts for NHIS items.

#### Acceptance Criteria

1. WHEN an administrator downloads the NHIS coverage CSV THEN the System SHALL include item code, item name, current hospital price, NHIS tariff price (from Master), and current copay amount.
2. WHEN an administrator downloads the NHIS coverage CSV THEN the System SHALL pre-fill the NHIS tariff price from the Tariff Master for mapped items.
3. WHEN an administrator imports the NHIS coverage CSV THEN the System SHALL save only the copay amount to the coverage rule.
4. WHEN an administrator imports the NHIS coverage CSV THEN the System SHALL ignore tariff values in the CSV since they come from the Master.
5. WHEN displaying the CSV template THEN the System SHALL include instructions explaining that tariff comes from Master and only copay is editable.

### Requirement 7: NHIS Member Management

**User Story:** As a receptionist, I want to record and verify patient NHIS membership details, so that NHIS patients can be properly identified and their claims processed.

#### Acceptance Criteria

1. WHEN registering or editing a patient THEN the System SHALL allow entry of NHIS member ID and card expiry date.
2. WHEN checking in an NHIS patient THEN the System SHALL display the NHIS member ID and expiry status.
3. WHEN an NHIS card is expired THEN the System SHALL display a warning to staff during check-in.
4. WHEN selecting payment type at check-in THEN the System SHALL allow selection of NHIS if the patient has a valid NHIS member ID.
5. WHEN an NHIS patient is checked in THEN the System SHALL flag the visit for NHIS claim generation.

### Requirement 8: Modal-Based Claim Vetting Interface

**User Story:** As a claims officer, I want to vet claims in a modal overlay, so that I can efficiently review claims without navigating away from the claims list.

#### Acceptance Criteria

1. WHEN a claims officer clicks "Vet Claim" on a claim row THEN the System SHALL open a modal overlay displaying the claim details.
2. WHEN the vetting modal opens THEN the System SHALL display patient information including name, date of birth, gender, folder number, and NHIS member ID.
3. WHEN the vetting modal opens THEN the System SHALL display attendance details including type of attendance, date of attendance, date of discharge, type of service, and service outcome.
4. WHEN the vetting modal opens THEN the System SHALL display claim metadata including specialty attended, attending prescriber, and claim check code.
5. WHEN the claims officer closes the modal without approving THEN the System SHALL return to the claims list without saving changes.

### Requirement 9: G-DRG Selection for NHIS Claims

**User Story:** As a claims officer, I want to select a G-DRG code for NHIS claims, so that the claim is properly categorized for NHIS submission.

#### Acceptance Criteria

1. WHEN the vetting modal opens for an NHIS claim THEN the System SHALL display a searchable G-DRG dropdown field.
2. WHEN the claims officer types in the G-DRG field THEN the System SHALL filter available G-DRG options by name or code.
3. WHEN displaying G-DRG options THEN the System SHALL format each option as "Name (Code - GHS Price)".
4. WHEN a G-DRG is selected THEN the System SHALL update the claim total calculation to include the G-DRG tariff price.
5. WHEN no G-DRG is selected for an NHIS claim THEN the System SHALL prevent claim approval.

### Requirement 10: Claim Diagnoses Management

**User Story:** As a claims officer, I want to add or remove diagnoses on a claim, so that I can ensure the claim accurately reflects the conditions treated.

#### Acceptance Criteria

1. WHEN the vetting modal opens THEN the System SHALL display all diagnoses from the original consultation as pre-populated entries.
2. WHEN the claims officer adds a diagnosis to the claim THEN the System SHALL save the diagnosis to the claim record only, not the original consultation.
3. WHEN the claims officer removes a diagnosis from the claim THEN the System SHALL remove the diagnosis from the claim record only.
4. WHEN displaying diagnoses THEN the System SHALL show the diagnosis name and ICD-10 code.
5. WHEN the claims officer searches for a diagnosis to add THEN the System SHALL filter available diagnoses by name or ICD-10 code.

### Requirement 11: Claim Items Display

**User Story:** As a claims officer, I want to view all items (investigations, prescriptions, procedures) associated with a claim, so that I can verify the services provided.

#### Acceptance Criteria

1. WHEN the vetting modal opens THEN the System SHALL display tabs for Investigations, Prescriptions, and Procedures.
2. WHEN displaying claim items THEN the System SHALL show the item name, NHIS code (if mapped), quantity, and NHIS tariff price.
3. WHEN an item is not mapped to NHIS THEN the System SHALL display "Not Covered" indicator for that item.
4. WHEN a patient was admitted THEN the System SHALL aggregate items from the initial consultation and all ward round records.
5. WHEN displaying item totals THEN the System SHALL show subtotals for each category and a grand total.

### Requirement 12: Claim Total Calculation

**User Story:** As a claims officer, I want to see the calculated claim total, so that I can verify the amount before approving the claim.

#### Acceptance Criteria

1. WHEN displaying an NHIS claim THEN the System SHALL calculate the grand total as: G-DRG tariff + Investigations total + Prescriptions total + Procedures total.
2. WHEN the G-DRG selection changes THEN the System SHALL recalculate and display the updated grand total.
3. WHEN displaying the grand total THEN the System SHALL format the amount as "GRAND TOTAL: GHS [amount]".
4. WHEN an item is not covered by NHIS THEN the System SHALL exclude that item from the NHIS claim total.

### Requirement 13: Claim Approval

**User Story:** As a claims officer, I want to approve a vetted claim, so that the claim is ready for batch submission.

#### Acceptance Criteria

1. WHEN the claims officer clicks "Approve Claim" THEN the System SHALL validate that all required fields are completed.
2. WHEN an NHIS claim is approved without a G-DRG selection THEN the System SHALL display an error message and prevent approval.
3. WHEN a claim is successfully approved THEN the System SHALL update the claim status to "vetted".
4. WHEN a claim is successfully approved THEN the System SHALL record the approving user and approval timestamp.
5. WHEN a claim is successfully approved THEN the System SHALL store the NHIS tariff prices at the time of vetting for historical accuracy.

### Requirement 14: Claim Batch Management

**User Story:** As a claims officer, I want to group vetted claims into batches for monthly submission, so that I can organize and track claim submissions to NHIA.

#### Acceptance Criteria

1. WHEN a claims officer creates a new batch THEN the System SHALL require a batch name and submission period (month/year).
2. WHEN a claims officer adds claims to a batch THEN the System SHALL only allow vetted claims to be added.
3. WHEN displaying a batch THEN the System SHALL show the total number of claims, total claim amount, and batch status.
4. WHEN a batch is created THEN the System SHALL set the initial status to "draft".
5. WHEN a claims officer finalizes a batch THEN the System SHALL prevent further modifications to the batch.

### Requirement 15: XML Export for NHIA Submission

**User Story:** As a claims officer, I want to export a claim batch to XML format, so that I can upload it to the NHIA portal for submission.

#### Acceptance Criteria

1. WHEN a claims officer exports a batch THEN the System SHALL generate an XML file in NHIA-compliant format.
2. WHEN generating the XML THEN the System SHALL include facility code, batch details, and all claim records with their line items.
3. WHEN generating claim records THEN the System SHALL include patient NHIS ID, G-DRG code, diagnoses (ICD-10), and all item NHIS codes with prices.
4. WHEN the export is complete THEN the System SHALL allow the user to download the XML file.
5. WHEN a batch is exported THEN the System SHALL record the export timestamp.

### Requirement 16: Batch Submission Tracking

**User Story:** As a claims officer, I want to track the submission status of claim batches, so that I can monitor the progress of claims through the NHIA process.

#### Acceptance Criteria

1. WHEN a claims officer marks a batch as submitted THEN the System SHALL update the batch status to "submitted" and record the submission date.
2. WHEN viewing submitted batches THEN the System SHALL display submission date and current status.
3. WHEN a batch status changes THEN the System SHALL maintain a history of status changes with timestamps.
4. WHEN displaying batch list THEN the System SHALL allow filtering by status (draft, submitted, approved, rejected, paid).

### Requirement 17: Reimbursement Tracking

**User Story:** As a claims officer, I want to record NHIA responses and payments, so that I can track which claims have been approved, rejected, or paid.

#### Acceptance Criteria

1. WHEN NHIA approves claims THEN the System SHALL allow recording approved amount per claim.
2. WHEN NHIA rejects claims THEN the System SHALL allow recording rejection reason per claim.
3. WHEN payment is received THEN the System SHALL allow recording payment amount and date per batch.
4. WHEN displaying claim details THEN the System SHALL show claimed amount, approved amount, and payment status.
5. WHEN a claim is rejected THEN the System SHALL allow the claim to be corrected and resubmitted in a new batch.

### Requirement 18: NHIS Claims Reports

**User Story:** As a claims officer or administrator, I want to generate reports on NHIS claims, so that I can analyze claim performance and outstanding amounts.

#### Acceptance Criteria

1. WHEN generating a claims summary report THEN the System SHALL show total claims, total amount claimed, approved, rejected, and paid by period.
2. WHEN generating an outstanding report THEN the System SHALL show all unpaid approved claims with aging information.
3. WHEN generating a rejection report THEN the System SHALL show rejected claims grouped by rejection reason.
4. WHEN generating a tariff coverage report THEN the System SHALL show percentage of hospital items mapped to NHIS tariffs.
5. WHEN exporting reports THEN the System SHALL allow export to Excel format.

