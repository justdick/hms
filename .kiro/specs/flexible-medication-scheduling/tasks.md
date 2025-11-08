# Implementation Plan

## Note on Task Numbering

Tasks marked with **"-R"** suffix (e.g., "4-R") are **Revised** tasks that replace earlier completed tasks due to a design change. The original approach used auto-generated interval-based scheduling starting at 6 AM. The revised approach uses nurse-configured, day-based scheduling with smart defaults.

- **Old tasks (1-7)**: Marked complete where applicable - represents initial implementation
- **Revised tasks (X-R)**: New implementation approach - these are the active tasks to work on
- **Unchanged tasks**: Tasks without "-R" that remain valid (e.g., Task 2, Task 7)

**When working on this spec, focus on the "-R" tasks for the new approach.**

---

- [x] 1. Database migrations and schema updates (INITIAL IMPLEMENTATION)
  - Create `medication_schedule_adjustments` table with foreign keys, indexes, and audit fields
  - Add `discontinued_at`, `discontinued_by_id`, `discontinuation_reason` columns to `prescriptions` table
  - Add `is_adjusted` boolean column to `medication_administrations` table
  - _Requirements: 7.1, 7.2, 10.2, 10.3, 10.4_

- [x] 1-R. Add schedule_pattern column to prescriptions table





  - Add `schedule_pattern` JSON column to `prescriptions` table
  - Create migration file
  - Run migration
  - _Requirements: 1.1, 2.1_

- [x] 2. Create MedicationScheduleAdjustment model and relationships (UNCHANGED)
  - Create `MedicationScheduleAdjustment` model with fillable fields and casts
  - Define `medicationAdministration()` and `adjustedBy()` relationships
  - Add `scheduleAdjustments()` and `latestAdjustment()` relationships to `MedicationAdministration` model
  - Add helper methods: `isAdjusted()`, `canBeAdjusted()`
  - _Requirements: 7.1, 7.2, 6.1_

- [x] 3. Update Prescription model for discontinuation (INITIAL IMPLEMENTATION)
  - Add `discontinued_at`, `discontinued_by_id`, `discontinuation_reason` to fillable array
  - Add `discontinuedBy()` relationship
  - Create `discontinue(User $user, ?string $reason)` method
  - Add helper methods: `isDiscontinued()`, `canBeDiscontinued()`
  - Add `scopeActive()` to exclude discontinued prescriptions
  - Update casts for new datetime field
  - _Requirements: 10.2, 10.3, 10.4, 10.6_

- [x] 3-R. Update Prescription model for schedule pattern




  - Add `schedule_pattern` to fillable array
  - Update casts to include `schedule_pattern` as JSON
  - Add helper methods: `hasSchedule()`, `isPendingSchedule()`
  - _Requirements: 1.1, 1.2_

- [x] 4. Enhance MedicationScheduleService with interval-based scheduling (OLD APPROACH - DEPRECATED)
  - [Old implementation - will be replaced by 4-R]
  - Implemented `calculateFirstDoseTime()`, `calculateIntervalHours()`, `parseFrequencyInterval()`
  - Implemented `generateSchedule()`, `adjustScheduleTime()`, `discontinuePrescription()`

- [x] 4-R. Implement MedicationScheduleService with day-based scheduling (NEW APPROACH)






- [x] 4-R.1 Implement `generateSmartDefaults()` method


  - For BID: Day 1 = [current time, next standard time], Subsequent = [06:00, 18:00]
  - For TID: Day 1 = [next available from 06:00/14:00/22:00], Subsequent = [06:00, 14:00, 22:00]
  - For QID: Subsequent = [06:00, 12:00, 18:00, 00:00]
  - For Q4H/Q6H/Q2H: Calculate from current time rounded to nearest hour
  - Return array with day_1 and subsequent keys
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 4-R.2 Implement `generateScheduleFromPattern()` method


  - Read schedule_pattern from prescription
  - For each day in duration: use day_1 for Day 1, day_X if exists, else subsequent
  - Create MedicationAdministration records with calculated times
  - Skip PRN prescriptions
  - _Requirements: 1.1, 2.1, 2.2, 2.3, 2.4, 2.5_

- [x] 4-R.3 Implement `reconfigureSchedule()` method


  - Cancel all future scheduled administrations (status = 'cancelled')
  - Preserve administrations already given
  - Update prescription schedule_pattern
  - Call generateScheduleFromPattern() to create new schedule
  - Create audit record of reconfiguration
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

- [x] 4-R.4 Implement `adjustScheduleTime()` method

  - Validate administration is not already given
  - Update `scheduled_time` field
  - Set `is_adjusted` to true
  - Create `MedicationScheduleAdjustment` audit record
  - _Requirements: 4.2, 4.3, 4.4, 7.1, 7.2_

