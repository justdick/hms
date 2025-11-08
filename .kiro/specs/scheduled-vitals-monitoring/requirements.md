# Requirements Document

## Introduction

This feature enables scheduled vitals monitoring for admitted patients with configurable recording intervals. The system will alert healthcare staff through toast notifications with sound when vitals are due or overdue, ensuring consistent patient monitoring and preventing missed recordings. A 15-minute grace period is provided before marking vitals as overdue.

## Glossary

- **Vitals_System**: The scheduled vitals monitoring and alerting system
- **Patient_Admission**: A patient currently admitted to a ward
- **Vitals_Schedule**: A configured interval plan for recording patient vitals
- **Recording_Interval**: The time period between scheduled vital sign recordings (e.g., every 2 hours, every 4 hours)
- **Due_Time**: The scheduled time when vitals should be recorded
- **Grace_Period**: A 15-minute window after the due time before marking vitals as overdue
- **Overdue_Status**: Status indicating vitals were not recorded within the grace period
- **Toast_Notification**: A temporary UI notification message displayed on screen
- **Sound_Alert**: An audio notification played to alert staff
- **Healthcare_Staff**: Nurses, doctors, or other authorized users who record vitals

## Requirements

### Requirement 1

**User Story:** As a nurse, I want to set vitals recording intervals for admitted patients, so that I can ensure consistent monitoring based on patient needs

#### Acceptance Criteria

1. WHEN a Patient_Admission is active, THE Vitals_System SHALL allow Healthcare_Staff to configure a Vitals_Schedule with a Recording_Interval
2. THE Vitals_System SHALL support Recording_Interval options of 1 hour, 2 hours, 4 hours, 6 hours, 8 hours, and 12 hours
3. THE Vitals_System SHALL allow Healthcare_Staff to set a custom Recording_Interval in minutes
4. THE Vitals_System SHALL store the Vitals_Schedule with the Patient_Admission record
5. THE Vitals_System SHALL allow Healthcare_Staff to modify or disable an existing Vitals_Schedule

### Requirement 2

**User Story:** As a nurse, I want to receive toast notifications with sound when vitals are due, so that I am reminded to record vitals even when busy with other tasks

#### Acceptance Criteria

1. WHEN the current time reaches a Due_Time for a Patient_Admission, THE Vitals_System SHALL display a Toast_Notification to Healthcare_Staff viewing the ward
2. WHEN displaying a Toast_Notification for due vitals, THE Vitals_System SHALL play a gentle Sound_Alert
3. THE Toast_Notification SHALL display the patient name, bed number, and scheduled vitals due time
4. THE Toast_Notification SHALL include a clickable action to navigate to the vitals recording form
5. THE Toast_Notification SHALL remain visible for 10 seconds or until dismissed by Healthcare_Staff

### Requirement 3

**User Story:** As a nurse, I want a grace period before vitals are marked overdue, so that I have reasonable time to complete the recording during busy periods

#### Acceptance Criteria

1. WHEN vitals are not recorded at the Due_Time, THE Vitals_System SHALL wait for a Grace_Period of 15 minutes before marking as Overdue_Status
2. WHILE vitals are within the Grace_Period, THE Vitals_System SHALL display the status as "Due"
3. WHEN the Grace_Period expires without vitals being recorded, THE Vitals_System SHALL change the status to Overdue_Status
4. THE Vitals_System SHALL calculate the next Due_Time based on the Recording_Interval from the last recorded vitals time

### Requirement 4

**User Story:** As a nurse, I want more urgent notifications for overdue vitals, so that I prioritize patients who need immediate attention

#### Acceptance Criteria

1. WHEN vitals reach Overdue_Status, THE Vitals_System SHALL display a Toast_Notification with urgent styling
2. WHEN displaying an overdue Toast_Notification, THE Vitals_System SHALL play a more prominent Sound_Alert
3. THE Vitals_System SHALL repeat the overdue Toast_Notification every 15 minutes until vitals are recorded
4. THE overdue Toast_Notification SHALL display the patient name, bed number, and time elapsed since Due_Time
5. THE overdue Toast_Notification SHALL remain visible for 15 seconds or until dismissed by Healthcare_Staff

### Requirement 5

**User Story:** As a nurse, I want to see all pending and overdue vitals on a dashboard, so that I can prioritize my work and ensure no patient is missed

#### Acceptance Criteria

1. THE Vitals_System SHALL provide a dashboard view showing all Patient_Admission records with active Vitals_Schedule
2. THE dashboard SHALL display each patient with their next Due_Time and current status (Upcoming, Due, or Overdue)
3. THE dashboard SHALL sort patients by urgency with Overdue_Status patients displayed first
4. THE dashboard SHALL allow filtering by ward
5. WHEN Healthcare_Staff clicks on a patient entry, THE Vitals_System SHALL navigate to the vitals recording form

### Requirement 6

**User Story:** As a nurse, I want to see vitals schedule status on ward and patient pages, so that I can quickly identify which patients need vitals recorded without navigating to a separate dashboard

#### Acceptance Criteria

1. WHEN Healthcare_Staff views a ward page, THE Vitals_System SHALL display vitals schedule status for each Patient_Admission in the ward
2. THE ward page SHALL show visual indicators (badges or icons) for patients with Due or Overdue_Status vitals
3. WHEN Healthcare_Staff views a patient details page, THE Vitals_System SHALL display the current Vitals_Schedule including Recording_Interval and next Due_Time
4. THE patient details page SHALL show the vitals status (Upcoming, Due, or Overdue) with time remaining or time elapsed
5. THE patient details page SHALL provide a quick action button to record vitals immediately

### Requirement 7

**User Story:** As a nurse, I want the system to automatically calculate the next vitals due time after I record vitals, so that monitoring continues without manual scheduling

#### Acceptance Criteria

1. WHEN Healthcare_Staff records vitals for a Patient_Admission with an active Vitals_Schedule, THE Vitals_System SHALL calculate the next Due_Time by adding the Recording_Interval to the recorded time
2. THE Vitals_System SHALL store the next Due_Time with the Vitals_Schedule
3. THE Vitals_System SHALL reset any Overdue_Status to normal status after vitals are recorded
4. THE Vitals_System SHALL continue scheduling subsequent Due_Time values based on the Recording_Interval until the Vitals_Schedule is disabled or the patient is discharged

### Requirement 8

**User Story:** As a ward administrator, I want to configure sound alert preferences, so that staff can adjust notification volume and sounds based on ward environment

#### Acceptance Criteria

1. THE Vitals_System SHALL allow Healthcare_Staff to enable or disable Sound_Alert notifications
2. THE Vitals_System SHALL allow Healthcare_Staff to adjust Sound_Alert volume
3. THE Vitals_System SHALL store sound preferences per user account
4. THE Vitals_System SHALL provide a test button to preview Sound_Alert tones
5. THE Vitals_System SHALL respect browser notification permissions

### Requirement 9

**User Story:** As a nurse, I want vitals scheduling to automatically stop when a patient is discharged, so that I don't receive alerts for patients no longer in the ward

#### Acceptance Criteria

1. WHEN a Patient_Admission status changes to discharged, THE Vitals_System SHALL automatically disable the associated Vitals_Schedule
2. THE Vitals_System SHALL not display Toast_Notification or play Sound_Alert for discharged patients
3. THE Vitals_System SHALL retain historical Vitals_Schedule data for discharged patients for reporting purposes
