# Implementation Plan

- [x] 1. Set up backend routes and controller structure






  - Create dedicated patient routes file at `routes/patients.php`
  - Register patient routes in `bootstrap/app.php`
  - Extend `PatientController` with index, show, and update methods
  - _Requirements: 1.1, 1.2, 5.1, 5.2_

- [x] 2. Implement patient list backend functionality




  - [x] 2.1 Enhance PatientController index method


    - Update existing `index()` method in `PatientController`
    - Add eager loading for recent check-in relationship (most recent incomplete check-in)
    - Ensure search functionality works using existing `Patient::search()` scope
    - Verify pagination is set to 25 per page
    - Include recent check-in data in Inertia response
    - _Requirements: 1.2, 1.3, 1.4, 1.5_

- [x] 3. Implement patient profile backend functionality




  - [x] 3.1 Implement PatientController show method


    - Write `show()` method in `PatientController`
    - Load patient with all relationships (insurance plans, check-in history)
    - Include authorization check using policy
    - Return Inertia response with patient data and permissions
    - _Requirements: 5.1, 5.2_

  - [x] 3.2 Create UpdatePatientRequest for validation


    - Write `UpdatePatientRequest` at `app/Http/Requests/UpdatePatientRequest.php`
    - Define validation rules for patient demographics
    - Define validation rules for insurance information
    - Include authorization logic
    - _Requirements: 5.4, 5.5_

  - [x] 3.3 Implement PatientController update method


    - Write `update()` method in `PatientController`
    - Validate request using `UpdatePatientRequest`
    - Update patient demographics
    - Update or create insurance records if provided
    - Return success response with updated patient data
    - _Requirements: 5.3, 5.4, 5.5_

- [x] 4. Create patient list frontend page






  - [x] 4.1 Create PatientCard component

    - Write `PatientCard` component at `resources/js/components/Patient/PatientCard.tsx`
    - Display patient number, name, age, gender, phone
    - Show insurance badge if active insurance exists
    - Include "View" and "Check-in" action buttons
    - _Requirements: 1.2, 1.4_


  - [x] 4.2 Create Patients/Index page

    - Write `Patients/Index.tsx` at `resources/js/Pages/Patients/Index.tsx`
    - Implement search input with debouncing (300ms)
    - Display patient list using PatientCard components
    - Implement pagination controls
    - Add "Register New Patient" button
    - Handle quick check-in action
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 5. Create patient profile and edit pages




  - [x] 5.1 Create Patients/Show page


    - Write `Patients/Show.tsx` at `resources/js/Pages/Patients/Show.tsx`
    - Display patient demographics section
    - Display insurance information section
    - Display check-in history section
    - Include "Edit" and "Check-in" buttons
    - _Requirements: 5.1, 5.2_

  - [x] 5.2 Create Patients/Edit page


    - Write `Patients/Edit.tsx` at `resources/js/Pages/Patients/Edit.tsx`
    - Reuse PatientRegistrationForm component for editing
    - Pre-populate form with existing patient data
    - Handle form submission to update endpoint
    - Redirect to profile page on success
    - _Requirements: 5.3, 5.4, 5.5_

- [x] 6. Implement patient registration modal and check-in prompt









  - [x] 6.1 Create PatientRegistrationModal component

    - Write `PatientRegistrationModal` at `resources/js/components/Patient/RegistrationModal.tsx`
    - Wrap existing PatientRegistrationForm in a modal dialog
    - Handle modal open/close state
    - Trigger check-in prompt on successful registration
    - _Requirements: 2.1, 2.2, 2.3, 4.1, 4.2_


  - [x] 6.2 Create CheckinPromptDialog component

    - Write `CheckinPromptDialog` at `resources/js/components/Checkin/CheckinPromptDialog.tsx`
    - Display confirmation dialog with patient information
    - Include "Check-in Now" and "Later" buttons
    - Open CheckinModal when "Check-in Now" is clicked
    - Close dialog when "Later" is clicked
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_


  - [x] 6.3 Integrate check-in prompt into registration flow

    - Update PatientRegistrationForm to trigger check-in prompt callback
    - Update Checkin/Index page to show check-in prompt after registration
    - Update Patients/Index page to show check-in prompt after registration
    - Ensure CheckinModal receives pre-populated patient data
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 4.3, 4.4, 4.5_

- [x] 7. Update sidebar navigation




  - Update `app-sidebar.tsx` to add "Patients" menu item
  - Position between "Check-in" and "Consultation"
  - Use Users icon from lucide-react
  - Link to `/patients` route
  - _Requirements: 1.1_

- [x] 8. Create route helper functions





  - Add patient route helpers to route definitions
  - Create `patients.index.url()`, `patients.show.url(id)`, `patients.edit.url(id)` helpers
  - Update route imports in components
  - _Requirements: 1.1, 5.1_

- [x] 9. Implement authorization and policies






  - [x] 9.1 Create PatientPolicy

    - Write `PatientPolicy` at `app/Policies/PatientPolicy.php`
    - Implement `viewAny()`, `view()`, `create()`, `update()` methods
    - Check for appropriate permissions (patients.view, patients.create, patients.update)
    - _Requirements: 1.1, 2.1, 5.3_


  - [x] 9.2 Register policy and permissions

    - Register PatientPolicy in service provider
    - Ensure permissions are seeded in database
    - Update user roles to include patient management permissions
    - _Requirements: 1.1, 2.1, 5.3_

- [x] 10. Add database indexes for performance





  - Create migration to add indexes on patients table
  - Add composite index on (first_name, last_name) for name search
  - Add index on phone_number for phone search
  - Run migration
  - _Requirements: 1.5_

- [x] 11. Integration and end-to-end testing




  - [x] 11.1 Write feature tests for patient management


    - Write `PatientManagementTest.php` at `tests/Feature/Patient/PatientManagementTest.php`
    - Test patient list viewing with search and pagination
    - Test patient profile viewing
    - Test patient registration without insurance
    - Test patient registration with insurance
    - Test patient information update
    - Test validation errors
    - Test authorization checks
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 5.1, 5.2, 5.3, 5.4, 5.5_

  - [x] 11.2 Write feature tests for check-in flow


    - Write `PatientCheckinFlowTest.php` at `tests/Feature/Patient/PatientCheckinFlowTest.php`
    - Test check-in prompt appears after registration
    - Test immediate check-in after registration
    - Test skipping immediate check-in
    - Test quick check-in from patient list
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 4.3, 4.4, 4.5_

  - [x] 11.3 Write browser tests for patient workflows


    - Write browser test for patient registration and immediate check-in flow
    - Write browser test for patient search and profile viewing
    - Write browser test for patient editing
    - Test UI interactions and navigation
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4, 4.5, 5.1, 5.2, 5.3, 5.4, 5.5_
