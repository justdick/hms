# Requirements Document

## Introduction

This feature enhances the patient management workflow by introducing a dedicated patient navigation section, improving patient registration to capture insurance information, and streamlining the check-in process by allowing direct check-in immediately after patient registration.

## Glossary

- **Patient Management System**: The application component responsible for patient registration, search, and profile management
- **Check-in System**: The application component that handles patient check-in for consultations
- **Insurance Module**: The application component that manages patient insurance information
- **Sidebar Navigation**: The main navigation menu displayed on the left side of the application interface
- **Registration Modal**: A dialog interface for capturing new patient information
- **Check-in Prompt**: A confirmation dialog offering immediate check-in after patient registration

## Requirements

### Requirement 1

**User Story:** As a receptionist, I want a dedicated Patients section in the sidebar navigation, so that I can quickly access patient management features without going through the check-in page

#### Acceptance Criteria

1. WHEN the application loads, THE Patient Management System SHALL display a "Patients" menu item in the sidebar navigation
2. WHEN a user clicks the Patients menu item, THE Patient Management System SHALL navigate to a patient list page showing all registered patients
3. THE Patient Management System SHALL display patient search functionality on the patient list page
4. THE Patient Management System SHALL display a "Register New Patient" button on the patient list page
5. WHEN a user searches for a patient, THE Patient Management System SHALL filter the patient list based on name, patient ID, or phone number

### Requirement 2

**User Story:** As a receptionist, I want to capture insurance information during patient registration, so that I can complete all patient details in one step

#### Acceptance Criteria

1. WHEN a user initiates patient registration, THE Registration Modal SHALL display insurance information fields alongside basic patient details
2. THE Registration Modal SHALL include fields for insurance provider, policy number, and coverage type
3. WHEN a user submits the registration form with insurance details, THE Patient Management System SHALL save both patient and insurance information
4. WHERE insurance information is optional, THE Patient Management System SHALL allow registration without insurance details
5. WHEN insurance information is provided, THE Insurance Module SHALL validate the policy number format

### Requirement 3

**User Story:** As a receptionist, I want to check in a patient immediately after registration, so that I can save time and avoid navigating back to the check-in page

#### Acceptance Criteria

1. WHEN patient registration completes successfully, THE Check-in Prompt SHALL display a confirmation dialog
2. THE Check-in Prompt SHALL ask "Would you like to check in this patient for consultation now?"
3. WHEN a user confirms immediate check-in, THE Check-in System SHALL initiate the check-in process for the newly registered patient
4. WHEN a user declines immediate check-in, THE Patient Management System SHALL close the prompt and remain on the current page
5. WHEN immediate check-in is initiated, THE Check-in System SHALL pre-populate the patient information in the check-in form

### Requirement 4

**User Story:** As a receptionist, I want to register patients from both the check-in page and the new patient page, so that I have flexibility in my workflow

#### Acceptance Criteria

1. THE Patient Management System SHALL provide patient registration functionality on the check-in page
2. THE Patient Management System SHALL provide patient registration functionality on the dedicated patient list page
3. WHEN a user registers a patient from either location, THE Registration Modal SHALL display identical fields and functionality
4. WHEN a user registers a patient from the check-in page, THE Check-in Prompt SHALL offer immediate check-in
5. WHEN a user registers a patient from the patient list page, THE Check-in Prompt SHALL offer immediate check-in

### Requirement 5

**User Story:** As a receptionist, I want to view and manage patient profiles from the patient list, so that I can update patient information when needed

#### Acceptance Criteria

1. WHEN a user clicks on a patient in the patient list, THE Patient Management System SHALL display the patient's profile page
2. THE Patient Management System SHALL display patient demographics, contact information, and insurance details on the profile page
3. THE Patient Management System SHALL provide an "Edit" button on the patient profile page
4. WHEN a user clicks the Edit button, THE Patient Management System SHALL display an editable form with current patient information
5. WHEN a user saves profile changes, THE Patient Management System SHALL update the patient record and display a success confirmation
