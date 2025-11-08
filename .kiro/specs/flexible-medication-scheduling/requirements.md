# Requirements Document

## Introduction

This specification addresses the need for flexible medication scheduling for admitted patients. Currently, medication administration schedules are auto-generated based on frequency codes (BID, TID, QID) using fixed times that may not align with actual ward medication rounds or clinical needs. This feature will implement interval-based scheduling with manual adjustment capabilities and comprehensive audit tracking.

## Glossary

- **MedicationScheduleService**: The Laravel service responsible for generating medication administration schedules
- **MedicationAdministration**: Database model representing a scheduled or completed medication dose
- **Prescription**: Database model representing a doctor's medication order
- **Ward Round Time**: Standard times when nurses conduct medication rounds on a ward
- **Frequency Code**: Medical abbreviation indicating how often medication should be given (e.g., BID, TID, QID)
- **Interval-Based Scheduling**: Calculating medication times based on equal time intervals (e.g., 12 hours for BID, 8 hours for TID)
- **Schedule Adjustment**: Manual modification of auto-generated medication times
- **Audit Trail**: Historical record of who made changes and when

## Requirements

### Requirement 1: Nurse-Configured Schedule Times

**User Story:** As a ward nurse, I want to configure medication administration times for each prescription, so that doses align with patient admission time and ward routines.

#### Acceptance Criteria

1. WHEN a prescription is created, THE System SHALL NOT automatically generate a medication schedule
2. WHEN a prescription has no configured schedule, THE System SHALL display a "Configure Times" indicator in the medication list
3. THE System SHALL provide an interface for nurses to configure administration times before generating the schedule
4. WHERE the prescription frequency is "PRN", THE System SHALL NOT require schedule configuration
5. THE System SHALL allow schedule configuration at any time after prescription creation

### Requirement 2: Day-Based Time Configuration

**User Story:** As a ward nurse, I want to set different administration times for the first day versus subsequent days, so that I can give immediate treatment while maintaining standard ward routines.

#### Acceptance Criteria

1. THE System SHALL allow nurses to configure administration times for Day 1 separately from subsequent days
2. THE System SHALL allow nurses to add custom time configurations for additional specific days (Day 2, Day 3, etc.)
3. THE System SHALL provide a "Subsequent Days" configuration that applies to all remaining days not specifically configured
4. WHEN generating the schedule, THE System SHALL use Day 1 times for the first day, then apply subsequent day times for remaining days
5. THE System SHALL allow nurses to add or remove doses for any configured day

### Requirement 3: Smart Default Time Population

**User Story:** As a ward nurse, I want the system to suggest sensible default times based on frequency and current time, so that I can quickly configure schedules without manual calculation.

#### Acceptance Criteria

1. WHEN configuring times for BID frequency, THE System SHALL suggest Day 1 times starting from current time and next standard time (e.g., now and 18:00), and subsequent days as 06:00 and 18:00
2. WHEN configuring times for TID frequency, THE System SHALL suggest times at 06:00, 14:00, and 22:00
3. WHEN configuring times for QID frequency, THE System SHALL suggest times at 06:00, 12:00, 18:00, and 00:00
4. WHEN configuring times for interval-based frequencies (Q4H, Q6H, Q2H), THE System SHALL calculate times starting from current time rounded to the nearest hour
5. THE System SHALL allow nurses to modify any suggested default time before generating the schedule

### Requirement 4: Manual Schedule Adjustment After Generation

**User Story:** As a healthcare provider, I want to manually adjust individual medication administration times after the schedule is generated, so that I can accommodate special clinical needs or patient preferences.

#### Acceptance Criteria

1. THE System SHALL provide an interface to view all scheduled medication times for a prescription
2. THE System SHALL allow authorized users to modify individual scheduled administration times
3. THE System SHALL prevent adjustment of medication administrations that have already been given
4. WHERE a scheduled time is in the past and status is "scheduled", THE System SHALL allow time adjustment
5. WHEN a scheduled time is adjusted, THE System SHALL create an audit record of the change

