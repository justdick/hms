# Requirements Document

## Introduction

Injectable and infusion prescriptions (where the doctor does not set a quantity at prescription time) currently do not appear on insurance claims until the pharmacist reviews and enters the quantity. For NHIS auditing compliance, ALL prescribed medications must appear on claims immediately at prescription time, even before the pharmacist determines the quantity. This feature ensures injectable/infusion claim items are created at prescription time with zero quantity and a pending flag, then updated when the pharmacist or vetting officer provides the actual quantity.

## Glossary

- **Prescription_Observer**: The Laravel observer (`PrescriptionObserver`) that handles Prescription model lifecycle events and triggers charge/claim creation.
- **Insurance_Claim_Service**: The service class (`InsuranceClaimService`) responsible for creating, updating, and managing insurance claim items and claim totals.
- **Dispensing_Service**: The service class (`DispensingService`) that handles pharmacy prescription review and dispensing workflows.
- **Pharmacy_Billing_Service**: The service class (`PharmacyBillingService`) that creates and manages charges for prescriptions and links them to insurance claims.
- **Claim_Item**: A line item on an insurance claim (`InsuranceClaimItem`) representing a single service, drug, or procedure billed to insurance.
- **Pending_Quantity_Claim_Item**: A Claim_Item created with `is_pending_quantity = true`, quantity of 0, and zero financial amounts, awaiting quantity determination.
- **Injectable_Prescription**: A prescription for an injectable or infusion drug where the doctor does not set a quantity at prescription time (quantity is null).
- **Vetting_Officer**: A user with the `insurance.vet-claims` permission who reviews and approves claim items before submission to NHIS.
- **Vetting_UI**: The frontend interface used by the Vetting_Officer to review, approve, and modify insurance claim items.

## Requirements

### Requirement 1: Create Pending-Quantity Claim Items at Prescription Time

**User Story:** As an NHIS auditor, I want all prescribed medications to appear on insurance claims immediately at prescription time, so that no prescribed items are missing from claim submissions.

#### Acceptance Criteria

1. WHEN a doctor creates an Injectable_Prescription (quantity is null) for an insured patient, THE Prescription_Observer SHALL create a Pending_Quantity_Claim_Item on the patient's insurance claim with quantity = 0, insurance_pays = 0, patient_pays = 0, subtotal = 0, and `is_pending_quantity = true`.
2. WHEN a doctor creates an Injectable_Prescription (quantity is null) for an insured patient, THE Prescription_Observer SHALL NOT create a billing charge for the prescription.
3. WHEN a doctor creates a non-injectable prescription (quantity is not null), THE Prescription_Observer SHALL continue to follow the existing charge and claim item creation logic without modification.
4. THE Pending_Quantity_Claim_Item SHALL store the drug code, drug name, item_type as "drug", and the prescription's item_date, so that the Claim_Item is identifiable on the claim.
5. IF no insurance claim exists for the patient's current check-in, THEN THE Prescription_Observer SHALL skip Pending_Quantity_Claim_Item creation without raising an error.

### Requirement 2: Database Schema for Pending Quantity Flag

**User Story:** As a developer, I want a boolean flag on claim items to distinguish pending-quantity items from fully resolved ones, so that the system can track which items still need quantity determination.

#### Acceptance Criteria

1. THE `insurance_claim_items` table SHALL have a boolean column `is_pending_quantity` that defaults to `false`.
2. THE Claim_Item model SHALL include `is_pending_quantity` in its fillable attributes and cast it as a boolean.

### Requirement 3: Pharmacist Dispensing Updates Pending Claim Items

**User Story:** As a pharmacist, I want the claim item to be updated with the actual quantity when I review and keep an injectable prescription, so that the insurance claim reflects the dispensed amount.

#### Acceptance Criteria

