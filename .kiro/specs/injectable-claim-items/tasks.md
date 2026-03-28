# Implementation Plan: Injectable Claim Items

## Overview

This plan implements pending-quantity claim items for injectable/infusion prescriptions. The work modifies the existing prescription observer, claim service, billing service, claim controller, and vetting UI to ensure injectable prescriptions appear on insurance claims immediately at prescription time with a pending flag, then get resolved when the pharmacist or vetting officer provides the quantity.

## Tasks

- [x] 1. Database schema and model changes
  - [x] 1.1 Create migration to add `is_pending_quantity` column to `insurance_claim_items` table
    - Add `boolean('is_pending_quantity')->default(false)->after('has_flexible_copay')`
    - _Requirements: 2.1_

  - [x] 1.2 Update `InsuranceClaimItem` model with `is_pending_quantity` field
    - Add `is_pending_quantity` to `$fillable` array
    - Add `'is_pending_quantity' => 'boolean'` to `casts()` method
    - _Requirements: 2.2_

  - [x] 1.3 Write unit test for `is_pending_quantity` model attribute
    - Test that `is_pending_quantity` defaults to `false` on new claim items
    - Test that `is_pending_quantity` is properly cast as boolean
    - _Requirements: 2.1, 2.2_

- [x] 2. InsuranceClaimService — new methods for pending claim items
  - [x] 2.1 Implement `createPendingQuantityClaimItem(InsuranceClaim $claim, Prescription $prescription): InsuranceClaimItem`
    - Create claim item with qty=0, all financial fields at 0, `is_pending_quantity = true`
    - Set `item_type = 'drug'`, `code = drug->drug_code`, description = `"{drug_name} (Pending quantity)"`
    - Set `charge_id = null`, `item_date = prescription->created_at`
    - _Requirements: 1.1, 1.2, 1.4_

  - [x] 2.2 Implement `findPendingClaimItemForPrescription(InsuranceClaim $claim, string $drugCode): ?InsuranceClaimItem`
    - Query `insurance_claim_items` by `insurance_claim_id`, `code` (drug code), and `item_type = 'drug'`
    - Return the matching item or null
    - _Requirements: 3.4_

  - [x] 2.3 Implement `updatePendingClaimItemQuantity(InsuranceClaimItem $item, int $quantity): InsuranceClaimItem`
    - Set quantity, recalculate tariffs via `InsuranceService::calculateCoverage()`
    - Set `is_pending_quantity = false`
    - Call `recalculateClaimTotals()` on the parent claim
    - Do NOT create any billing charge
    - _Requirements: 4.1, 4.2, 4.3_

  - [x] 2.4 Write property test: Pending claim item creation for injectable prescriptions (Property 1)
    - **Property 1: Pending claim item creation for injectable prescriptions**
    - **Validates: Requirements 1.1, 1.2, 1.4**

  - [x] 2.5 Write property test: Vetting officer update resolves pending claim item without charge (Property 6)
    - **Property 6: Vetting officer update resolves pending claim item without charge**
    - **Validates: Requirements 4.1, 4.2**

  - [x] 2.6 Write property test: Claim totals equal sum of item amounts (Property 7)
    - **Property 7: Claim totals equal sum of item amounts**
    - **Validates: Requirements 4.3**

- [ ] 3. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. PrescriptionObserver — create pending claim items for injectables
  - [x] 4.1 Modify `created()` method in `PrescriptionObserver` to handle injectable prescriptions
    - Add branch: when `$prescription->drug_id && $prescription->quantity === null && $prescription->isPrescribed()`, call new `createPendingQuantityClaimItem` logic
    - Look up patient's insurance claim via checkin ID (reuse existing `getPatientCheckinId` helper)
    - If no claim exists, skip silently (Requirement 1.5)
    - Return early — skip charge creation since there's no quantity yet
    - _Requirements: 1.1, 1.2, 1.3, 1.5_

  - [x] 4.2 Write property test: Non-injectable prescriptions follow existing workflow (Property 2)
    - **Property 2: Non-injectable prescriptions follow existing workflow**
    - **Validates: Requirements 1.3, 7.1, 7.2**

  - [x] 4.3 Write unit test for observer edge cases
    - Test observer skips when no insurance claim exists for the check-in
    - Test observer skips for prescriptions with non-null quantity
    - Test observer skips when drug has no drug_code
    - _Requirements: 1.3, 1.5, 7.1, 7.2_

