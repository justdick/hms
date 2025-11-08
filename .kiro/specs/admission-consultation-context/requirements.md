# Requirements Document

## Introduction

This feature enhances the admitted patient show page by displaying consultation context (vitals and prescriptions) from the consultation that led to the admission. This enables nurses to view critical patient information and administer medications immediately upon admission, without waiting for ward rounds.

## Glossary

- **Admission System**: The ward management system that tracks admitted patients
- **Consultation Context**: The clinical information (vitals, prescriptions, diagnoses) from the consultation that resulted in patient admission
- **Admitted Patient Show Page**: The detailed view page for an individual admitted patient in the ward
- **Consultation Vitals**: Vital signs recorded during the consultation visit
- **Consultation Prescriptions**: Medications prescribed during the consultation
- **Medication Administration**: The process by which nurses give prescribed medications to patients
- **Ward Rounds**: Regular doctor visits to review admitted patients

## Requirements

### Requirement 1

**User Story:** As a nurse, I want to see the vitals recorded on the day of admission during the consultation, so that I have baseline clinical data for the patient.

#### Acceptance Criteria

1. WHEN a nurse views an admitted patient's page, THE Admission System SHALL display vitals recorded during the consultation that led to admission
2. THE Admission System SHALL display temperature, blood pressure (systolic/diastolic), pulse rate, respiratory rate, oxygen saturation, weight, and height from the consultation vitals
3. THE Admission System SHALL display the date and time when the consultation vitals were recorded
4. THE Admission System SHALL display the name of the staff member who recorded the consultation vitals
5. WHERE no consultation vitals exist, THE Admission System SHALL display a message indicating no vitals were recorded during consultation

### Requirement 2

**User Story:** As a nurse, I want to see the prescriptions from the consultation that led to admission, so that I can begin administering medications immediately without waiting for ward rounds.

#### Acceptance Criteria

1. WHEN a nurse views an admitted patient's page, THE Admission System SHALL display all prescriptions from the consultation that led to the admission
2. THE Admission System SHALL display medication name, dosage form, frequency, duration, dose quantity, and instructions for each consultation prescription
3. THE Admission System SHALL integrate consultation prescriptions with the existing medication administration workflow
4. THE Admission System SHALL allow nurses to schedule and administer consultation prescriptions using the existing medication administration panel
5. WHERE no consultation prescriptions exist, THE Admission System SHALL display a message indicating no medications were prescribed during consultation

### Requirement 3

**User Story:** As a nurse, I want to clearly distinguish between consultation vitals/prescriptions and ward round data, so that I understand the timeline of patient care.

#### Acceptance Criteria

1. THE Admission System SHALL display consultation vitals and prescriptions in clearly labeled sections
2. THE Admission System SHALL use visual indicators (badges or labels) to identify data as "From Admission Consultation"
3. THE Admission System SHALL display consultation vitals within the existing Vitals tab alongside ward vitals
4. THE Admission System SHALL display consultation prescriptions within the existing Medications tab
5. THE Admission System SHALL maintain the existing "View Consultation" button for doctors to access full consultation details

### Requirement 4

**User Story:** As a healthcare provider, I want consultation prescriptions to be available for medication administration scheduling, so that treatment can begin immediately after admission.

#### Acceptance Criteria

1. THE Admission System SHALL create medication administration records for consultation prescriptions upon admission
2. THE Admission System SHALL allow nurses to schedule administration times for consultation prescriptions
3. THE Admission System SHALL track administration status (scheduled, given, held, refused, omitted) for consultation prescriptions
4. THE Admission System SHALL link consultation prescriptions to the medication administration workflow
5. THE Admission System SHALL maintain separate tracking for consultation prescriptions versus ward round prescriptions

### Requirement 5

**User Story:** As a system administrator, I want the consultation context to load efficiently, so that page performance remains acceptable.

#### Acceptance Criteria

1. THE Admission System SHALL load consultation context using eager loading to prevent N+1 queries
2. THE Admission System SHALL include consultation vitals, prescriptions, and diagnoses in the initial page load
3. THE Admission System SHALL complete page rendering within 2 seconds for admissions with consultation context
4. THE Admission System SHALL handle admissions without linked consultations gracefully
5. THE Admission System SHALL maintain existing page performance for other admission data