1. WHEN the pharmacist reviews an Injectable_Prescription with action "keep" and enters a quantity, AND the matching Claim_Item has `is_pending_quantity = true`, THE Dispensing_Service SHALL update the Claim_Item with the actual quantity, recalculate tariffs (unit_tariff, subtotal, insurance_pays, patient_pays), link the charge_id, and set `is_pending_quantity = false`.
2. WHEN the pharmacist reviews an Injectable_Prescription with action "keep" and enters a quantity, AND the matching Claim_Item has `is_pending_quantity = false` (vetting officer already updated it), THE Pharmacy_Billing_Service SHALL link the charge_id to the existing Claim_Item without overwriting the quantity or financial amounts.
3. WHEN the pharmacist reviews an Injectable_Prescription with action "keep", THE Dispensing_Service SHALL create a billing charge as it does currently, regardless of the Claim_Item's pending status.
4. WHEN the pharmacist reviews an Injectable_Prescription with action "keep" and a charge is created, THE Pharmacy_Billing_Service SHALL detect the existing Pending_Quantity_Claim_Item by matching the drug code and claim, and link the charge to it instead of creating a duplicate Claim_Item.

### Requirement 4: Vetting Officer Manual Quantity Update

**User Story:** As a vetting officer, I want to manually set the quantity on pending-quantity claim items, so that I can prepare claims for NHIS submission without waiting for the pharmacist to dispense.

#### Acceptance Criteria

1. WHEN the Vetting_Officer updates the quantity on a Pending_Quantity_Claim_Item, THE Insurance_Claim_Service SHALL update the Claim_Item quantity, recalculate tariffs (unit_tariff, subtotal, insurance_pays, patient_pays), and set `is_pending_quantity = false`.
2. WHEN the Vetting_Officer updates the quantity on a Pending_Quantity_Claim_Item, THE Insurance_Claim_Service SHALL NOT create any billing charge.
3. WHEN the Vetting_Officer updates the quantity on a Claim_Item, THE Insurance_Claim_Service SHALL recalculate the parent claim totals (total_claim_amount, insurance_covered_amount, patient_copay_amount).

### Requirement 5: Priority Logic Between Vetting Officer and Pharmacist

**User Story:** As a system administrator, I want the vetting officer's manual quantity to take priority over the pharmacist's dispensing quantity on claim items, so that NHIS claim accuracy is maintained by the vetting officer.

#### Acceptance Criteria

1. WHEN the Vetting_Officer has already set the quantity on a Claim_Item (`is_pending_quantity = false`), AND the pharmacist subsequently dispenses the same prescription, THE Pharmacy_Billing_Service SHALL link the charge_id to the Claim_Item without modifying the quantity, unit_tariff, subtotal, insurance_pays, or patient_pays.
2. WHEN the pharmacist dispenses first (`is_pending_quantity = false` after pharmacist update), AND the Vetting_Officer subsequently updates the quantity, THE Insurance_Claim_Service SHALL overwrite the quantity and recalculate tariffs, because the Vetting_Officer has final authority on claim item amounts.

### Requirement 6: Vetting UI Highlights Pending-Quantity Items

**User Story:** As a vetting officer, I want pending-quantity claim items to be visually highlighted in the vetting interface, so that I can quickly identify which items need my attention for quantity entry.

#### Acceptance Criteria

1. WHILE a Claim_Item has `is_pending_quantity = true`, THE Vetting_UI SHALL display the Claim_Item row with a yellow/amber background highlight to distinguish it from resolved items.
2. WHILE a Claim_Item has `is_pending_quantity = true`, THE Vetting_UI SHALL display a "Pending Qty" badge or label next to the item description.
3. WHEN the Vetting_Officer clicks on a Pending_Quantity_Claim_Item, THE Vetting_UI SHALL present an editable quantity input field allowing the Vetting_Officer to enter the quantity.
4. WHEN the Vetting_Officer submits a quantity update for a Pending_Quantity_Claim_Item, THE Vetting_UI SHALL refresh the Claim_Item row to show the updated quantity, recalculated tariffs, and remove the pending highlight.

### Requirement 7: Scope Limitation to Injectable/Infusion Prescriptions

**User Story:** As a developer, I want this feature to apply only to prescriptions where quantity is null at creation time, so that existing oral medication workflows remain unaffected.

#### Acceptance Criteria

1. THE Prescription_Observer SHALL only create Pending_Quantity_Claim_Items for prescriptions where quantity is null at creation time.
2. WHEN a prescription is created with a non-null quantity (oral medications such as tablets, syrups, suspensions), THE Prescription_Observer SHALL follow the existing workflow without any changes.
3. THE Dispensing_Service SHALL only check for existing Pending_Quantity_Claim_Items during the "keep" review action when the original prescription had a null quantity.
