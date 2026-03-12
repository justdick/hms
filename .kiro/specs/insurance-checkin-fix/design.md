# Insurance Check-in Fix — Bugfix Design

## Overview

Five related insurance bugs affect the check-in workflow: (1) `isPast()` on `coverage_end_date` excludes the expiry day itself, (2) expired insurance warnings lack the actual expiry date, (3) editing patient insurance for the same plan creates duplicates instead of updating, (4) no NHIS extension verification prompt for expired insurance in the check-in modal, and (5) the `ApplyInsuranceToCheckinModal` lacks a pending charge summary before confirming retroactive insurance application. The fix strategy is minimal and targeted — correct the date comparison, enhance UI messages, add upsert logic for insurance records, surface the existing NHIS extension hook for expired insurance, and pass pending charge data to the retroactive modal.

## Glossary

- **Bug_Condition (C)**: The set of conditions that trigger each of the five bugs — expiry-day exclusion, missing expiry date display, duplicate record creation, missing NHIS verification prompt, and missing charge summary
- **Property (P)**: The desired correct behavior — inclusive date comparison, actionable expiry messages, upsert on same-plan insurance, NHIS verify button for expired insurance, and charge summary in retroactive modal
- **Preservation**: Existing behaviors that must remain unchanged — past-date expiry, null-date validity, future-date validity, different-plan creation, valid insurance check-in flow, paid charge protection, event-driven billing
- **`CheckinController::checkInsurance`**: The method in `app/Http/Controllers/Checkin/CheckinController.php` that checks patient insurance status and returns JSON with `is_expired` flag
- **`PatientInsurance::isExpired()`**: The method in `app/Models/PatientInsurance.php` that uses `coverage_end_date->isPast()` to determine expiry
- **`Patient::activeInsurance()`**: The HasOne relationship in `app/Models/Patient.php` that filters by `coverage_end_date >= now()` — already uses inclusive comparison at the query level
- **`PatientController::update`**: The method in `app/Http/Controllers/Patient/PatientController.php` that saves patient data including insurance, currently only checking `activeInsurance` for upsert
- **`InsuranceDialog`**: The React component in `resources/js/components/Checkin/InsuranceDialog.tsx` that handles insurance check-in with CCC verification
- **`ApplyInsuranceToCheckinModal`**: The React component in `resources/js/components/Patient/ApplyInsuranceToCheckinModal.tsx` for retroactive insurance application
- **`useNhisExtension`**: The React hook in `resources/js/hooks/useNhisExtension.ts` that communicates with the NHIS browser extension for membership verification
- **`InsuranceApplicationService`**: The service in `app/Services/InsuranceApplicationService.php` that handles retroactive insurance application to active check-ins

## Bug Details

### Bug Condition

The bugs manifest across five related scenarios in the insurance check-in workflow. The common thread is that insurance handling at check-in time has gaps in date comparison logic, UI feedback, data integrity, renewal workflow, and retroactive application visibility.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type InsuranceCheckInContext
  OUTPUT: boolean

  // Bug 1: Expiry day exclusion
  LET expiryDayBug = input.coverage_end_date IS NOT NULL
                     AND input.coverage_end_date == TODAY
                     AND isPast(input.coverage_end_date) == true

  // Bug 2: Missing expiry date in warning
  LET missingDateBug = input.insurance.is_expired == true
                       AND input.ui_warning DOES NOT CONTAIN formatted(input.coverage_end_date)

  // Bug 3: Duplicate insurance records
  LET duplicateBug = input.action == 'update_patient_insurance'
                     AND EXISTS record IN patient.insurancePlans
                       WHERE record.insurance_plan_id == input.insurance_plan_id
                     AND system CREATES new record instead of UPDATING existing

  // Bug 4: Missing NHIS verify prompt for expired insurance
  LET nhisPromptBug = input.insurance.is_expired == true
                      AND input.insurance.plan.provider.is_nhis == true
                      AND input.nhis_settings.verification_mode == 'extension'
                      AND ui DOES NOT SHOW 'Verify NHIS Membership' button

  // Bug 5: Missing charge summary in retroactive modal
  LET chargeSummaryBug = input.modal == 'ApplyInsuranceToCheckinModal'
                         AND input.checkin.pending_charges_count > 0
                         AND ui DOES NOT SHOW charge count or total amount

  RETURN expiryDayBug OR missingDateBug OR duplicateBug OR nhisPromptBug OR chargeSummaryBug