- [x] 4-R.5 Implement `discontinuePrescription()` method

  - Set prescription discontinuation fields
  - Cancel all future scheduled administrations (status = 'cancelled')
  - Preserve administrations already given
  - _Requirements: 10.2, 10.3, 10.4, 10.5_

- [x] 5. Create API controllers and routes (OLD APPROACH - DEPRECATED)
  - [Old implementation - will be replaced by 5-R]
  - Implemented `index()`, `adjustTime()`, `adjustmentHistory()`, `discontinue()`
  - Created `AdjustScheduleTimeRequest`, `DiscontinuePrescriptionRequest`
  - Registered old API routes

- [x] 5-R. Create API controllers and routes (NEW APPROACH)





- [x] 5-R.1 Create MedicationScheduleController with new endpoints


  - Implement `smartDefaults()` to return smart default time patterns
  - Implement `configureSchedule()` to save pattern and generate schedule
  - Implement `reconfigureSchedule()` to reconfigure existing schedule
  - Keep existing: `index()`, `adjustTime()`, `adjustmentHistory()`, `discontinue()`
  - _Requirements: 1.1, 1.2, 2.1, 4.1, 7.4, 9.1, 10.1_



- [x] 5-R.2 Create form request classes


  - Create `ConfigureScheduleRequest` with validation for schedule_pattern JSON
  - Keep existing: `AdjustScheduleTimeRequest`, `DiscontinuePrescriptionRequest`
  - _Requirements: 1.1, 2.1, 4.2, 8.1, 8.2, 10.7_



- [x] 5-R.3 Register new API routes


  - GET `/api/prescriptions/{prescription}/smart-defaults`
  - POST `/api/prescriptions/{prescription}/configure-schedule`
  - POST `/api/prescriptions/{prescription}/reconfigure-schedule`
  - Keep existing routes: schedule, adjust-time, adjustment-history, discontinue
  - _Requirements: 1.1, 2.1, 4.1, 7.4, 9.1, 10.1_

- [x] 6. Implement policy authorization (OLD APPROACH - PARTIAL)
  - [Partial implementation - needs new methods in 6-R]
  - Implemented `adjustSchedule()` in MedicationAdministrationPolicy
  - Implemented `discontinue()` in PrescriptionPolicy

- [x] 6-R. Add new policy methods for schedule configuration






- [x] 6-R.1 Update PrescriptionPolicy with new methods



  - Implement `configureSchedule()` method checking user permissions
  - Implement `reconfigureSchedule()` method checking user permissions
  - Keep existing: `discontinue()` method
  - _Requirements: 8.1, 8.2, 8.3_

- [x] 6-R.2 Verify MedicationAdministrationPolicy





  - Verify existing `adjustSchedule()` method works with new approach
  - Ensure ward access checks are in place
  - _Requirements: 8.1, 8.2, 8.3, 8.4_

- [x] 7. Create Medication Administration tab UI






