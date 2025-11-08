# Requirements Document

## Introduction

This feature enhances the ward patient show page by adding an Overview tab that provides a comprehensive snapshot of the patient's current status, and a dedicated Labs tab for viewing laboratory orders and results. The Overview tab will consolidate key information from diagnosis, prescriptions, vitals, and labs into a single, easily digestible view. The Labs tab will only appear when lab orders exist for the patient, providing focused access to laboratory data.

## Glossary

- **Ward_Patient_System**: The inpatient care management system for tracking admitted patients
- **Overview_Tab**: A new tab displaying consolidated patient information including diagnosis, prescriptions, vitals, and lab summaries
- **Labs_Tab**: A dedicated tab for viewing detailed laboratory orders and results
- **Patient_Admission**: A record of a patient's admission to the ward
- **Ward_Round**: A doctor's visit and assessment of an admitted patient
- **Lab_Order**: A request for laboratory tests or services
- **Vital_Signs**: Physiological measurements including temperature, blood pressure, pulse, etc.
- **Prescription**: Medication orders from consultations or ward rounds
- **Diagnosis**: Medical conditions identified during consultation or ward rounds

## Requirements

### Requirement 1

**User Story:** As a healthcare provider, I want to see an overview of the patient's key information in one place, so that I can quickly assess their current status without navigating multiple tabs

#### Acceptance Criteria

1. WHEN THE Ward_Patient_System loads the patient show page, THE Ward_Patient_System SHALL display an Overview tab as the default tab
2. THE Overview_Tab SHALL display the most recent diagnosis from consultation or ward rounds
3. THE Overview_Tab SHALL display active prescriptions from both consultation and ward rounds
4. THE Overview_Tab SHALL display the most recent vital signs with timestamp
5. THE Overview_Tab SHALL display a summary of pending lab orders and recent lab results

### Requirement 2

**User Story:** As a healthcare provider, I want to see detailed laboratory information in a dedicated tab, so that I can review test orders and results without clutter from other patient data

#### Acceptance Criteria

1. WHEN THE Patient_Admission has one or more Lab_Orders, THE Ward_Patient_System SHALL display a Labs tab in the tab list
2. WHEN THE Patient_Admission has zero Lab_Orders, THE Ward_Patient_System SHALL NOT display a Labs tab
3. THE Labs_Tab SHALL display all Lab_Orders with their status, priority, and order date
4. THE Labs_Tab SHALL display result values and notes for completed Lab_Orders
5. THE Labs_Tab SHALL group Lab_Orders by status (pending, in_progress, completed, cancelled)

### Requirement 3

**User Story:** As a healthcare provider, I want the overview to highlight critical information, so that I can identify urgent issues at a glance

#### Acceptance Criteria

1. THE Overview_Tab SHALL display visual indicators for overdue vitals
2. THE Overview_Tab SHALL display visual indicators for pending medications
3. THE Overview_Tab SHALL display visual indicators for urgent lab orders
4. THE Overview_Tab SHALL use color coding to distinguish between normal, warning, and critical states
5. THE Overview_Tab SHALL display the admission day number and length of stay

### Requirement 4

**User Story:** As a healthcare provider, I want to navigate from the overview to detailed information, so that I can drill down into specific areas when needed

#### Acceptance Criteria

1. WHEN A healthcare provider clicks on a diagnosis in the Overview_Tab, THE Ward_Patient_System SHALL navigate to the Ward Rounds tab
2. WHEN A healthcare provider clicks on prescriptions in the Overview_Tab, THE Ward_Patient_System SHALL navigate to the Medication Administration tab
3. WHEN A healthcare provider clicks on vitals in the Overview_Tab, THE Ward_Patient_System SHALL navigate to the Vital Signs tab
4. WHEN A healthcare provider clicks on labs in the Overview_Tab, THE Ward_Patient_System SHALL navigate to the Labs tab
5. THE Overview_Tab SHALL provide clear visual cues that sections are clickable

### Requirement 5

**User Story:** As a healthcare provider, I want to see lab results in an organized format, so that I can quickly interpret test outcomes

#### Acceptance Criteria

1. THE Labs_Tab SHALL display lab results in a table format with test name, value, reference range, and status
2. THE Labs_Tab SHALL highlight abnormal results that fall outside reference ranges
3. THE Labs_Tab SHALL display the ordering physician and date for each Lab_Order
4. THE Labs_Tab SHALL allow filtering Lab_Orders by status
5. THE Labs_Tab SHALL display special instructions for each Lab_Order when present