END FUNCTION
```

### Examples

- **Bug 1**: Patient AGARTHA AFFUL has `coverage_end_date = 2026-03-11`. On 2026-03-11, `coverage_end_date->isPast()` returns `true` because Carbon's `isPast()` compares datetime (the date 2026-03-11 00:00:00 is before the current time 2026-03-11 10:14:00). Expected: insurance valid all day. Actual: insurance marked expired.
- **Bug 2**: When insurance is expired, the UI shows "⚠️ Coverage expired - please renew to use insurance" with no actual date. Expected: "Insurance expired on 2026-03-11 — update insurance dates in patient profile if renewed". Actual: generic message with no date.
- **Bug 3**: Patient has existing `patient_insurance` record for NHIS Plan (id=5, status=active, expired dates). Staff edits patient and submits insurance for the same plan id=5 with new dates. `PatientController::update` checks `$patient->activeInsurance` which returns `null` (because the existing record has expired dates and fails the `coverage_end_date >= now()` filter), so it creates a new record. Result: 5 duplicate records after repeated attempts. Expected: update the existing record for the same plan.
- **Bug 4**: Patient has expired NHIS insurance. Check-in modal shows expired warning but no "Verify NHIS Membership" button, even though the `useNhisExtension` hook is already imported and used for valid insurance verification. The receptionist has no way to check if the patient renewed online without navigating away.
- **Bug 5**: Patient checked in at 10:14 AM without insurance. 6 medications + consultation fee = GHS 680 in pending charges. Insurance added at 1:30 PM. `ApplyInsuranceToCheckinModal` opens but shows no charge summary — receptionist clicks "Apply Insurance" blindly without knowing 7 charges totaling GHS 680 will be affected.

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Past dates (before today) continue to be treated as expired (requirement 3.1)
- Null `coverage_end_date` continues to be treated as valid indefinitely (requirement 3.2)
- Future dates continue to be treated as valid (requirement 3.3)
- Adding insurance for a patient with no existing insurance creates a new record (requirement 3.4)
- Adding insurance for a different plan creates a new record (requirement 3.5)
- `InsuranceService::verifyEligibility` continues using `coverage_end_date >= $date` (requirement 3.6)
- Valid insurance check-in flow works as before (requirement 3.7)
- Paid/settled charges are not affected by retroactive insurance application (requirement 3.8)
- No active check-in means only profile update, no charge re-evaluation (requirement 3.9)
- New charges after insurance applied get coverage via event-driven billing (requirement 3.10)
- Valid NHIS extension verification continues to auto-fill CCC and sync dates (requirement 3.11)
- Manual mode with valid insurance continues to work (requirement 3.12)
- NHIS extension INACTIVE status continues to block insurance (requirement 3.13)
- New insurance with active check-in triggers apply insurance redirect (requirement 3.14)

**Scope:**
All inputs that do NOT involve the five bug conditions should be completely unaffected by this fix. This includes:
- Check-ins for patients with future or null coverage end dates
- Insurance records for different plans (legitimate new records)
- Mouse clicks, form submissions, and navigation unrelated to insurance check-in
- Charge creation, payment processing, and billing flows
- NHIS extension verification for valid (non-expired) insurance

## Hypothesized Root Cause

Based on the bug description and code analysis, the root causes are:

1. **`isPast()` datetime comparison (Bug 1)**: `CheckinController::checkInsurance` (line 524) uses `$activeInsurance->coverage_end_date->isPast()`. Carbon's `isPast()` compares the full datetime — since `coverage_end_date` is cast as `date`, it becomes `2026-03-11 00:00:00`, which is "past" at any point after midnight on that day. The `PatientInsurance::isExpired()` method (line 88) has the same bug. Notably, the `Patient::activeInsurance()` relationship and `PatientInsurance::scopeActive` already use `coverage_end_date >= now()` which works correctly at the SQL level because MySQL compares date-to-datetime inclusively.

2. **Generic expired warning message (Bug 2)**: The `InsuranceDialog.tsx` component (around line 340) renders a hardcoded string `"⚠️ Coverage expired - please renew to use insurance"` without interpolating the actual `coverage_end_date` from the `insurance` prop. The date is available in `insurance.coverage_end_date` but not used in the warning.

3. **`activeInsurance` filter too narrow for upsert (Bug 3)**: `PatientController::update` (line 455) checks `$patient->activeInsurance` to decide whether to update or create. The `activeInsurance()` relationship filters by `status = 'active'` AND `coverage_start_date <= now()` AND `(coverage_end_date IS NULL OR coverage_end_date >= now())`. When the existing record has expired dates, this returns `null`, so the code falls through to `create()`. The fix should look for any existing record with the same `insurance_plan_id` regardless of date/status.

4. **NHIS verify button only shown for non-expired insurance (Bug 4)**: In `InsuranceDialog.tsx`, the "Verify NHIS Membership" button is inside the CCC verification section which is wrapped in a container that gets `opacity-60` and disabled state when `!canUseInsurance` (i.e., when expired). The button exists but is visually disabled and functionally blocked. For expired NHIS insurance, the verify button should be shown prominently in the expired warning section, outside the disabled CCC area.

5. **No charge data passed to modal (Bug 5)**: `PatientController::getActiveCheckinWithoutInsurance` (line 1076) returns only check-in metadata (id, department, status, checked_in_at). It does not query or return pending charge count or total. The `ApplyInsuranceToCheckinModal` component has no charge data to display.

## Correctness Properties

Property 1: Bug Condition — Expiry Day Insurance Validity

_For any_ patient whose `coverage_end_date` equals today's date, the `checkInsurance` endpoint SHALL return `is_expired: false` and the `PatientInsurance::isExpired()` method SHALL return `false`, treating the insurance as valid for the entire expiry day.

**Validates: Requirements 2.1**

Property 2: Bug Condition — Expiry Date Display in Warning

_For any_ expired insurance displayed in the check-in modal, the warning message SHALL include the formatted actual expiry date from `coverage_end_date` and a suggestion to update insurance dates if renewed.

**Validates: Requirements 2.2**

Property 3: Bug Condition — Same-Plan Insurance Upsert

_For any_ patient update that submits insurance data with an `insurance_plan_id` matching an existing `patient_insurance` record for that patient, the system SHALL update the existing record instead of creating a duplicate, regardless of the existing record's status or date validity.

**Validates: Requirements 2.3**

Property 4: Bug Condition — NHIS Extension Verify for Expired Insurance

_For any_ expired NHIS insurance displayed in the check-in modal when `verification_mode = 'extension'`, the system SHALL display a "Verify NHIS Membership" button that triggers the `useNhisExtension` hook, and upon successful verification with active status and new dates, SHALL auto-sync the coverage dates and allow insurance check-in.

**Validates: Requirements 2.4**

Property 5: Bug Condition — Pending Charge Summary in Retroactive Modal

_For any_ active check-in with pending charges displayed in the `ApplyInsuranceToCheckinModal`, the modal SHALL show the count and total amount of pending charges that will have insurance coverage applied before the receptionist confirms.

**Validates: Requirements 2.5**

Property 6: Preservation — Past Date Expiry and Future/Null Date Validity

_For any_ `coverage_end_date` strictly before today, the system SHALL continue to treat insurance as expired. _For any_ `coverage_end_date` in the future or null, the system SHALL continue to treat insurance as valid. _For any_ different `insurance_plan_id`, the system SHALL continue to create a new record.

**Validates: Requirements 3.1, 3.2, 3.3, 3.5**

Property 7: Preservation — Retroactive Application Charge Protection

_For any_ charge with status other than `pending` (e.g., `paid`, `settled`), the `InsuranceApplicationService::applyInsuranceToActiveCheckin` SHALL continue to skip those charges, and _for any_ patient without an active check-in, updating insurance SHALL only update the profile without attempting charge re-evaluation.

**Validates: Requirements 3.8, 3.9**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `app/Http/Controllers/Checkin/CheckinController.php`

**Function**: `checkInsurance`

**Specific Changes**:
1. **Replace `isPast()` with date-only comparison**: Change line 524 from `$activeInsurance->coverage_end_date->isPast()` to `$activeInsurance->coverage_end_date->isBefore(today())`. Since `coverage_end_date` is cast as `date` (midnight), `isBefore(today())` returns `true` only when the date is strictly before today, treating today's date as valid.
2. **Include `coverage_end_date` in the JSON response**: The `coverage_end_date` is already returned (line 536). No change needed — the frontend just needs to use it.

---

**File**: `app/Models/PatientInsurance.php`

**Function**: `isExpired`

**Specific Changes**:
1. **Replace `isPast()` with date-only comparison**: Change `$this->coverage_end_date->isPast()` to `$this->coverage_end_date->isBefore(today())` for consistency with the controller fix.

---

**File**: `resources/js/components/Checkin/InsuranceDialog.tsx`

**Specific Changes**:
1. **Enhance expired warning message**: In the `{!cccData && isExpired && ...}` block (around line 340), replace the generic message with one that includes the formatted `insurance.coverage_end_date` and a suggestion to update.
2. **Add NHIS verify button for expired insurance**: When `isExpired && isNhisProvider && isExtensionMode`, render a "Verify NHIS Membership" button inside the expired warning section (outside the disabled CCC area). This button calls the existing `handleVerifyNhis` function. When verification succeeds and dates are synced, the `isExpired` state will update via the existing `useEffect` that watches `cccData`.
3. **Show manual mode guidance**: When `isExpired && isNhisProvider && !isExtensionMode`, show the expiry date and a clear suggestion to update insurance dates in the patient profile.

---

**File**: `app/Http/Controllers/Patient/PatientController.php`

**Function**: `update`

**Specific Changes**:
1. **Replace `activeInsurance` check with same-plan lookup**: Instead of `$patient->activeInsurance`, query `$patient->insurancePlans()->where('insurance_plan_id', $validated['insurance_plan_id'])->first()` to find any existing record for the same plan regardless of status or dates.
2. **Update the `$insuranceWasAdded` logic**: Set `$insuranceWasAdded = !$existingInsurance` based on the new lookup, so the apply-insurance redirect still triggers correctly when insurance is genuinely new.

---

**File**: `app/Http/Controllers/Patient/PatientController.php`

**Function**: `getActiveCheckinWithoutInsurance`

**Specific Changes**:
1. **Add pending charge count and total**: Query `Charge::where('patient_checkin_id', $checkin->id)->where('status', 'pending')->where('is_insurance_claim', false)` to get count and sum of `amount`. Include `pending_charges_count` and `pending_charges_total` in the returned array.

---

**File**: `resources/js/components/Patient/ApplyInsuranceToCheckinModal.tsx`

**Specific Changes**:
1. **Update `ActiveCheckin` interface**: Add `pending_charges_count?: number` and `pending_charges_total?: number` fields.
2. **Render charge summary**: Before the CCC input section, display a summary block showing the count and formatted total of pending charges (e.g., "7 pending charges totaling GHS 680.00 will have insurance coverage applied").

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bugs on unfixed code, then verify the fixes work correctly and preserve existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bugs BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Write tests that exercise each of the five bug conditions on the unfixed code to observe failures and confirm root causes.

**Test Cases**:
1. **Expiry Day Test**: Create a patient with `coverage_end_date = today`, call `checkInsurance` — expect `is_expired: true` on unfixed code (will fail after fix)
2. **Duplicate Insurance Test**: Create a patient with an expired insurance record for plan X, submit update with same plan X — expect 2 records on unfixed code (will fail after fix)
3. **PatientInsurance::isExpired Test**: Create a `PatientInsurance` with `coverage_end_date = today`, call `isExpired()` — expect `true` on unfixed code (will fail after fix)

**Expected Counterexamples**:
- `checkInsurance` returns `is_expired: true` when `coverage_end_date` equals today
- `PatientController::update` creates a second `patient_insurance` record for the same plan when the existing one is expired
- `PatientInsurance::isExpired()` returns `true` on the expiry day

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed functions produce the expected behavior.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := fixedFunction(input)
  ASSERT expectedBehavior(result)
END FOR
```

