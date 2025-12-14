# Smart Prescription Input - Test Scenarios Spec

This document defines comprehensive test scenarios for the Smart Prescription Input feature, covering all drug forms, frequency patterns, and edge cases.

## Drug Forms to Test

| Form | Unit Type | Example Drug | Quantity Calculation |
|------|-----------|--------------|---------------------|
| tablet | piece | Amlodipine 5mg | dose × frequency × days |
| capsule | piece | Amoxicillin 500mg | dose × frequency × days |
| syrup | bottle | Paracetamol Syrup 120mg/5mL | ceil(ml × frequency × days / bottle_size) |
| suspension | bottle | Amoxicillin Suspension 125mg/5mL | ceil(ml × frequency × days / bottle_size) |
| injection | vial/ampoule | Ceftriaxone 1g | dose × frequency × days |
| cream | tube | Clotrimazole 1% | typically 1 tube |
| ointment | tube | Tetracycline Eye Ointment 1% | typically 1 tube |
| drops | bottle | Chloramphenicol Eye Drops 0.5% | typically 1 bottle |
| inhaler | device | Salbutamol Inhaler | typically 1 inhaler |

---

## Test Scenarios

### Category 1: Standard Frequencies (Tablets)

#### Test 1.1: Once Daily (OD)
- **Drug**: Amlodipine Tablet, 5 mg
- **Input**: `1 OD x 30 days`
- **Expected**:
  - Dose: 1 tablet(s)
  - Frequency: Once daily (OD)
  - Duration: 30 days
  - Quantity: 30 tablets

#### Test 1.2: Twice Daily (BD)
- **Drug**: Metformin Tablet, 500 mg
- **Input**: `1 BD x 30 days`
- **Expected**:
  - Dose: 1 tablet(s)
  - Frequency: Twice daily (BD)
  - Duration: 30 days
  - Quantity: 60 tablets

#### Test 1.3: Three Times Daily (TDS)
- **Drug**: Paracetamol Tablet, 500 mg
- **Input**: `2 TDS x 5 days`
- **Expected**:
  - Dose: 2 tablet(s)
  - Frequency: Three times daily (TDS)
  - Duration: 5 days
  - Quantity: 30 tablets

#### Test 1.4: Four Times Daily (QDS)
- **Drug**: Ibuprofen Tablet, 400 mg
- **Input**: `1 QDS x 3 days`
- **Expected**:
  - Dose: 1 tablet(s)
  - Frequency: Four times daily (QDS)
  - Duration: 3 days
  - Quantity: 12 tablets

#### Test 1.5: Every 6 Hours (Q6H)
- **Drug**: Amoxicillin Tablet (if available) or Paracetamol
- **Input**: `1 Q6H x 5 days`
- **Expected**:
  - Dose: 1 tablet(s)
  - Frequency: Every 6 hours (Q6H)
  - Duration: 5 days
  - Quantity: 20 tablets (4 times/day × 5 days)

#### Test 1.6: Every 8 Hours (Q8H)
- **Drug**: Metronidazole Tablet, 400 mg
- **Input**: `1 Q8H x 7 days`
- **Expected**:
  - Dose: 1 tablet(s)
  - Frequency: Every 8 hours (Q8H)
  - Duration: 7 days
  - Quantity: 21 tablets (3 times/day × 7 days)

#### Test 1.7: Every 12 Hours (Q12H)
- **Drug**: Ciprofloxacin Tablet, 500 mg
- **Input**: `1 Q12H x 7 days`
- **Expected**:
  - Dose: 1 tablet(s)
  - Frequency: Every 12 hours (Q12H)
  - Duration: 7 days
  - Quantity: 14 tablets (2 times/day × 7 days)

---

### Category 2: Capsules

#### Test 2.1: Capsule BD
- **Drug**: Amoxicillin Capsule, 500 mg
- **Input**: `1 BD x 7 days`
- **Expected**:
  - Dose: 1 capsule(s)
  - Frequency: Twice daily (BD)
  - Duration: 7 days
  - Quantity: 14 capsules

