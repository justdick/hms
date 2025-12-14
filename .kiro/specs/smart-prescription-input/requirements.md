# Requirements Document

## Introduction

This document specifies the requirements for a Smart Prescription Input feature that allows doctors to enter prescriptions using natural medical shorthand in a single text field. The feature provides a toggle between "Smart Mode" (text-based input with real-time parsing) and "Classic Mode" (existing dropdown-based form), enabling doctors to choose their preferred input method while maintaining full compatibility with the existing prescription and MAR workflows.

## Glossary

- **Smart Mode**: Text-based prescription input mode where doctors type prescriptions using medical abbreviations
- **Classic Mode**: The existing dropdown-based prescription form with frequency and duration selectors
- **Prescription Parser**: A backend service that interprets medical shorthand and extracts structured prescription data
- **Medical Shorthand**: Standard medical abbreviations for dosing (BD, TDS, OD, PRN, STAT, etc.)
- **Split Dose Pattern**: A dosing pattern where different quantities are taken at different times of day (e.g., 1-0-1 means 1 morning, skip noon, 1 evening)
- **Custom Interval Schedule**: A dosing schedule with specific hour intervals (e.g., 0h, 8h, 24h for antimalarials)
- **Taper Pattern**: A decreasing dose schedule over multiple days (e.g., 4-3-2-1)
- **MAR**: Medication Administration Record - the schedule of when medications should be given to admitted patients
- **Interpretation Panel**: A UI component that displays the parsed result of the smart input for doctor confirmation

## Requirements

### Requirement 1: Input Mode Toggle

**User Story:** As a doctor, I want to switch between Smart and Classic prescription input modes, so that I can use whichever method is faster for my workflow.

#### Acceptance Criteria

1. WHEN the prescription form section loads THEN the System SHALL display a toggle switch with "Smart" and "Classic" options at the top of the form
2. WHEN a doctor selects Classic mode THEN the System SHALL display the existing dropdown-based prescription form unchanged
3. WHEN a doctor selects Smart mode THEN the System SHALL display the drug selector followed by a single text input field for prescription entry
4. WHEN a doctor switches modes THEN the System SHALL preserve the selected drug but clear other form fields
5. WHEN the form is submitted successfully THEN the System SHALL maintain the current mode selection for subsequent prescriptions

### Requirement 2: Smart Input Text Field

**User Story:** As a doctor, I want to type prescriptions using medical shorthand in a single text field, so that I can enter prescriptions quickly without navigating multiple dropdowns.

#### Acceptance Criteria

1. WHEN Smart mode is active and a drug is selected THEN the System SHALL display a text input field with placeholder text showing example formats
2. WHEN a doctor types in the smart input field THEN the System SHALL parse the input in real-time and display the interpretation below
3. WHEN the input is valid THEN the System SHALL display a green-bordered interpretation panel showing parsed dose, frequency, duration, and calculated quantity
4. WHEN the input is invalid or incomplete THEN the System SHALL display a yellow-bordered panel with guidance on expected format
5. WHEN the input field is empty THEN the System SHALL display example patterns to guide the doctor

### Requirement 3: Standard Frequency Parsing

**User Story:** As a doctor, I want to enter standard frequencies using common medical abbreviations, so that I can prescribe medications using familiar notation.

#### Acceptance Criteria

1. WHEN a doctor enters "2 BD x 5 days" THEN the System SHALL parse it as 2 units, twice daily, for 5 days with total quantity of 20
2. WHEN a doctor enters "1 TDS x 7/7" THEN the System SHALL parse it as 1 unit, three times daily, for 7 days with total quantity of 21
3. WHEN a doctor enters "1 OD x 30 days" THEN the System SHALL parse it as 1 unit, once daily, for 30 days with total quantity of 30
4. WHEN a doctor enters "5ml TDS x 5 days" THEN the System SHALL parse it as 5ml dose, three times daily, for 5 days
5. WHEN a doctor enters frequency abbreviations (OD, BD, TDS, QDS, Q6H, Q8H, Q12H) THEN the System SHALL recognize and map them to standard frequency values
6. WHEN a doctor enters duration as "x N days", "x N/7", or "x N weeks" THEN the System SHALL parse the duration correctly

### Requirement 4: Split Dose Pattern Parsing

**User Story:** As a doctor, I want to enter split dose patterns like "1-0-1", so that I can prescribe medications with different doses at different times of day.

#### Acceptance Criteria

1. WHEN a doctor enters "1-0-1 x 30 days" THEN the System SHALL parse it as 1 morning, 0 noon, 1 evening for 30 days with total quantity of 60
2. WHEN a doctor enters "2-1-1 x 7 days" THEN the System SHALL parse it as 2 morning, 1 noon, 1 evening for 7 days with total quantity of 28
3. WHEN a doctor enters a split dose pattern THEN the System SHALL calculate total daily dose and total quantity correctly
4. WHEN a doctor enters a split dose pattern THEN the System SHALL store the pattern for MAR reference

### Requirement 5: Custom Interval Schedule Parsing