Specifically:
- For Bug 1: `checkInsurance` returns `is_expired: false` when `coverage_end_date == today`
- For Bug 3: `PatientController::update` results in exactly 1 record per plan after update

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed functions produce the same result as the original functions.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT originalFunction(input) = fixedFunction(input)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many date values automatically across the input domain (past, future, null)
- It catches edge cases like boundary dates that manual tests might miss
- It provides strong guarantees that date comparison behavior is unchanged for non-expiry-day dates

**Test Plan**: Observe behavior on UNFIXED code first for non-bug inputs, then write property-based tests capturing that behavior.

**Test Cases**:
1. **Past Date Preservation**: Verify `coverage_end_date` before today still returns `is_expired: true` after fix
2. **Future Date Preservation**: Verify `coverage_end_date` after today still returns `is_expired: false` after fix
3. **Null Date Preservation**: Verify null `coverage_end_date` still returns `is_expired: false` after fix
4. **Different Plan Preservation**: Verify adding insurance for a different plan still creates a new record
5. **Paid Charge Preservation**: Verify paid charges are not affected by retroactive insurance application

### Unit Tests

- Test `PatientInsurance::isExpired()` with today, yesterday, tomorrow, and null dates
- Test `checkInsurance` endpoint returns correct `is_expired` for boundary dates
- Test `PatientController::update` upserts same-plan insurance and creates different-plan insurance
- Test `getActiveCheckinWithoutInsurance` returns pending charge count and total

### Property-Based Tests

- Generate random dates across a wide range and verify `isExpired()` returns `true` only for dates strictly before today
- Generate random plan ID combinations and verify upsert-vs-create logic produces correct record counts
- Generate random charge sets with mixed statuses and verify only pending charges are counted in the summary

### Integration Tests

- Test full check-in flow with insurance expiring today — insurance should be applied
- Test patient edit with existing expired insurance for same plan — should update, not duplicate
- Test retroactive insurance application with pending charges — modal should show charge summary
- Test NHIS extension verification for expired insurance — should verify, sync dates, and allow check-in