#### Test 2.2: Capsule TDS
- **Drug**: Flucloxacillin Capsule, 250 mg
- **Input**: `2 TDS x 5 days`
- **Expected**:
  - Dose: 2 capsule(s)
  - Frequency: Three times daily (TDS)
  - Duration: 5 days
  - Quantity: 30 capsules

#### Test 2.3: Capsule OD
- **Drug**: Omeprazole Capsule (Esomeprazole Capsule, 20 mg)
- **Input**: `1 OD x 14 days`
- **Expected**:
  - Dose: 1 capsule(s)
  - Frequency: Once daily (OD)
  - Duration: 14 days
  - Quantity: 14 capsules

---

### Category 3: Syrups/Liquids (Bottle Calculation)

#### Test 3.1: Syrup TDS - Single Bottle
- **Drug**: Paracetamol Syrup, 120 mg/5 mL (125 mL bottle)
- **Input**: `5ml TDS x 5 days`
- **Expected**:
  - Dose: 5 ml
  - Frequency: Three times daily (TDS)
  - Duration: 5 days
  - Total ml: 75ml (5 × 3 × 5)
  - Quantity: 1 bottle (75ml < 125ml)

#### Test 3.2: Syrup TDS - Multiple Bottles
- **Drug**: Paracetamol Syrup, 120 mg/5 mL (125 mL bottle)
- **Input**: `10ml TDS x 7 days`
- **Expected**:
  - Dose: 10 ml
  - Frequency: Three times daily (TDS)
  - Duration: 7 days
  - Total ml: 210ml (10 × 3 × 7)
  - Quantity: 2 bottles (210ml / 125ml = 1.68, ceil = 2)

#### Test 3.3: Syrup BD
- **Drug**: Salbutamol Syrup, 2 mg/5 mL (200 mL bottle)
- **Input**: `5ml BD x 14 days`
- **Expected**:
  - Dose: 5 ml
  - Frequency: Twice daily (BD)
  - Duration: 14 days
  - Total ml: 140ml (5 × 2 × 14)
  - Quantity: 1 bottle (140ml < 200ml)

#### Test 3.4: Suspension TDS
- **Drug**: Amoxicillin Suspension, 125 mg/5 mL (100 mL bottle)
- **Input**: `5ml TDS x 7 days`
- **Expected**:
  - Dose: 5 ml
  - Frequency: Three times daily (TDS)
  - Duration: 7 days
  - Total ml: 105ml (5 × 3 × 7)
  - Quantity: 2 bottles (105ml / 100ml = 1.05, ceil = 2)

---

### Category 4: Injections

#### Test 4.1: Injection OD
- **Drug**: Ceftriaxone Injection, 1g
- **Input**: `1 OD x 5 days`
- **Expected**:
  - Dose: 1 vial(s)
  - Frequency: Once daily (OD)
  - Duration: 5 days
  - Quantity: 5 vials

#### Test 4.2: Injection BD
- **Drug**: Gentamicin Injection, 40 mg/mL
- **Input**: `1 BD x 7 days`
- **Expected**:
  - Dose: 1 ampoule(s)
  - Frequency: Twice daily (BD)
  - Duration: 7 days
  - Quantity: 14 ampoules

#### Test 4.3: Injection STAT
- **Drug**: Hydrocortisone Injection, 100 mg
- **Input**: `1 STAT`
- **Expected**:
  - Dose: 1 vial(s)
  - Frequency: Immediately (STAT)
  - Duration: Single dose
  - Quantity: 1 vial

---

### Category 5: Split Dose Patterns

#### Test 5.1: Split Dose 1-0-1
- **Drug**: Prednisolone Tablet, 5 mg
- **Input**: `1-0-1 x 7 days`
- **Expected**:
  - Dose: 1-0-1 tablet(s)
  - Frequency: Split dose (morning-noon-evening)
  - Duration: 7 days
  - Quantity: 14 tablets (2 per day × 7 days)