**User Story:** As a doctor, I want to enter custom interval schedules for antimalarials and other special medications, so that I can prescribe complex dosing regimens efficiently.

#### Acceptance Criteria

1. WHEN a doctor enters "4 tabs 0h,8h,24h,36h,48h,60h" THEN the System SHALL parse it as 4 tablets at each of 6 specified intervals with total quantity of 24
2. WHEN a doctor enters "0,8,24,36,48,60" with a dose THEN the System SHALL interpret the numbers as hour intervals
3. WHEN a doctor enters a custom interval schedule THEN the System SHALL store the intervals for MAR reference
4. WHEN a doctor enters a custom interval schedule THEN the System SHALL display the schedule clearly in the interpretation panel

### Requirement 6: Special Instruction Parsing

**User Story:** As a doctor, I want to enter special instructions like STAT and PRN, so that I can prescribe immediate or as-needed medications.

#### Acceptance Criteria

1. WHEN a doctor enters "STAT" THEN the System SHALL parse it as a single immediate dose
2. WHEN a doctor enters "PRN" THEN the System SHALL parse it as an as-needed medication without scheduled times
3. WHEN a doctor enters "2 PRN" THEN the System SHALL parse it as 2 units as needed
4. WHEN a doctor enters STAT or PRN THEN the System SHALL not require duration input

### Requirement 7: Taper Pattern Parsing

**User Story:** As a doctor, I want to enter taper patterns for medications like steroids, so that I can prescribe decreasing dose schedules.

#### Acceptance Criteria

1. WHEN a doctor enters "4-3-2-1 taper" THEN the System SHALL parse it as 4 units day 1, 3 units day 2, 2 units day 3, 1 unit day 4
2. WHEN a doctor enters a taper pattern THEN the System SHALL calculate total quantity as sum of all doses
3. WHEN a doctor enters a taper pattern THEN the System SHALL display the day-by-day schedule in the interpretation panel

### Requirement 8: Quantity Calculation

**User Story:** As a doctor, I want the system to automatically calculate the total quantity to dispense, so that I don't have to do mental math.

#### Acceptance Criteria

1. WHEN a valid prescription is parsed THEN the System SHALL calculate and display the total quantity to dispense
2. WHEN the selected drug is a tablet or capsule THEN the System SHALL calculate quantity as dose × frequency × duration
3. WHEN the selected drug is a syrup or liquid THEN the System SHALL calculate bottles needed based on ml per dose and bottle size
4. WHEN a doctor manually overrides the calculated quantity THEN the System SHALL use the override value

### Requirement 9: Interpretation Panel Display

**User Story:** As a doctor, I want to see a clear interpretation of my prescription input before submitting, so that I can verify the system understood my intent correctly.

#### Acceptance Criteria

1. WHEN a prescription is successfully parsed THEN the System SHALL display dose quantity, frequency description, duration, and total quantity
2. WHEN a prescription has a custom schedule THEN the System SHALL display the schedule times or intervals
3. WHEN a prescription is parsed THEN the System SHALL display the interpretation in a visually distinct panel
4. WHEN the interpretation is incorrect THEN the doctor SHALL be able to modify the input and see updated interpretation

### Requirement 10: Form Submission

**User Story:** As a doctor, I want to submit prescriptions from Smart mode using the same workflow as Classic mode, so that prescriptions are processed consistently.

#### Acceptance Criteria

1. WHEN a doctor submits a valid Smart mode prescription THEN the System SHALL store the same data fields as Classic mode (dose_quantity, frequency, duration, quantity_to_dispense)
2. WHEN a doctor submits a prescription with custom intervals THEN the System SHALL store the intervals in the schedule_pattern field
3. WHEN a doctor submits a prescription THEN the System SHALL create the prescription and trigger the same billing events as Classic mode
4. WHEN a prescription is submitted THEN the System SHALL clear the smart input field and maintain Smart mode for the next prescription

### Requirement 11: MAR Integration

**User Story:** As a ward staff member, I want prescriptions entered via Smart mode to work with the existing MAR workflow, so that I can configure administration schedules as usual.

#### Acceptance Criteria

1. WHEN a Smart mode prescription is created for an admitted patient THEN the System SHALL store frequency and schedule hints for MAR reference
2. WHEN ward staff configures MAR for a Smart mode prescription THEN the System SHALL display the original prescription pattern as a reference
3. WHEN a prescription has custom intervals THEN the System SHALL display those intervals as suggested times in MAR configuration
4. WHEN ward staff configures MAR THEN the System SHALL allow full control over actual administration times regardless of input mode

### Requirement 12: Error Handling

**User Story:** As a doctor, I want clear feedback when my prescription input cannot be parsed, so that I can correct it or switch to Classic mode.

#### Acceptance Criteria

1. WHEN the parser cannot interpret the input THEN the System SHALL display a helpful error message with format suggestions
2. WHEN the input is partially valid THEN the System SHALL indicate which parts were recognized and which need correction
3. WHEN parsing fails THEN the System SHALL provide a link to switch to Classic mode
4. WHEN the input contains ambiguous patterns THEN the System SHALL display the most likely interpretation with a note about the ambiguity

