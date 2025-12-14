# Requirements Document

## Introduction

This document specifies the requirements for comprehensive testing of prescription quantity calculations across all drug forms in the Hospital Management System. The feature ensures that the Smart Prescription Input correctly calculates dispensing quantities based on dose, frequency, and duration for all medication types.

## Glossary

- **Piece-Based Drug**: Medications dispensed as discrete units (tablets, capsules, vials, suppositories, sachets, lozenges, pessaries, enemas, IV bags)
- **Volume-Based Drug**: Medications dispensed in bottles measured by volume (syrups, suspensions)
- **Interval-Based Drug**: Medications changed at fixed intervals (transdermal patches)
- **Fixed-Unit Drug**: Medications typically dispensed as single units regardless of duration (topicals, drops, inhalers, combination packs)
- **Bottle Size**: The volume capacity of a liquid medication container
- **Frequency Multiplier**: Number of doses per day based on frequency code (OD=1, BD=2, TDS=3, QDS=4, Q6H=4, Q8H=3, Q12H=2)
- **Quantity to Dispense**: Total units needed for the complete prescription course

## Requirements

### Requirement 1: Piece-Based Drug Quantity Calculation

**User Story:** As a doctor, I want piece-based medication quantities calculated using dose × frequency × days, so that patients receive the exact number of units needed.

#### Acceptance Criteria

1. WHEN a piece-based drug is prescribed N units at frequency F for D days THEN the System SHALL calculate quantity as N × frequency_multiplier × D
2. WHEN frequency is OD THEN the System SHALL use multiplier of 1
3. WHEN frequency is BD THEN the System SHALL use multiplier of 2
4. WHEN frequency is TDS THEN the System SHALL use multiplier of 3
5. WHEN frequency is QDS THEN the System SHALL use multiplier of 4
6. WHEN frequency is Q6H THEN the System SHALL use multiplier of 4
7. WHEN frequency is Q8H THEN the System SHALL use multiplier of 3
8. WHEN frequency is Q12H THEN the System SHALL use multiplier of 2

9. WHEN the drug form is tablet, capsule, suppository, sachet, nebulizer vial, lozenge, pessary, enema, injection vial, ampoule, or IV bag THEN the System SHALL apply piece-based calculation

### Requirement 2: Volume-Based Drug Quantity Calculation

**User Story:** As a doctor, I want syrup and suspension quantities calculated in bottles based on total ml needed, so that patients receive enough medication.

#### Acceptance Criteria

1. WHEN a volume-based drug is prescribed M ml at frequency F for D days THEN the System SHALL calculate total ml as M × frequency_multiplier × D
2. WHEN total ml is calculated THEN the System SHALL divide by bottle_size and round up (ceiling) to get bottles needed
3. WHEN bottle_size is 100ml and total ml is 105 THEN the System SHALL dispense 2 bottles
4. WHEN bottle_size is 125ml and total ml is 75 THEN the System SHALL dispense 1 bottle
5. WHEN the drug form is syrup or suspension THEN the System SHALL apply volume-based calculation
6. WHEN the drug has no bottle_size defined THEN the System SHALL default to 1 bottle or prompt for manual entry

### Requirement 3: Interval-Based Drug Quantity Calculation

**User Story:** As a doctor, I want transdermal patch quantities calculated based on change frequency, so that patients have patches for the full treatment course.

#### Acceptance Criteria

1. WHEN a patch is prescribed with change interval I days for D days duration THEN the System SHALL calculate quantity as ceiling(D ÷ I)
2. WHEN patch is changed every 3 days for 30 days THEN the System SHALL dispense 10 patches
3. WHEN patch is changed weekly for 4 weeks THEN the System SHALL dispense 4 patches
4. WHEN the drug form is patch THEN the System SHALL apply interval-based calculation

### Requirement 4: Fixed-Unit Drug Quantity Calculation

**User Story:** As a doctor, I want topicals, drops, inhalers, and combination packs dispensed as appropriate unit quantities, so that treatments are complete.

#### Acceptance Criteria