#### Test 5.2: Split Dose 2-1-1
- **Drug**: Prednisolone Tablet, 5 mg
- **Input**: `2-1-1 x 5 days`
- **Expected**:
  - Dose: 2-1-1 tablet(s)
  - Frequency: Split dose
  - Duration: 5 days
  - Quantity: 20 tablets (4 per day × 5 days)

#### Test 5.3: Split Dose 1-1-1
- **Drug**: Metformin Tablet, 500 mg
- **Input**: `1-1-1 x 30 days`
- **Expected**:
  - Dose: 1-1-1 tablet(s)
  - Frequency: Split dose (equivalent to TDS)
  - Duration: 30 days
  - Quantity: 90 tablets (3 per day × 30 days)

#### Test 5.4: Split Dose 2-2-2
- **Drug**: Paracetamol Tablet, 500 mg
- **Input**: `2-2-2 x 3 days`
- **Expected**:
  - Dose: 2-2-2 tablet(s)
  - Frequency: Split dose
  - Duration: 3 days
  - Quantity: 18 tablets (6 per day × 3 days)

---

### Category 6: STAT (Immediate) Doses

#### Test 6.1: Tablet STAT
- **Drug**: Furosemide Tablet, 40 mg
- **Input**: `2 STAT`
- **Expected**:
  - Dose: 2 tablet(s)
  - Frequency: Immediately (STAT)
  - Duration: Single dose
  - Quantity: 2 tablets

#### Test 6.2: Injection STAT
- **Drug**: Diazepam Injection, 5 mg/mL
- **Input**: `1 STAT`
- **Expected**:
  - Dose: 1 ampoule(s)
  - Frequency: Immediately (STAT)
  - Duration: Single dose
  - Quantity: 1 ampoule

---

### Category 7: PRN (As Needed)

#### Test 7.1: PRN Basic
- **Drug**: Paracetamol Tablet, 500 mg
- **Input**: `2 PRN`
- **Expected**:
  - Dose: 2 tablet(s)
  - Frequency: As needed (PRN)
  - Duration: As needed
  - Quantity: (user may need to specify or default)

#### Test 7.2: PRN with Max Daily
- **Drug**: Tramadol Capsule, 50mg
- **Input**: `1-2 PRN pain max 8/24h x 7 days`
- **Expected**:
  - Dose: 1-2 capsule(s)
  - Frequency: As needed (PRN)
  - Duration: 7 days
  - Quantity: 56 capsules (8 max per day × 7 days)

#### Test 7.3: PRN with Duration
- **Drug**: Ibuprofen Tablet, 400 mg
- **Input**: `1 PRN x 5 days`
- **Expected**:
  - Dose: 1 tablet(s)
  - Frequency: As needed (PRN)
  - Duration: 5 days
  - Quantity: (calculated based on reasonable max)

---

### Category 8: Taper Patterns

#### Test 8.1: Simple Taper
- **Drug**: Prednisolone Tablet, 5 mg
- **Input**: `4-3-2-1 taper`
- **Expected**:
  - Dose: Taper schedule
  - Frequency: Taper
  - Duration: 4 days
  - Quantity: 10 tablets (4+3+2+1)

#### Test 8.2: Extended Taper
- **Drug**: Prednisolone Tablet, 5 mg
- **Input**: `4 OD x 3/7, then 2 OD x 3/7, then 1 OD x 3/7`
- **Expected**:
  - Dose: Taper schedule
  - Frequency: Taper
  - Duration: 9 days
  - Quantity: 21 tablets (4×3 + 2×3 + 1×3)

---

### Category 9: Duration Format Variations

#### Test 9.1: Days Format
- **Drug**: Amoxicillin Capsule, 500 mg
- **Input**: `1 TDS x 7 days`
- **Expected**: Duration: 7 days, Quantity: 21

#### Test 9.2: Shorthand /7 Format
- **Drug**: Amoxicillin Capsule, 500 mg
- **Input**: `1 TDS x 7/7`
- **Expected**: Duration: 7 days, Quantity: 21

