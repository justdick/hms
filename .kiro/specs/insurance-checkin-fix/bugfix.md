# Bugfix Requirements Document

## Introduction

Multiple related insurance bugs affect the check-in workflow, causing insurance to not be applied to patient charges even when coverage is valid. The root issues are: (1) the `isPast()` check on `coverage_end_date` treats the expiry date itself as expired, (2) the check-in modal shows expired insurance but the message is not actionable enough for receptionists, (3) editing a patient's insurance creates duplicate records instead of updating existing ones, (4) there is no clear path for receptionists to renew expired insurance records, and (5) the retroactive insurance application mechanism exists but the `ApplyInsuranceToCheckinModal` lacks a pending charge summary, giving the receptionist no visibility into what charges will be affected before confirming. These bugs were discovered when patient AGARTHA AFFUL (0803/2026) was checked in on her coverage end date (2026-03-11) and insurance was not applied, then 5 duplicate insurance records were created when staff tried to fix it. Additionally, the same patient was checked in at 10:14 AM without insurance applied, charges were created as cash (consultation fee + 6 medications totaling GHS 680), and insurance was added to her profile at 1:30 PM — the retroactive insurance application mechanism exists (`InsuranceApplicationService`, `ApplyInsuranceToCheckinModal`) but the modal lacks a charge summary, giving the receptionist no visibility into what charges will be affected before confirming.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a patient's `coverage_end_date` equals today's date THEN the `CheckinController::checkInsurance` method marks the insurance as expired via `coverage_end_date->isPast()`, which returns `true` at any point during the expiry day, preventing insurance from being used for that day's check-in.

1.2 WHEN a patient's insurance is expired and the check-in modal displays the insurance status THEN the system shows a generic "Insurance expired - cash payment only" message without displaying the actual expiry date prominently or suggesting the receptionist update the insurance dates if the patient has renewed externally.

1.3 WHEN a user edits a patient and submits insurance data for a plan that already has an existing `patient_insurance` record (active or expired) THEN the `PatientController::update` method creates a new `PatientInsurance` record instead of detecting and updating the existing record for the same plan, resulting in duplicate insurance entries.

1.4 WHEN a patient has expired NHIS insurance and the check-in modal displays the expired status THEN the system does not prompt the receptionist to use the existing NHIS browser extension verification (`useNhisExtension` hook) to check if the patient has renewed their membership online. The expired insurance warning ("Coverage expired - please renew to use insurance") provides no actionable path, even though the system already has an NHIS extension integration (used in `InsuranceDialog.tsx`, `ApplyInsuranceToCheckinModal.tsx`, `Patients/Edit.tsx`, and `RegistrationForm.tsx`) that can verify membership against the NHIA portal, retrieve updated coverage dates, and auto-sync them via the `syncInsuranceDates` function. The receptionist has to manually figure out what to do or navigate away to edit the patient profile.

1.5 WHEN a patient is checked in without insurance (card not available, insurance expired, or not on file) and charges are created as cash/non-insured, and then insurance is subsequently added or updated on the patient's profile while the check-in is still active THEN the retroactive insurance application mechanism exists (`InsuranceApplicationService::applyInsuranceToActiveCheckin`, `ApplyInsuranceToCheckinModal`, `CheckinController::applyInsurance`) but has a gap — the `ApplyInsuranceToCheckinModal` does not show a summary of the pending charges (count and total amount) before the receptionist clicks "Apply Insurance", giving the receptionist no visibility into what charges will be affected. Additionally, the modal only appears on the Patient Show page, so the receptionist must navigate there to trigger it.

### Expected Behavior (Correct)

2.1 WHEN a patient's `coverage_end_date` equals today's date THEN the system SHALL treat the insurance as valid for the entire day, using a date-only comparison (`coverage_end_date >= today`) instead of `isPast()` so that coverage ending today is still usable for today's check-ins.

2.2 WHEN a patient's insurance is expired and the check-in modal displays the insurance status THEN the system SHALL show a clear message including the actual expiry date (e.g., "Insurance expired on 2026-03-11") and a suggestion to update the patient's insurance if they have renewed (e.g., "Update insurance dates in patient profile if renewed").

2.3 WHEN a user edits a patient and submits insurance data for a plan that already has an existing `patient_insurance` record for the same `insurance_plan_id` THEN the system SHALL update the existing record instead of creating a duplicate, regardless of whether the existing record is active or expired.