1. WHEN a cream, ointment, or gel is prescribed THEN the System SHALL default to 1 tube
2. WHEN eye or ear drops are prescribed (e.g., "2 QDS x 7 days" with a drops drug selected) THEN the System SHALL default to 1 bottle since standard bottles contain sufficient drops for typical courses
8. WHEN a drops drug is selected and input is "2 QDS x 7 days" THEN the System SHALL interpret "2" as drops per application (not bottles) and dispense 1 bottle
3. WHEN an inhaler is prescribed THEN the System SHALL default to 1 device
4. WHEN a combination pack (e.g., antimalarial blister pack) is prescribed THEN the System SHALL dispense whole packs
5. WHEN the drug form is cream, ointment, gel, drops, inhaler, or combination pack THEN the System SHALL apply fixed-unit calculation
6. WHEN extended duration requires more units THEN the System SHALL allow manual quantity override
7. WHEN drops are prescribed for bilateral use (both eyes/ears) for extended duration THEN the System SHALL allow quantity adjustment

### Requirement 5: Split Dose Pattern Quantity Calculation

**User Story:** As a doctor, I want split dose patterns (1-0-1, 2-1-1) to calculate total daily dose correctly, so that quantities are accurate.

#### Acceptance Criteria

1. WHEN a split dose pattern A-B-C is specified for D days THEN the System SHALL calculate quantity as (A + B + C) × D
2. WHEN pattern is 1-0-1 for 30 days THEN the System SHALL calculate quantity as 60
3. WHEN pattern is 2-1-1 for 7 days THEN the System SHALL calculate quantity as 28
4. WHEN pattern is 1-1-1 for 30 days THEN the System SHALL calculate quantity as 90

### Requirement 6: STAT Dose Quantity Calculation

**User Story:** As a doctor, I want STAT doses to dispense only the immediate dose amount, so that single doses are handled correctly.

#### Acceptance Criteria

1. WHEN a prescription specifies N units STAT THEN the System SHALL calculate quantity as N
2. WHEN STAT is specified THEN the System SHALL not require duration
3. WHEN 2 tablets STAT is prescribed THEN the System SHALL dispense 2 tablets

### Requirement 7: PRN Quantity Calculation

**User Story:** As a doctor, I want PRN prescriptions to calculate reasonable quantities based on maximum daily usage.

#### Acceptance Criteria

1. WHEN PRN is specified with duration and max daily THEN the System SHALL calculate as max_daily × duration_days
2. WHEN PRN is specified without duration THEN the System SHALL prompt for quantity or use reasonable default
3. WHEN PRN with "max 8/24h x 7 days" is specified THEN the System SHALL calculate quantity as 56

### Requirement 8: Taper Pattern Quantity Calculation

**User Story:** As a doctor, I want taper patterns to sum all doses across all days, so that steroid tapers have correct quantities.

#### Acceptance Criteria

1. WHEN a taper pattern [D1, D2, D3, ...Dn] is specified THEN the System SHALL calculate quantity as sum of all doses
2. WHEN pattern is 4-3-2-1 taper THEN the System SHALL calculate quantity as 10
3. WHEN extended taper with phases is specified THEN the System SHALL sum all phases

### Requirement 9: Custom Interval Schedule Quantity Calculation

**User Story:** As a doctor, I want custom interval schedules (like antimalarial 0h, 8h, 24h, 36h, 48h, 60h) to calculate total doses correctly.

#### Acceptance Criteria

1. WHEN custom intervals are specified with N dose per interval and M intervals THEN the System SHALL calculate quantity as N × M
2. WHEN antimalarial is prescribed 4 tabs at 0h, 8h, 24h, 36h, 48h, 60h THEN the System SHALL dispense 24 tablets
3. WHEN custom intervals are specified THEN the System SHALL store the interval pattern for MAR reference

### Requirement 10: UI Verification via Playwright

**User Story:** As a QA engineer, I want automated browser tests to verify quantity calculations in the actual UI, so that end-to-end correctness is validated.

#### Acceptance Criteria

1. WHEN a test scenario is executed THEN the Playwright test SHALL navigate to consultation prescription form
2. WHEN smart input is entered THEN the Playwright test SHALL verify the interpretation panel shows correct quantity
3. WHEN prescription is submitted THEN the Playwright test SHALL verify the prescription list shows correct quantity
4. WHEN multiple drug forms are tested THEN the Playwright test SHALL cover all calculation types (piece-based, volume-based, interval-based, fixed-unit)
5. WHEN edge cases are tested THEN the Playwright test SHALL verify error handling for invalid inputs