- [x] 7.1 Create MedicationAdministrationTab component




  - Fetch today's medication administrations for patient
  - Display timeline view grouped by scheduled times
  - Show status indicators (due, overdue, given, adjusted)
  - Implement quick actions: Give, Hold, Refuse
  - Add filter: Today / Upcoming / All / Given
  - Calculate and display red badge count (today's pending only)
  - _Requirements: 3.1, 5.1, 5.2_

- [x] 7.2 Create MedicationAdministrationCard component




  - Display drug name, dose, route, scheduled time, status
  - Show adjusted badge if `is_adjusted` is true
  - Click time to open adjust modal
  - Quick action buttons for Give, Hold, Refuse
  - _Requirements: 3.1, 5.1, 5.2, 5.3_



- [x] 7.3 Integrate tab into Ward Patient page

  - Add "Medication Administration" tab to patient page tabs
  - Display red badge with count of today's pending medications
  - Update badge count in real-time after administration actions
  - _Requirements: 3.1_

- [x] 8. Create Medication History tab UI




- [x] 8.1 Create MedicationHistoryTab component


  - Fetch all prescriptions (active and discontinued) for patient
  - Display list of prescription cards
  - Implement filter dropdown: All / Active / Discontinued / Pending Schedule
  - Show "⚠️ Configure Times" button for prescriptions without schedule
  - Style discontinued prescriptions with reduced opacity
  - _Requirements: 1.1, 1.2, 5.1, 10.6_

- [x] 8.2 Create MedicationHistoryCard component


  - Display drug name, frequency, duration, start date, status
  - Show status badge (Active / Discontinued / Pending Schedule)
  - For pending schedule: prominent "Configure Times" button
  - For discontinued: show reason, discontinued by, date
  - Three-dot menu with "View Full Schedule", "Reconfigure Times", and "Discontinue" actions
  - Disable "Discontinue" for already discontinued prescriptions
  - _Requirements: 1.1, 1.2, 5.1, 9.1, 10.1, 10.6_

- [x] 8.3 Integrate tab into Ward Patient page


  - Add "Medication History" tab to patient page tabs
  - _Requirements: 5.1_

- [x] 9. Create shared modal components







- [x] 9.1 Create ConfigureScheduleTimesModal component (NEW)

  - Fetch smart defaults from API when modal opens
  - Display Day 1 section with time pickers for each dose
  - Display Subsequent Days section with time pickers
  - Allow adding custom days (Day 2, Day 3, etc.) with "+ Add another custom day" button
  - Allow adding/removing doses for any day
  - Show real-time schedule preview (total doses, days breakdown)
  - Submit button calls API to configure or reconfigure schedule
  - _Requirements: 1.1, 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.5, 9.1_

- [x] 9.2 Create AdjustScheduleTimeModal component


  - DateTime picker for new scheduled time
  - Optional reason textarea
  - Validation: must be future time
  - Display current time and new time side-by-side
  - Submit button calls API to adjust time
  - _Requirements: 4.2, 4.3, 7.1_


- [x] 9.3 Create DiscontinueMedicationModal component

  - Required reason textarea
  - Warning message about cancelling future doses
  - Display count and list of doses that will be cancelled
  - Confirmation button with "Discontinue Medication" text
  - Submit button calls API to discontinue prescription
  - _Requirements: 10.1, 10.2, 10.7_

- [x] 9.4 Create PrescriptionScheduleModal component


  - Display full schedule for a prescription (all administrations)
  - Show status indicators: given, scheduled, cancelled
  - Highlight adjusted times with badge
  - Show adjustment history on hover
  - _Requirements: 4.1, 6.1, 6.4_


- [x] 9.5 Create ScheduleAdjustmentBadge component

  - Display clock icon with edit symbol
  - Blue color styling
  - Tooltip showing adjustment history (who, when, reason)
  - _Requirements: 6.1, 6.2, 6.3_

- [x] 10. Write feature tests for smart defaults API





  - Test BID at 10:30 AM suggests Day 1: [10:30, 18:00], Subsequent: [06:00, 18:00]
  - Test TID at 10:00 AM suggests Day 1: [14:00, 22:00], Subsequent: [06:00, 14:00, 22:00]
  - Test QID suggests standard times [06:00, 12:00, 18:00, 00:00]
  - Test Q4H calculates from current time rounded to nearest hour
  - Test PRN returns empty defaults
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 11. Write feature tests for schedule configuration API





  - Test authorized user can configure schedule
  - Test configuration creates correct number of MedicationAdministration records
  - Test Day 1 pattern used for first day
  - Test subsequent pattern used for remaining days
  - Test custom day patterns (day_2, day_3) are applied correctly
  - Test PRN prescriptions cannot be configured
  - Test unauthorized user receives 403 error
  - _Requirements: 1.1, 2.1, 2.2, 2.3, 2.4, 2.5, 8.1, 8.2_

- [x] 12. Write feature tests for schedule reconfiguration API





  - Test reconfiguration cancels future doses
  - Test reconfiguration preserves given doses
  - Test reconfiguration creates new schedule with new pattern
  - Test unauthorized user receives 403 error
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 8.1, 8.2_

- [x] 13. Write feature tests for individual time adjustment API





  - Test authorized user can adjust individual times
  - Test unauthorized user receives 403 error
  - Test adjustment creates audit record with correct fields
  - Test cannot adjust already given medication (422 error)
  - Test cannot adjust to past time (422 error)
  - Test adjustment sets `is_adjusted` flag to true
  - _Requirements: 4.2, 4.3, 4.4, 7.1, 7.2, 8.1, 8.2, 8.3_

- [x] 14. Write feature tests for discontinuation API




  - Test authorized user can discontinue prescription
  - Test unauthorized user receives 403 error
  - Test future scheduled doses are cancelled
  - Test given doses are preserved (not cancelled)
  - Test discontinuation fields are set correctly
  - Test cannot discontinue already discontinued prescription
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.8, 8.1, 8.2_

- [x] 15. Write feature tests for policy authorization





  - Test doctors can configure schedules
  - Test nurses can configure schedules
  - Test users without permission cannot configure
  - Test users can only configure for wards they have access to
  - Test doctors can discontinue prescriptions
  - Test nurses can discontinue prescriptions (if permitted)
  - _Requirements: 8.1, 8.2, 8.3, 8.4_

- [x] 16. Update existing medication administration workflow





  - Ensure "Give" action works with configured schedules
  - Ensure "Hold" and "Refuse" actions work with configured schedules
  - Update any existing UI that displays medication schedules
  - Test integration with existing ward round and consultation flows
  - _Requirements: 4.5, 10.8_