2.4 WHEN the check-in modal shows expired insurance for an NHIS patient and the system is in extension mode (`NhisSettings.verification_mode = 'extension'`) THEN the system SHALL display a "Verify NHIS Membership" button alongside the expired insurance warning, using the existing `useNhisExtension` hook to verify membership against the NHIA portal. If the extension confirms the membership is active with a new expiry date, the system SHALL auto-update the `coverage_start_date` (if provided) and `coverage_end_date` on the existing `patient_insurance` record via the existing `syncInsuranceDates` flow, then allow the check-in to proceed with insurance. WHEN the system is in manual mode (`NhisSettings.verification_mode = 'manual'`) THEN the expired insurance warning SHALL include the actual expiry date and a clear suggestion to update the patient's insurance dates in the patient profile if the patient has renewed externally.

2.5 WHEN insurance is added or updated for a patient who has an active check-in with pending charges THEN the existing `ApplyInsuranceToCheckinModal` SHALL be enhanced to display a summary of the pending charges that will be affected (e.g., "7 pending charges totaling GHS 680.00 will have insurance coverage applied") before the receptionist confirms. This gives the receptionist visibility and control over what's about to change. The existing backend flow (`InsuranceApplicationService::applyInsuranceToActiveCheckin` via `CheckinController::applyInsurance`) already handles the actual re-evaluation correctly and SHALL continue to be used.

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a patient's `coverage_end_date` is in the past (before today) THEN the system SHALL CONTINUE TO treat the insurance as expired and not allow it to be used for check-in.

3.2 WHEN a patient's `coverage_end_date` is null (no end date set) THEN the system SHALL CONTINUE TO treat the insurance as valid indefinitely.

3.3 WHEN a patient's `coverage_end_date` is in the future THEN the system SHALL CONTINUE TO treat the insurance as valid and allow it to be used for check-in.

3.4 WHEN a patient has no insurance records at all and the user adds insurance via patient edit THEN the system SHALL CONTINUE TO create a new `PatientInsurance` record.

3.5 WHEN a patient has an active insurance record for Plan A and the user adds insurance for a different Plan B THEN the system SHALL CONTINUE TO create a new record for Plan B (this is a legitimate new insurance, not a duplicate).

3.6 WHEN the `InsuranceService::verifyEligibility` method checks coverage dates THEN the system SHALL CONTINUE TO use the existing `coverage_end_date >= $date` comparison which already handles the inclusive date correctly.

3.7 WHEN a patient has valid (non-expired) insurance and is checked in THEN the system SHALL CONTINUE TO show the insurance confirmation dialog and apply insurance to charges as before.

3.8 WHEN charges for a check-in have already been paid or settled (status is not 'pending') THEN the system SHALL CONTINUE TO leave those charges unchanged — the existing `InsuranceApplicationService::applyInsuranceToActiveCheckin` already respects charge status and only processes pending charges, and this behavior SHALL be preserved.

3.9 WHEN a patient's insurance is added or updated but the patient has no active check-in or admission THEN the system SHALL CONTINUE TO only update the patient's insurance profile without attempting to re-evaluate any charges.

3.10 WHEN new charges are created after insurance has already been applied to the check-in THEN the system SHALL CONTINUE TO apply insurance coverage to those new charges at creation time via the existing event-driven billing flow.

3.11 WHEN a patient has valid (non-expired) NHIS insurance and the receptionist uses the NHIS extension to verify membership in extension mode THEN the system SHALL CONTINUE TO auto-fill the CCC, auto-sync coverage dates if they differ, and allow check-in with insurance as before.

3.12 WHEN the system is in manual verification mode (`NhisSettings.verification_mode = 'manual'`) and a patient has valid insurance THEN the system SHALL CONTINUE TO allow the receptionist to manually enter the CCC and proceed with insurance check-in without requiring extension verification.

3.13 WHEN the NHIS extension verifies a membership and returns INACTIVE status for an expired insurance record THEN the system SHALL CONTINUE TO block insurance check-in and display the INACTIVE warning, even if the stored insurance dates were expired — the extension result takes precedence over stored data.

3.14 WHEN insurance is newly added via `PatientController::update` and the patient has an active check-in without insurance THEN the system SHALL CONTINUE TO redirect back with a prompt to apply insurance (the existing redirect flow), allowing the receptionist to trigger the `ApplyInsuranceToCheckinModal`.
