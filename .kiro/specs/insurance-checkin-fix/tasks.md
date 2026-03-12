# Implementation Plan

- [x] 1. Write bug condition exploration tests
  - **Property 1: Bug Condition** - Insurance Expiry Day Exclusion, Duplicate Records, and Missing Charge Summary
  - **CRITICAL**: These tests MUST FAIL on unfixed code — failure confirms the bugs exist
  - **DO NOT attempt to fix the tests or the code when they fail**
  - **NOTE**: These tests encode the expected behavior — they will validate the fixes when they pass after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bugs exist
  - **Scoped PBT Approach**: Scope properties to concrete failing cases for reproducibility
  - Test 1a — Expiry Day: Create a `PatientInsurance` with `coverage_end_date = today()`, assert `isExpired()` returns `false` (Bug Condition: `coverage_end_date == TODAY AND isPast(coverage_end_date) == true`)
  - Test 1b — CheckInsurance Endpoint: Create patient with active insurance where `coverage_end_date = today()`, call `checkInsurance` endpoint, assert response JSON `is_expired` is `false` (Bug Condition: controller uses `isPast()` on expiry day)
  - Test 1c — Duplicate Insurance: Create patient with an expired `PatientInsurance` record for plan X (`coverage_end_date` in the past), submit `PatientController::update` with same `insurance_plan_id`, assert patient has exactly 1 insurance record for that plan (Bug Condition: `activeInsurance` returns null for expired record, so system creates duplicate)
  - Test 1d — Charge Summary: Create patient with active check-in and pending charges, call `getActiveCheckinWithoutInsurance`, assert response contains `pending_charges_count` and `pending_charges_total` keys with correct values (Bug Condition: endpoint does not return charge data)
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests FAIL (this is correct — it proves the bugs exist)
  - Document counterexamples found to understand root causes
  - Mark task complete when tests are written, run, and failures are documented
  - _Requirements: 1.1, 1.3, 1.5_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Date Comparison, Different-Plan Creation, and Paid Charge Protection
  - **IMPORTANT**: Follow observation-first methodology
  - Observe on UNFIXED code: `PatientInsurance::isExpired()` returns `true` for `coverage_end_date = yesterday`, returns `false` for `coverage_end_date = tomorrow`, returns `false` for `coverage_end_date = null`
  - Observe on UNFIXED code: Adding insurance for a different `insurance_plan_id` creates a new record
  - Observe on UNFIXED code: `InsuranceApplicationService::applyInsuranceToActiveCheckin` skips charges with status != 'pending'
  - Observe on UNFIXED code: Patient with no active check-in — updating insurance only updates profile, no charge re-evaluation
  - Test 2a — Past Date Expiry: For any `coverage_end_date` strictly before today, `isExpired()` returns `true` and `checkInsurance` returns `is_expired: true` (Preservation: requirement 3.1)
  - Test 2b — Future Date Validity: For any `coverage_end_date` after today, `isExpired()` returns `false` and `checkInsurance` returns `is_expired: false` (Preservation: requirement 3.3)
  - Test 2c — Null Date Validity: When `coverage_end_date` is null, `isExpired()` returns `false` (Preservation: requirement 3.2)
  - Test 2d — Different Plan Creates New Record: When patient has insurance for plan A and user adds insurance for plan B, a new record is created (Preservation: requirement 3.5)
  - Test 2e — New Insurance Creates Record: When patient has no insurance and user adds insurance, a new record is created (Preservation: requirement 3.4)
  - Test 2f — Paid Charges Unchanged: When `applyInsuranceToActiveCheckin` runs, charges with status 'paid' or 'settled' are not modified (Preservation: requirement 3.8)
  - Test 2g — No Active Checkin: When patient has no active check-in, updating insurance only updates profile (Preservation: requirement 3.9)
  - Verify all tests PASS on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.8, 3.9_