#### Test 9.3: Weeks Format
- **Drug**: Amlodipine Tablet, 5 mg
- **Input**: `1 OD x 4 weeks`
- **Expected**: Duration: 28 days, Quantity: 28

#### Test 9.4: Month Format (if supported)
- **Drug**: Amlodipine Tablet, 5 mg
- **Input**: `1 OD x 1 month`
- **Expected**: Duration: 30 days, Quantity: 30

---

### Category 10: Topical Preparations

#### Test 10.1: Cream
- **Drug**: Clotrimazole Cream, 1%
- **Input**: `apply BD x 7 days`
- **Expected**:
  - Dose: Apply
  - Frequency: Twice daily (BD)
  - Duration: 7 days
  - Quantity: 1 tube

#### Test 10.2: Ointment
- **Drug**: Tetracycline Eye Ointment, 1%
- **Input**: `apply TDS x 5 days`
- **Expected**:
  - Dose: Apply
  - Frequency: Three times daily (TDS)
  - Duration: 5 days
  - Quantity: 1 tube

#### Test 10.3: Eye Drops
- **Drug**: Chloramphenicol Eye Drops, 0.5%
- **Input**: `1 drop QDS x 7 days`
- **Expected**:
  - Dose: 1 drop
  - Frequency: Four times daily (QDS)
  - Duration: 7 days
  - Quantity: 1 bottle

---

### Category 11: Inhalers

#### Test 11.1: Inhaler BD
- **Drug**: Salbutamol Inhaler, 100 microgram
- **Input**: `2 puffs BD x 30 days`
- **Expected**:
  - Dose: 2 puffs
  - Frequency: Twice daily (BD)
  - Duration: 30 days
  - Quantity: 1 inhaler (typically 200 doses)

#### Test 11.2: Inhaler PRN
- **Drug**: Salbutamol Inhaler, 100 microgram
- **Input**: `2 puffs PRN`
- **Expected**:
  - Dose: 2 puffs
  - Frequency: As needed (PRN)
  - Quantity: 1 inhaler

---

### Category 12: Edge Cases & Error Handling

#### Test 12.1: Empty Input
- **Input**: (empty)
- **Expected**: Validation error or guidance message

#### Test 12.2: Invalid Pattern
- **Input**: `random text here`
- **Expected**: Error with helpful message suggesting valid formats

#### Test 12.3: Missing Duration
- **Input**: `2 BD`
- **Expected**: Warning about missing duration or prompt to add

#### Test 12.4: Zero Dose
- **Input**: `0 BD x 5 days`
- **Expected**: Error - dose must be positive

#### Test 12.5: Negative Duration
- **Input**: `2 BD x -5 days`
- **Expected**: Error - duration must be positive

#### Test 12.6: Case Insensitivity
- **Input**: `2 bd x 5 DAYS`
- **Expected**: Should parse correctly (case insensitive)

#### Test 12.7: Extra Whitespace
- **Input**: `  2   BD   x   5   days  `
- **Expected**: Should parse correctly (trim whitespace)

---

## Test Execution Checklist

### Pre-Test Setup
- [ ] Navigate to consultation page with active consultation
- [ ] Switch to Prescriptions tab
- [ ] Enable Smart mode toggle

### For Each Test Scenario
1. [ ] Select the specified drug from dropdown
2. [ ] Enter the input pattern in smart input field
3. [ ] Verify interpretation panel shows correct:
   - [ ] Dose quantity
   - [ ] Frequency description
   - [ ] Duration
   - [ ] Calculated quantity
4. [ ] Click "Add Prescription" button
5. [ ] Verify prescription appears in list
6. [ ] Verify success notification

### Post-Test Verification
- [ ] All prescriptions saved correctly
- [ ] Quantities calculated accurately
- [ ] No console errors
- [ ] Form resets properly after each submission

---

## Notes

- Bottle size calculations should use `ceil()` to round up
- STAT and PRN may not require duration
- Split dose patterns should sum all doses for daily total
- Taper patterns should sum all doses across all days
- Topical preparations typically dispense 1 unit regardless of duration
