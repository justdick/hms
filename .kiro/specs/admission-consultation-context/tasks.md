# Implementation Plan

- [x] 1. Update backend controller to load consultation data




  - [x] 1.1 Modify WardPatientController@show to eager load consultation relationships


    - Add consultation.doctor relationship
    - Add consultation.patientCheckin.vitalSigns relationship with latest record
    - Add consultation.prescriptions.drug relationship
    - Ensure proper eager loading to prevent N+1 queries
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 5.1, 5.2_
  
  - [x] 1.2 Write feature test for controller consultation data loading


    - Test consultation data is included in response
    - Test vitals are loaded with consultation
    - Test prescriptions are loaded with consultation
    - Test admissions without consultations work correctly
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

- [x] 2. Create consultation vitals display component





  - [x] 2.1 Create ConsultationVitalsCard component




    - Display vital signs in a readable format (temperature, BP, pulse, etc.)
    - Show recorded date/time and recorded by staff name
    - Add blue border styling for visual distinction
    - Add "Consultation" badge
    - Handle missing vitals gracefully
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 3.1, 3.2_

- [x] 3. Create consultation prescriptions display component





  - [x] 3.1 Create ConsultationPrescriptionsCard component


    - Display prescriptions in a table format
    - Show medication name, dosage form, frequency, duration, instructions
    - Add blue border styling for visual distinction
    - Add "Consultation" badge
    - Handle missing prescriptions gracefully
    - _Requirements: 2.1, 2.2, 2.5, 3.1, 3.2_

- [x] 4. Integrate consultation components into PatientShow page







  - [x] 4.1 Add ConsultationVitalsCard to Vitals tab


    - Place at top of Vitals tab content
    - Conditionally render only when consultation vitals exist
    - Maintain existing vitals schedule and recorded vitals sections
    - _Requirements: 1.1, 3.3, 3.5_
  
  - [x] 4.2 Add ConsultationPrescriptionsCard to Medications tab


    - Place at top of Medications tab content
    - Conditionally render only when consultation prescriptions exist
    - Maintain existing medication administration sections
    - _Requirements: 2.1, 3.4, 3.5_
  
  - [x] 4.3 Update TypeScript interfaces for consultation data


    - Add Consultation interface with vitals and prescriptions
    - Update PatientAdmission interface to include consultation
    - Ensure type safety for all consultation data
    - _Requirements: 5.1, 5.2_

- [ ] 5. Add visual indicators and styling
  - [ ] 5.1 Implement blue border styling for consultation cards
    - Use border-blue-200 for light mode
    - Use dark:border-blue-800 for dark mode
    - Ensure consistent styling across both cards
    - _Requirements: 3.1, 3.2_
  
  - [ ] 5.2 Add consultation badges
    - Create blue outline badge with "Consultation" text
    - Use bg-blue-50 text-blue-700 for light mode
    - Ensure dark mode compatibility
    - _Requirements: 3.1, 3.2_

- [ ] 6. Handle edge cases and error scenarios
  - [ ] 6.1 Handle admissions without consultations
    - Ensure page renders normally when consultation_id is null
    - No consultation sections should appear
    - Existing functionality unaffected
    - _Requirements: 5.4_
  
  - [ ] 6.2 Handle consultations without vitals
    - Display prescriptions section only
    - No vitals section appears
    - No error messages in console
    - _Requirements: 1.5_
  
  - [ ] 6.3 Handle consultations without prescriptions
    - Display vitals section only
    - No prescriptions section appears
    - No error messages in console
    - _Requirements: 2.5_

- [ ] 7. Testing and validation
  - [ ] 7.1 Write feature tests for ward patient show page
    - Test consultation vitals display correctly
    - Test consultation prescriptions display correctly
    - Test visual indicators are present
    - Test edge cases (no consultation, no vitals, no prescriptions)
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 3.1, 3.2_
  
  - [ ] 7.2 Write browser test for end-to-end workflow
    - Navigate to admitted patient page
    - Verify consultation vitals section in Vitals tab
    - Verify consultation prescriptions section in Medications tab
    - Verify "View Consultation" button still works
    - Verify visual distinction between consultation and ward data
    - _Requirements: 1.1, 2.1, 3.1, 3.2, 3.3, 3.4, 3.5_
  
  - [ ] 7.3 Verify performance with eager loading
    - Check query count doesn't increase significantly
    - Verify page load time remains under 2 seconds
    - Test with admissions that have large consultation datasets
    - _Requirements: 5.1, 5.2, 5.3_

- [ ] 8. Documentation and cleanup
  - [ ] 8.1 Update component documentation
    - Document ConsultationVitalsCard props and usage
    - Document ConsultationPrescriptionsCard props and usage
    - Add inline code comments for complex logic
    - _Requirements: All_
  
  - [ ] 8.2 Run code formatting and linting
    - Run `vendor/bin/pint` for PHP files
    - Run `npm run lint` for TypeScript files
    - Fix any linting errors
    - _Requirements: All_
