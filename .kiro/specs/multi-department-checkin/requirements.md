# Requirements Document

## Introduction

This feature enables patients to check in to multiple departments on the same day (e.g., ANC in the morning, General OPD in the afternoon) while maintaining data integrity and providing clear feedback to users. It also improves the insurance claims vetting experience by grouping same-day visits and adding date filtering capabilities, and adds permission-based date filtering to the consultation queue.

## Glossary

- **Check-in**: The process of registering a patient's arrival at a department for a visit
- **CCC (Claim Check Code)**: A unique code from NHIA for insurance claims, one per patient per day
- **Department**: A hospital unit/specialty (e.g., General OPD, ANC, Pediatrics)
- **Same-Day Visit**: Multiple check-ins for the same patient on the same calendar day
- **Active Admission**: A patient currently admitted to a ward (not yet discharged)
- **Vetting Officer**: Staff member who reviews and approves insurance claims
- **Consultation Queue**: The list of checked-in patients awaiting or in consultation

## Requirements

### Requirement 1: Multi-Department Same-Day Check-in

**User Story:** As a receptionist, I want to check in a patient to multiple departments on the same day, so that patients can receive care from different specialties during a single hospital visit.

#### Acceptance Criteria

1. WHEN a patient is checked in to Department A, THE System SHALL allow check-in to Department B on the same day
2. WHEN a patient attempts to check in to the same department twice on the same day, THE System SHALL block the check-in and display a specific error message
3. WHEN checking in a patient, THE System SHALL validate that the selected department differs from any existing same-day check-ins for that patient
4. THE System SHALL track each check-in as a separate record with its own consultation, billing, and clinical data

### Requirement 2: Admission Warning During Check-in

**User Story:** As a receptionist, I want to be warned when checking in a patient who has an active admission, so that I can make an informed decision about proceeding.

#### Acceptance Criteria

1. WHEN a patient with an active admission attempts OPD check-in, THE System SHALL display a warning with the admission details
2. WHEN the warning is displayed, THE System SHALL provide an option to proceed with check-in anyway
3. WHEN the receptionist confirms to proceed, THE System SHALL create the check-in as a separate OPD visit
4. THE Check-in record SHALL include a flag indicating it was created while an admission was active

### Requirement 3: Specific Check-in Error Messages

**User Story:** As a receptionist, I want to see specific error messages when check-in fails, so that I understand exactly what went wrong and how to fix it.

#### Acceptance Criteria

1. WHEN check-in fails due to duplicate same-department check-in, THE System SHALL display: "Patient already checked in to [Department Name] today"
2. WHEN check-in fails due to missing CCC for NHIS patient, THE System SHALL display: "CCC (Claim Check Code) is required for NHIS patients"
3. WHEN check-in fails due to invalid department, THE System SHALL display: "Invalid department selected"
4. WHEN check-in fails due to validation errors, THE System SHALL display the specific validation error message
5. THE System SHALL NOT display generic "Failed to check in patient" messages without specific context

### Requirement 4: Claims List CCC Grouping

**User Story:** As a vetting officer, I want same-day claims (same CCC) to appear close together in the list, so that I can easily identify and review related visits.

#### Acceptance Criteria

1. THE Claims_List SHALL sort claims by claim_check_code as a secondary sort criterion
2. WHEN multiple claims share the same CCC, THE System SHALL display them in consecutive rows
3. WHEN consecutive rows share the same CCC, THE System SHALL display a visual indicator (subtle background or border)
4. THE Visual_Indicator SHALL be subtle enough not to distract but noticeable enough to identify related claims

### Requirement 5: Claims Date Filter with Presets

**User Story:** As a vetting officer, I want to filter claims by date using quick presets, so that I can quickly find claims from specific time periods.

#### Acceptance Criteria

1. THE Claims_List SHALL include a date filter component
2. THE Date_Filter SHALL provide preset options: Today, Yesterday, This Week, Last Week, This Month, Last Month
3. THE Date_Filter SHALL provide a custom date range option with from/to date pickers
4. WHEN a date preset is selected, THE System SHALL filter claims by date_of_attendance within that range
5. WHEN a custom range is selected, THE System SHALL filter claims where date_of_attendance is between the selected dates
6. THE Date_Filter SHALL display the currently active filter selection clearly
7. WHEN filters are cleared, THE System SHALL show all claims regardless of date

### Requirement 6: Same-Day Check-in CCC Sharing

**User Story:** As a billing officer, I want same-day check-ins to share the same CCC, so that NHIS claims are properly consolidated.

#### Acceptance Criteria

1. WHEN a patient has multiple check-ins on the same day, THE System SHALL use the same CCC for all check-ins
2. WHEN the first check-in of the day is created with a CCC, subsequent same-day check-ins SHALL inherit that CCC
3. WHEN a CCC is entered on a subsequent check-in, THE System SHALL validate it matches any existing same-day CCC
4. IF the entered CCC differs from an existing same-day CCC, THE System SHALL display a warning and allow override

### Requirement 7: Consultation Queue Date Filter (Permission-Based)

**User Story:** As a supervisor or admin, I want to filter the consultation queue by date, so that I can review check-ins from previous days (e.g., after system downtime or for auditing).

#### Acceptance Criteria

1. THE System SHALL define a permission: `consultations.filter-by-date`
2. WHEN a user has the `consultations.filter-by-date` permission, THE Consultation_Page SHALL display a date filter component
3. WHEN a user does NOT have the permission, THE Consultation_Page SHALL show only today's check-ins (current behavior)
4. THE Date_Filter SHALL provide preset options: Today, Yesterday, This Week, Last Week, Custom Range
5. WHEN a date is selected, THE System SHALL filter the check-in queue and completed list by that date
6. THE Default selection SHALL be "Today" even for users with the permission
7. WHEN the page loads, THE System SHALL NOT automatically show historical data unless explicitly filtered
