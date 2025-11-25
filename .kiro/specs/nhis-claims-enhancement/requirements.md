# Requirements Document

## Introduction

This feature enhances the insurance claims vetting system to support NHIS (National Health Insurance Scheme) claims with G-DRG (Ghana Diagnosis Related Groups) tariff selection, while maintaining flexibility for other insurance providers that use standard coverage-based claims. The vetting interface uses a modal-based approach for efficient workflow without page navigation.

## Glossary

- **G-DRG (Ghana Diagnosis Related Groups)**: A classification system used by NHIS to categorize patient cases into groups with similar clinical characteristics and resource consumption. Each G-DRG code has an associated tariff price.
- **NHIS (National Health Insurance Scheme)**: Ghana's national health insurance provider with specific claim submission requirements including G-DRG codes.
- **Claim Vetting**: The process of reviewing and validating insurance claims before submission to the insurance provider.
- **Claims Officer**: A user responsible for reviewing and approving insurance claims.
- **Tariff**: The price associated with a service or G-DRG code for insurance billing purposes.
- **MDC (Major Diagnostic Category)**: A broad classification of diagnoses (e.g., Out Patient, In Patient).

## Requirements

### Requirement 1: G-DRG Tariff Management

**User Story:** As an administrator, I want to manage G-DRG tariff codes and prices, so that claims officers can select appropriate tariffs during NHIS claim vetting.

#### Acceptance Criteria

1. WHEN an administrator accesses the G-DRG tariff management page THEN the System SHALL display a list of all G-DRG codes with their names, codes, MDC category, and tariff prices.
2. WHEN an administrator creates a new G-DRG tariff THEN the System SHALL require a unique code, name, MDC category, and tariff price in GHS.
3. WHEN an administrator imports G-DRG tariffs from a file THEN the System SHALL validate the data format and create or update tariff records accordingly.
4. WHEN an administrator updates a G-DRG tariff price THEN the System SHALL save the new price without affecting previously vetted claims.
5. WHEN an administrator searches for a G-DRG tariff THEN the System SHALL filter results by code or name.

### Requirement 2: Insurance Provider G-DRG Configuration

**User Story:** As an administrator, I want to configure which insurance providers require G-DRG selection, so that the system displays the appropriate vetting interface for each provider.

#### Acceptance Criteria

1. WHEN an administrator edits an insurance provider THEN the System SHALL display a "Requires G-DRG" toggle option.
2. WHEN the "Requires G-DRG" option is enabled for a provider THEN the System SHALL display the G-DRG vetting modal for claims from that provider.
3. WHEN the "Requires G-DRG" option is disabled for a provider THEN the System SHALL use the standard coverage-based vetting interface.

### Requirement 3: Modal-Based Claim Vetting Interface

**User Story:** As a claims officer, I want to vet claims in a modal overlay, so that I can efficiently review claims without navigating away from the claims list.

#### Acceptance Criteria

1. WHEN a claims officer clicks "Click to Vet" on a claim row THEN the System SHALL open a modal overlay displaying the claim details.
2. WHEN the vetting modal opens THEN the System SHALL display patient information including surname, other names, date of birth, gender, folder ID, and membership ID.
3. WHEN the vetting modal opens THEN the System SHALL display attendance details including type of attendance, date of attendance, date of discharge, type of service, and service outcome.
4. WHEN the vetting modal opens THEN the System SHALL display claim metadata including specialty attended, attending prescriber, dependant status, pharmacy included flag, HIN, and claim check code.
5. WHEN the claims officer closes the modal without approving THEN the System SHALL return to the claims list without saving changes.

### Requirement 4: G-DRG Selection for NHIS Claims

**User Story:** As a claims officer, I want to select a G-DRG code for NHIS claims, so that the claim is properly categorized for NHIS submission.

#### Acceptance Criteria

1. WHEN the vetting modal opens for an NHIS claim THEN the System SHALL display a searchable G-DRG dropdown field.
2. WHEN the claims officer types in the G-DRG field THEN the System SHALL filter available G-DRG options by name or code.
3. WHEN displaying G-DRG options THEN the System SHALL format each option as "Name (Code - GHS Price)" (e.g., "General OPD - Adult (OPDC06A - GHS 55.06)").
4. WHEN a G-DRG is selected THEN the System SHALL update the claim total calculation to include the G-DRG tariff price.
5. WHEN no G-DRG is selected for an NHIS claim THEN the System SHALL prevent claim approval.

### Requirement 5: Claim Diagnoses Management

