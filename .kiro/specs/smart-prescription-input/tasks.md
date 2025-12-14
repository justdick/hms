# Implementation Plan

- [x] 1. Create PrescriptionParserService






  - [x] 1.1 Create ParsedPrescriptionResult value object

    - Create `app/Services/Prescription/ParsedPrescriptionResult.php`
    - Define properties: isValid, doseQuantity, frequency, frequencyCode, duration, durationDays, quantityToDispense, scheduleType, schedulePattern, displayText, errors, warnings
    - Add static factory methods for creating valid/invalid results
    - _Requirements: 2.2, 2.3, 2.4_


  - [x] 1.2 Implement PrescriptionParserService core parsing

    - Create `app/Services/Prescription/PrescriptionParserService.php`
    - Implement `parse(string $input, ?Drug $drug = null): ParsedPrescriptionResult`
    - Implement `parseFrequency(string $input): ?array` for OD, BD, TDS, QDS, Q6H, Q8H, Q12H
    - Implement `parseDuration(string $input): ?array` for "x N days", "x N/7", "x N weeks"
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_


  - [x] 1.3 Write property test for frequency abbreviation mapping

    - **Property 2: Frequency abbreviation mapping consistency**
    - **Validates: Requirements 3.5**


  - [x] 1.4 Write property test for duration format parsing

    - **Property 3: Duration format parsing consistency**
    - **Validates: Requirements 3.6**



  - [x] 1.5 Implement split dose pattern parsing
    - Add `parseSplitDose(string $input): ?array` method
    - Parse patterns like "1-0-1", "2-1-1" with duration
    - Calculate daily total and total quantity
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

  - [x] 1.6 Write property test for split dose quantity calculation

    - **Property 4: Split dose quantity calculation**
    - **Validates: Requirements 4.3**


  - [x] 1.7 Implement custom interval schedule parsing
    - Add `parseCustomIntervals(string $input): ?array` method
    - Parse patterns like "4 tabs 0h,8h,24h,36h,48h,60h" and "0,8,24,36,48,60"
    - Calculate total doses and quantity
    - _Requirements: 5.1, 5.2, 5.3, 5.4_



  - [x] 1.8 Write property test for custom interval quantity calculation
    - **Property 5: Custom interval quantity calculation**
    - **Validates: Requirements 5.1, 5.3**


  - [x] 1.9 Implement taper pattern parsing

    - Add `parseTaper(string $input): ?array` method
    - Parse patterns like "4-3-2-1 taper"

    - Calculate total quantity as sum of doses
    - _Requirements: 7.1, 7.2, 7.3_


  - [x] 1.10 Write property test for taper quantity calculation
    - **Property 6: Taper quantity calculation**
    - **Validates: Requirements 7.2**

  - [x] 1.11 Implement STAT and PRN parsing

    - Add special case handling for STAT (single immediate dose)
    - Add special case handling for PRN (as needed, no schedule)
    - Handle "2 PRN" format for quantity with PRN
    - _Requirements: 6.1, 6.2, 6.3, 6.4_



  - [x] 1.12 Write property test for STAT/PRN duration requirement
    - **Property 11: STAT and PRN don't require duration**
    - **Validates: Requirements 6.4**

  - [x] 1.13 Implement quantity calculation
    - Add `calculateQuantity(ParsedPrescriptionResult $result, Drug $drug): int`
    - Handle piece-based drugs (tablets/capsules): dose × frequency × duration
    - Handle liquid drugs (bottles/vials): ceil(ml × frequency × duration / bottle_size)
    - _Requirements: 8.1, 8.2, 8.3_


  - [x] 1.14 Write property test for quantity calculation correctness

    - **Property 7: Quantity calculation correctness by drug type**
    - **Validates: Requirements 8.1, 8.2, 8.3**



  - [x] 1.15 Implement schedule pattern conversion
    - Add `toSchedulePattern(ParsedPrescriptionResult $result): ?array`
    - Convert split dose, custom intervals, and taper to JSON structure
    - _Requirements: 4.4, 5.3, 11.1_

  - [x] 1.16 Write property test for schedule pattern storage
    - **Property 12: Schedule pattern storage for MAR**
    - **Validates: Requirements 4.4, 5.3, 11.1**

  - [x] 1.17 Implement format method for display
    - Add `format(ParsedPrescriptionResult $result): string`
    - Generate human-readable display text from parsed result
    - _Requirements: 9.1, 9.2_

  - [x] 1.18 Write property test for parsing round-trip

    - **Property 9: Parsing round-trip consistency**
    - **Validates: Requirements 2.2, 9.1**


  - [x] 1.19 Implement error handling and feedback
    - Return helpful error messages for unrecognized formats
    - Indicate which parts were recognized for partial matches
    - Add warnings for ambiguous patterns
    - _Requirements: 12.1, 12.2, 12.4_


  - [x] 1.20 Write property test for invalid input feedback

    - **Property 10: Invalid input produces helpful feedback**
    - **Validates: Requirements 12.1, 12.2, 12.4**

- [x] 2. Checkpoint - Make sure all tests are passing





  - Ensure all tests pass, ask the user if questions arise.