- [x] 3. Fix Bug 1 — Expiry day date comparison (`isPast()` → `isBefore(today())`)

  - [x] 3.1 Fix `CheckinController::checkInsurance` date comparison
    - In `app/Http/Controllers/Checkin/CheckinController.php`, change `$activeInsurance->coverage_end_date->isPast()` to `$activeInsurance->coverage_end_date->isBefore(today())`
    - This ensures `coverage_end_date == today` is treated as valid (not expired) for the entire day
    - _Bug_Condition: isBugCondition(input) where input.coverage_end_date == TODAY AND isPast(coverage_end_date) == true_
    - _Expected_Behavior: checkInsurance returns is_expired: false when coverage_end_date == today_
    - _Preservation: Past dates still expired, future/null dates still valid_
    - _Requirements: 2.1, 3.1, 3.2, 3.3_

  - [x] 3.2 Fix `PatientInsurance::isExpired()` date comparison
    - In `app/Models/PatientInsurance.php`, change `$this->coverage_end_date->isPast()` to `$this->coverage_end_date->isBefore(today())`
    - Consistent with the controller fix — both use date-only comparison
    - _Bug_Condition: isExpired() returns true on expiry day_
    - _Expected_Behavior: isExpired() returns false when coverage_end_date == today_
    - _Preservation: Past dates still expired, future/null dates still valid_
    - _Requirements: 2.1, 3.1, 3.2, 3.3_

  - [x] 3.3 Verify bug condition exploration tests for Bug 1 now pass
    - **Property 1: Expected Behavior** - Expiry Day Insurance Validity
    - **IMPORTANT**: Re-run the SAME tests 1a and 1b from task 1 — do NOT write new tests
    - Tests 1a and 1b encode the expected behavior for Bug 1
    - When these tests pass, it confirms the expiry day bug is fixed
    - **EXPECTED OUTCOME**: Tests 1a and 1b PASS (confirms bug is fixed)
    - _Requirements: 2.1_

  - [x] 3.4 Verify preservation tests still pass after Bug 1 fix
    - **Property 2: Preservation** - Date Comparison Preservation
    - **IMPORTANT**: Re-run the SAME tests 2a, 2b, 2c from task 2 — do NOT write new tests
    - **EXPECTED OUTCOME**: Tests 2a, 2b, 2c PASS (confirms no regressions in date comparison)
    - _Requirements: 3.1, 3.2, 3.3_

- [x] 4. Fix Bug 2 — Enhanced expired warning with actual expiry date

  - [x] 4.1 Update expired insurance warning in `InsuranceDialog.tsx`
    - In `resources/js/components/Checkin/InsuranceDialog.tsx`, replace the generic "Coverage expired" message with one that includes the formatted `insurance.coverage_end_date`
    - Show: "Insurance expired on {formatted date} — update insurance dates in patient profile if renewed"
    - _Bug_Condition: expired insurance warning does not contain formatted coverage_end_date_
    - _Expected_Behavior: warning includes actual expiry date and update suggestion_
    - _Requirements: 2.2_

- [x] 5. Fix Bug 3 — Duplicate insurance records (upsert by `insurance_plan_id`)

  - [x] 5.1 Update `PatientController::update` to upsert by `insurance_plan_id`
    - In `app/Http/Controllers/Patient/PatientController.php`, replace `$patient->activeInsurance` check with `$patient->insurancePlans()->where('insurance_plan_id', $validated['insurance_plan_id'])->first()`
    - This finds any existing record for the same plan regardless of status or date validity
    - Update the existing record if found, create new only if no record exists for that plan
    - Update `$insuranceWasAdded` logic: set to `true` only when a genuinely new record is created
    - _Bug_Condition: activeInsurance returns null for expired same-plan record, causing duplicate creation_
    - _Expected_Behavior: exactly 1 record per plan after update, existing record updated_
    - _Preservation: Different plan IDs still create new records, no-insurance patients still get new records_
    - _Requirements: 2.3, 3.4, 3.5_

  - [x] 5.2 Verify bug condition exploration test for Bug 3 now passes
    - **Property 1: Expected Behavior** - Same-Plan Insurance Upsert
    - **IMPORTANT**: Re-run the SAME test 1c from task 1 — do NOT write a new test
    - When test 1c passes, it confirms duplicate insurance bug is fixed
    - **EXPECTED OUTCOME**: Test 1c PASSES (confirms bug is fixed)
    - _Requirements: 2.3_

  - [x] 5.3 Verify preservation tests still pass after Bug 3 fix
    - **Property 2: Preservation** - Insurance Record Creation Preservation
    - **IMPORTANT**: Re-run the SAME tests 2d, 2e from task 2 — do NOT write new tests
    - **EXPECTED OUTCOME**: Tests 2d, 2e PASS (confirms different-plan and new-insurance creation still works)
    - _Requirements: 3.4, 3.5_