**User Story:** As a claims officer, I want to add or remove diagnoses on a claim, so that I can ensure the claim accurately reflects the conditions treated without modifying the original consultation record.

#### Acceptance Criteria

1. WHEN the vetting modal opens THEN the System SHALL display all diagnoses from the original consultation as pre-populated entries.
2. WHEN the claims officer adds a diagnosis to the claim THEN the System SHALL save the diagnosis to the claim record only, not the original consultation.
3. WHEN the claims officer removes a diagnosis from the claim THEN the System SHALL remove the diagnosis from the claim record only, not the original consultation.
4. WHEN displaying diagnoses THEN the System SHALL show the diagnosis name and ICD-10 code.
5. WHEN the claims officer searches for a diagnosis to add THEN the System SHALL filter available diagnoses by name or ICD-10 code.

### Requirement 6: Claim Items Display (Investigations, Prescriptions, Procedures)

**User Story:** As a claims officer, I want to view all investigations, prescriptions, and procedures associated with a claim, so that I can verify the services provided before approving the claim.

#### Acceptance Criteria

1. WHEN the vetting modal opens THEN the System SHALL display tabs for Investigations, Prescriptions, and Procedures.
2. WHEN displaying investigations THEN the System SHALL pull lab orders from the consultation and any associated ward rounds for admitted patients.
3. WHEN displaying prescriptions THEN the System SHALL pull prescriptions from the consultation and any associated ward rounds for admitted patients.
4. WHEN displaying procedures THEN the System SHALL pull procedures from the consultation and any associated ward rounds for admitted patients.
5. WHEN displaying claim items THEN the System SHALL show the item name, quantity (where applicable), and insurance tariff price.
6. WHEN a patient was admitted THEN the System SHALL aggregate items from the initial consultation and all ward round records.

### Requirement 7: Claim Total Calculation

**User Story:** As a claims officer, I want to see the calculated claim total, so that I can verify the amount before approving the claim.

#### Acceptance Criteria

1. WHEN displaying an NHIS claim THEN the System SHALL calculate the grand total as: G-DRG tariff + Investigations total + Prescriptions total + Procedures total.
2. WHEN displaying a non-NHIS claim THEN the System SHALL calculate the grand total based on the insurance provider's coverage rules (percentage or fixed amounts).
3. WHEN the G-DRG selection changes THEN the System SHALL recalculate and display the updated grand total.
4. WHEN displaying the grand total THEN the System SHALL format the amount as "GRAND TOTAL: GHS [amount]".

### Requirement 8: Claim Approval

**User Story:** As a claims officer, I want to approve a vetted claim, so that the claim is ready for submission to the insurance provider.

#### Acceptance Criteria

1. WHEN the claims officer clicks "Approve Claim" THEN the System SHALL validate that all required fields are completed.
2. WHEN an NHIS claim is approved without a G-DRG selection THEN the System SHALL display an error message and prevent approval.
3. WHEN a claim is successfully approved THEN the System SHALL update the claim status to "vetted".
4. WHEN a claim is successfully approved THEN the System SHALL record the approving user and approval timestamp.
5. WHEN a claim is successfully approved THEN the System SHALL close the modal and refresh the claims list.

### Requirement 9: Vetted Claims Export

**User Story:** As a claims officer, I want to export vetted claims to XML and Excel formats, so that I can submit claims to NHIS and maintain records.

#### Acceptance Criteria

1. WHEN the claims officer accesses the export function THEN the System SHALL display date range filter options.
2. WHEN the claims officer selects a date range and export format THEN the System SHALL generate a file containing all vetted claims within that range.
3. WHEN exporting to XML THEN the System SHALL format the data according to NHIS submission requirements.
4. WHEN exporting to Excel THEN the System SHALL include all claim details in a tabular format with appropriate column headers.
5. WHEN no vetted claims exist in the selected date range THEN the System SHALL display a message indicating no claims to export.

### Requirement 10: Standard Insurance Claims (Non-NHIS)

**User Story:** As a claims officer, I want to vet claims for non-NHIS insurance providers using their specific coverage rules, so that claims are processed according to each provider's requirements.

#### Acceptance Criteria

1. WHEN the vetting modal opens for a non-NHIS claim THEN the System SHALL hide the G-DRG selection field.
2. WHEN calculating coverage for a non-NHIS claim THEN the System SHALL apply the insurance provider's coverage rules (percentage, fixed amount, or category-based).
3. WHEN displaying a non-NHIS claim total THEN the System SHALL show the covered amount based on the provider's tariffs and coverage settings.