- [x] 5. PharmacyBillingService — detect-and-link pattern for pending claim items
  - [x] 5.1 Modify `linkChargeToInsuranceClaim()` in `PharmacyBillingService`
    - Before calling `addChargesToClaim`, check for existing pending claim item via `findPendingClaimItemForPrescription`
    - If found with `is_pending_quantity = true`: update quantity, tariffs, link charge_id, set `is_pending_quantity = false`
    - If found with `is_pending_quantity = false` (vetting officer already resolved): link `charge_id` only, no financial overwrite
    - If not found: fall through to existing `addChargesToClaim` logic
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 5.1_

  - [x] 5.2 Write property test: Pharmacist review resolves pending claim item (Property 3)
    - **Property 3: Pharmacist review resolves pending claim item**
    - **Validates: Requirements 3.1, 3.3**

  - [x] 5.3 Write property test: Vetting officer priority — charge links without financial overwrite (Property 4)
    - **Property 4: Vetting officer priority — charge links without financial overwrite**
    - **Validates: Requirements 3.2, 5.1**

  - [x] 5.4 Write property test: No duplicate claim items after pharmacist review (Property 5)
    - **Property 5: No duplicate claim items after pharmacist review**
    - **Validates: Requirements 3.4**

- [ ] 6. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. ClaimItem controller — handle pending quantity resolution on PATCH
  - [x] 7.1 Modify `updateItem()` in `InsuranceClaimController` to handle pending quantity resolution
    - When updating a claim item with `is_pending_quantity = true` and a `quantity` is provided, delegate to `InsuranceClaimService::updatePendingClaimItemQuantity()`
    - Validate quantity >= 1 for pending items
    - Return updated item data including `is_pending_quantity` status in JSON response
    - _Requirements: 4.1, 4.2, 4.3, 5.2_

  - [ ] 7.2 Write property test: Vetting officer overrides pharmacist quantity (Property 8)
    - **Property 8: Vetting officer overrides pharmacist quantity**
    - **Validates: Requirements 5.2**

  - [ ] 7.3 Write unit test for controller validation
    - Test that quantity of 0 is rejected for pending items
    - Test that negative quantity is rejected
    - Test that non-pending items still update via existing logic
    - _Requirements: 4.1, 5.2_

- [x] 8. Frontend — vetting UI highlights and editable pending items
  - [x] 8.1 Add `is_pending_quantity` to `ClaimItem` TypeScript interface in `types.ts`
    - Add `is_pending_quantity: boolean` field to the `ClaimItem` interface
    - _Requirements: 6.1_

  - [x] 8.2 Update `ClaimItemsTabs.tsx` to highlight pending-quantity items
    - Add amber/yellow background to rows where `is_pending_quantity = true`
    - Show a "Pending Qty" badge next to the item description for pending items
    - Ensure quantity field is editable for pending items (already editable in non-disabled mode)
    - On quantity save for a pending item, the existing PATCH endpoint handles resolution
    - After successful save, update local state to set `is_pending_quantity = false` and remove highlight
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

  - [x] 8.3 Update `getVettingData()` in `InsuranceClaimController` to include `is_pending_quantity` in claim item data
    - Ensure the `is_pending_quantity` field is returned in the vetting data response for each claim item
    - _Requirements: 6.1_

- [ ] 9. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass here, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- The existing `updateItem` PATCH endpoint at `/admin/insurance/claims/{claim}/items/{item}` is enhanced rather than creating a new endpoint
- The `DispensingService` itself does not need direct modification — the pending claim item detection happens in `PharmacyBillingService::linkChargeToInsuranceClaim()` which is already called during the dispensing review flow