- [x] 6. Fix Bug 4 — NHIS verify button for expired insurance

  - [x] 6.1 Add NHIS verify button to expired insurance warning in `InsuranceDialog.tsx`
    - In `resources/js/components/Checkin/InsuranceDialog.tsx`, when `isExpired && isNhisProvider && isExtensionMode`, render a "Verify NHIS Membership" button inside the expired warning section
    - Button calls the existing `handleVerifyNhis` function from `useNhisExtension` hook
    - When verification succeeds with active status and new dates, auto-sync via existing `syncInsuranceDates` flow
    - When `isExpired && isNhisProvider && !isExtensionMode` (manual mode), show expiry date and suggestion to update in patient profile
    - _Bug_Condition: expired NHIS insurance in extension mode shows no verify button_
    - _Expected_Behavior: verify button shown, triggers useNhisExtension, syncs dates on success_
    - _Requirements: 2.4_

- [x] 7. Fix Bug 5 — Pending charge summary in `ApplyInsuranceToCheckinModal`

  - [x] 7.1 Add pending charge data to `getActiveCheckinWithoutInsurance` response
    - In `app/Http/Controllers/Patient/PatientController.php`, query `Charge::where('patient_checkin_id', $checkin->id)->where('status', 'pending')->where('is_insurance_claim', false)` to get count and sum of `amount`
    - Include `pending_charges_count` and `pending_charges_total` in the returned array
    - _Bug_Condition: endpoint does not return charge count or total_
    - _Expected_Behavior: response includes pending_charges_count and pending_charges_total_
    - _Requirements: 2.5_

  - [x] 7.2 Display charge summary in `ApplyInsuranceToCheckinModal`
    - In `resources/js/components/Patient/ApplyInsuranceToCheckinModal.tsx`, update `ActiveCheckin` interface to add `pending_charges_count?: number` and `pending_charges_total?: number`
    - Render a summary block before the CCC input section: "X pending charges totaling GHS Y will have insurance coverage applied"
    - Only show summary when `pending_charges_count > 0`
    - _Bug_Condition: modal shows no charge information before confirmation_
    - _Expected_Behavior: modal displays charge count and total before receptionist confirms_
    - _Requirements: 2.5_

  - [x] 7.3 Verify bug condition exploration test for Bug 5 now passes
    - **Property 1: Expected Behavior** - Pending Charge Summary
    - **IMPORTANT**: Re-run the SAME test 1d from task 1 — do NOT write a new test
    - When test 1d passes, it confirms charge summary data is now returned
    - **EXPECTED OUTCOME**: Test 1d PASSES (confirms bug is fixed)
    - _Requirements: 2.5_

  - [x] 7.4 Verify preservation tests still pass after Bug 5 fix
    - **Property 2: Preservation** - Charge Protection and No-Checkin Preservation
    - **IMPORTANT**: Re-run the SAME tests 2f, 2g from task 2 — do NOT write new tests
    - **EXPECTED OUTCOME**: Tests 2f, 2g PASS (confirms paid charges unchanged and no-checkin behavior preserved)
    - _Requirements: 3.8, 3.9_

- [ ] 8. Checkpoint — Ensure all tests pass
  - Run the full test suite to confirm all exploration tests (task 1) now pass after fixes
  - Run the full test suite to confirm all preservation tests (task 2) still pass after fixes
  - Verify no regressions in existing test suite
  - Ask the user if questions arise