### Requirement 5: Per-Prescription Scope

**User Story:** As a prescribing doctor, I want schedule adjustments to apply only to the specific patient's prescription, so that changes don't affect other patients receiving the same medication.

#### Acceptance Criteria

1. WHEN a medication schedule is adjusted, THE System SHALL modify only the MedicationAdministration records linked to that specific prescription
2. THE System SHALL NOT modify schedules for other patients receiving the same drug
3. THE System SHALL NOT create system-wide default times based on individual adjustments

### Requirement 6: Visual Indicators for Adjusted Schedules

**User Story:** As a ward nurse, I want to see which medication times have been manually adjusted, so that I know they differ from the standard schedule.

#### Acceptance Criteria

1. THE System SHALL display a visual indicator on medication administration records that have been manually adjusted
2. THE System SHALL show both the original auto-generated time and the adjusted time when viewing schedule details
3. WHERE a schedule has not been adjusted, THE System SHALL display only the scheduled time without indicators
4. THE System SHALL use distinct styling (icon, badge, or color) to mark adjusted times in the medication list

### Requirement 7: Audit Trail for Schedule Changes

**User Story:** As a hospital administrator, I want to track who adjusts medication schedules and when, so that we maintain accountability and can review changes if needed.

#### Acceptance Criteria

1. WHEN a medication schedule time is adjusted, THE System SHALL record the user ID of the person making the change
2. WHEN a medication schedule time is adjusted, THE System SHALL record the timestamp of the change
3. WHEN a medication schedule time is adjusted, THE System SHALL record the original time and the new time
4. THE System SHALL provide an interface to view the adjustment history for a prescription
5. THE System SHALL retain adjustment history even after the medication has been administered

### Requirement 8: Permission-Based Access Control

**User Story:** As a system administrator, I want schedule adjustments to be controlled by existing permissions, so that only authorized staff can modify medication times.

#### Acceptance Criteria

1. THE System SHALL use Laravel policy authorization to control schedule adjustment access
2. THE System SHALL allow both doctors and nurses with appropriate permissions to adjust schedules
3. WHEN an unauthorized user attempts to adjust a schedule, THE System SHALL return a 403 Forbidden response
4. THE System SHALL check permissions before displaying schedule adjustment controls in the UI

### Requirement 9: Schedule Reconfiguration

**User Story:** As a ward nurse, I want to reconfigure medication times for an existing schedule, so that I can adjust to changing clinical needs or correct configuration errors.

#### Acceptance Criteria

1. THE System SHALL provide an interface to reconfigure times for prescriptions with existing schedules
2. WHEN reconfiguring times, THE System SHALL warn the nurse that future scheduled doses will be regenerated
3. WHEN reconfiguring times, THE System SHALL preserve medication administrations that have already been given
4. WHEN reconfiguring times, THE System SHALL cancel future scheduled administrations and create new ones based on the new configuration
5. THE System SHALL preserve the audit trail of previous adjustments even after reconfiguration

### Requirement 10: Discontinue Medication

**User Story:** As a prescribing doctor, I want to discontinue a medication that has already been prescribed, so that I can stop a drug and potentially switch to a different medication.

#### Acceptance Criteria

1. THE System SHALL provide an interface to discontinue an active prescription
2. WHEN a prescription is discontinued, THE System SHALL cancel all future scheduled medication administrations with status "scheduled"
3. WHEN a prescription is discontinued, THE System SHALL record the discontinuation date and time
4. WHEN a prescription is discontinued, THE System SHALL record which user discontinued the prescription
5. THE System SHALL NOT delete or modify medication administrations that have already been given (status "given")
6. THE System SHALL display discontinued prescriptions with a distinct visual indicator in the medication list
7. WHERE a prescription is discontinued, THE System SHALL allow the user to provide a reason for discontinuation
8. THE System SHALL prevent administration of medications from discontinued prescriptions