- [x] 3. Create API endpoint for real-time parsing






  - [x] 3.1 Create ParsePrescriptionRequest form request

    - Create `app/Http/Requests/Prescription/ParsePrescriptionRequest.php`
    - Validate input string (required, max length)
    - Validate drug_id (optional, exists in drugs table)
    - _Requirements: 2.2_


  - [x] 3.2 Add parse endpoint to prescription routes

    - Add `POST /api/prescriptions/parse` route
    - Create controller method in appropriate controller
    - Return ParsedPrescriptionResult as JSON
    - _Requirements: 2.2_


  - [x] 3.3 Write feature test for parse endpoint

    - Test valid inputs return parsed result
    - Test invalid inputs return error feedback
    - Test with and without drug_id
    - _Requirements: 2.2, 2.3, 2.4_

- [x] 4. Create frontend components





  - [x] 4.1 Create ModeToggle component
    - Create `resources/js/components/Prescription/ModeToggle.tsx`
    - Implement toggle switch with "Smart" and "Classic" labels
    - Style with Tailwind, support dark mode
    - _Requirements: 1.1, 1.2, 1.3_


  - [x] 4.2 Create InterpretationPanel component

    - Create `resources/js/components/Prescription/InterpretationPanel.tsx`
    - Display parsed dose, frequency, duration, quantity
    - Show schedule details for custom patterns
    - Green border for valid, yellow for warnings/partial, red for errors
    - Include "Switch to Classic Mode" link when parsing fails
    - _Requirements: 2.3, 2.4, 9.1, 9.2, 9.3, 12.3_


  - [x] 4.3 Create SmartPrescriptionInput component

    - Create `resources/js/components/Prescription/SmartPrescriptionInput.tsx`
    - Text input field with placeholder showing example formats
    - Debounced API call to parse endpoint on input change
    - Pass parsed result to parent via callback
    - Show example patterns when empty
    - _Requirements: 2.1, 2.2, 2.5_


  - [x] 4.4 Create usePrescriptionParser hook

    - Create `resources/js/hooks/usePrescriptionParser.ts`
    - Handle debounced API calls to parse endpoint
    - Manage loading state during parsing
    - Cache recent parse results
    - _Requirements: 2.2_

- [x] 5. Integrate Smart mode into PrescriptionFormSection





  - [x] 5.1 Add mode state and toggle to PrescriptionFormSection


    - Add `mode` state ('smart' | 'classic')
    - Render ModeToggle at top of form
    - Preserve drug selection when switching modes
    - Clear other fields when switching modes
    - _Requirements: 1.1, 1.2, 1.3, 1.4_


  - [x] 5.2 Write property test for mode switching preserves drug

    - **Property 1: Mode switching preserves drug selection**
    - **Validates: Requirements 1.4**


  - [x] 5.3 Implement Smart mode form rendering

    - Conditionally render SmartPrescriptionInput when mode is 'smart'
    - Render InterpretationPanel below input
    - Keep drug selector and instructions field
    - Hide frequency/duration dropdowns in Smart mode
    - _Requirements: 1.3, 2.1_

  - [x] 5.4 Implement Smart mode form submission


    - Extract parsed values from SmartPrescriptionInput result
    - Map to same form data structure as Classic mode
    - Include schedule_pattern for custom schedules
    - Submit using existing form submission logic
    - _Requirements: 10.1, 10.2, 10.3, 10.4_

  - [x] 5.5 Write property test for Smart mode data structure


    - **Property 8: Smart mode produces same data structure as Classic mode**
    - **Validates: Requirements 10.1**

  - [x] 5.6 Maintain mode after successful submission


    - Keep mode state after prescription is added
    - Clear smart input field after submission
    - _Requirements: 1.5, 10.4_

- [x] 6. Checkpoint - Make sure all tests are passing





  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Update backend prescription handling





  - [x] 7.1 Update StorePrescriptionRequest for Smart mode


    - Add `smart_input` optional field
    - Add `use_smart_mode` boolean field
    - Conditional validation: if use_smart_mode, parse smart_input instead of requiring frequency/duration
    - _Requirements: 10.1_


  - [x] 7.2 Update prescription store logic in controllers

    - Check if `use_smart_mode` is true
    - Parse smart_input using PrescriptionParserService
    - Map parsed result to prescription fields
    - Store schedule_pattern for custom schedules
    - Existing Classic mode flow unchanged
    - _Requirements: 10.1, 10.2, 10.3_


  - [x] 7.3 Write feature test for Smart mode prescription creation

    - Test creating prescription with smart input
    - Test schedule_pattern is stored correctly
    - Test billing events are triggered
    - _Requirements: 10.1, 10.2, 10.3_

- [x] 8. Update MAR display for Smart mode prescriptions






  - [x] 8.1 Display original prescription pattern in MAR

    - Show smart input pattern as reference when configuring MAR
    - Display custom intervals as suggested times
    - _Requirements: 11.2, 11.3_



  - [x] 8.2 Ensure MAR configuration works with Smart mode prescriptions
    - Verify ward staff can configure any schedule regardless of input mode
    - Test MAR generation for custom interval prescriptions
    - _Requirements: 11.4_

- [x] 9. Add Smart mode to Ward Round prescription form






  - [x] 9.1 Integrate mode toggle and Smart input into Ward Round form

    - Apply same changes as Consultation prescription form
    - Reuse SmartPrescriptionInput and InterpretationPanel components
    - _Requirements: 1.1, 1.2, 1.3_


  - [x] 9.2 Update Ward Round prescription submission

    - Handle smart_input in Ward Round controller
    - Same parsing and storage logic as Consultation
    - _Requirements: 10.1, 10.2_

- [x] 10. Final Checkpoint - Make sure all tests are passing





  - Ensure all tests pass, ask the user if questions arise.

