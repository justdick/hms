# Insurance Tariff & Co-pay Implementation

## Overview

Enhanced the insurance coverage system to support flexible pricing with tariffs and co-payments, allowing hospitals to handle various insurance scenarios including negotiated pricing and additional patient charges.

## Features Implemented

### 1. Tariff Amount Support
- Added `tariff_amount` column to `insurance_coverage_rules` table
- Allows insurance companies to negotiate different prices from standard hospital pricing
- Optional field - when empty, system uses standard hospital price

### 2. Patient Co-pay Amount
- Added `patient_copay_amount` column to `insurance_coverage_rules` table
- Allows fixed additional charges to insured patients
- Applied per quantity (e.g., KES 2 per tablet)
- Works in addition to the percentage-based payment calculated from coverage_value
- Note: `patient_copay_percentage` field still exists but is now calculated automatically from `coverage_value` (100 - coverage_value)

### 3. Flexible Pricing Scenarios

The system now supports all these scenarios:

#### Scenario 1: Tariff-Based with Fixed Copay
```
Standard Price: KES 20
Tariff: KES 10 (insurance negotiated)
Coverage: 100%
Patient Copay: KES 15 (fixed)

Result:
- Insurance pays: KES 10
- Patient pays: KES 15
- Hospital gets: KES 25
```

#### Scenario 2: Standard Price with Percentage Split
```
Standard Price: KES 20
Tariff: (empty - use standard)
Coverage: 80%
Patient Copay %: 20%

Result:
- Insurance pays: KES 16 (80%)
- Patient pays: KES 4 (20%)
- Hospital gets: KES 20
```

#### Scenario 3: Standard Price + Percentage + Additional Copay
```
Standard Price: KES 20
Tariff: (empty)
Coverage: 80%
Patient Copay %: 20%
Patient Copay Amount: KES 5

Result:
- Insurance pays: KES 16 (80%)
- Patient pays: KES 4 (20%) + KES 5 (copay) = KES 9
- Hospital gets: KES 25
```

#### Scenario 4: Full Tariff Coverage
```
Standard Price: KES 20
Tariff: KES 8
Coverage: 100%
Patient Copay: KES 0

Result:
- Insurance pays: KES 8
- Patient pays: KES 0
- Hospital gets: KES 8
```

## Database Changes

### Migration
- File: `2025_11_20_233741_add_tariff_and_copay_to_insurance_coverage_rules_table.php`
- Added columns:
  - `tariff_amount` (decimal, nullable) - Insurance negotiated price
  - `patient_copay_amount` (decimal, default 0) - Fixed patient copay

### Model Updates
- Updated `InsuranceCoverageRule` model:
  - Added fields to `$fillable`
  - Added casts for decimal precision

## Service Layer Changes

### InsuranceCoverageService
Updated `calculateCoverage()` method to:
1. Check for `tariff_amount` in coverage rule first
2. Fall back to tariff table if no rule tariff
3. Use standard price if no tariff at all
4. Calculate patient payment as: percentage payment + fixed copay
5. Apply copay per quantity

## Excel Import/Export

### Template Updates
Updated `CoverageExceptionTemplate` export to include:
- `tariff_amount` column
- `patient_copay_amount` column
- Updated instructions with examples
- Column widths adjusted

### Import Logic
Updated `InsuranceCoverageImportController` to:
- Read `tariff_amount` from Excel (optional)
- Read `patient_copay_amount` from Excel (optional)
- Handle empty values correctly

## Excel Format

```
item_code | item_name   | current_price | coverage_type | coverage_value | tariff_amount | patient_copay_amount | notes
----------|-------------|---------------|---------------|----------------|---------------|---------------------|-------
PMOL      | Paracetamol | 20           | percentage    | 100           | 10            | 15                  | Tariff + copay
AMX500    | Amoxicillin | 20           | percentage    | 80            |               | 0                   | Standard split
MOR001    | Morphine    | 20           | percentage    | 80            |               | 5                   | Split + copay
```

## Testing

### New Tests
Created `InsuranceTariffAndCopayTest` with 5 test cases:
1. ✅ Uses tariff amount when set instead of standard price
2. ✅ Calculates tariff with fixed copay correctly
3. ✅ Calculates standard price with percentage split
4. ✅ Calculates standard price with percentage split plus additional copay
5. ✅ Applies copay per quantity

### Existing Tests
All 14 existing `InsuranceCoverageServiceTest` tests still pass ✅

## Factory Updates

Updated `InsuranceCoverageRuleFactory`:
- Added `tariff_amount` (default: null)
- Added `patient_copay_amount` (default: 0)
- Set `patient_copay_percentage` default to 0 (was random)
- Set `is_active` default to true
- Set `effective_from/to` to null for simpler testing

## Usage

### Setting Up Coverage Rules

1. **Via Excel Import**:
   - Download template from Insurance → Plans → Coverage Management
   - Fill in `tariff_amount` and `patient_copay_amount` columns
   - Import the file

2. **Via Code**:
```php
InsuranceCoverageRule::create([
    'insurance_plan_id' => $plan->id,
    'coverage_category' => 'drug',
    'item_code' => 'PMOL',
    'coverage_type' => 'percentage',
    'coverage_value' => 100,
    'tariff_amount' => 10.00,           // Optional
    'patient_copay_amount' => 15.00,    // Optional
]);
```

### Calculating Coverage

```php
$service = new InsuranceCoverageService();

$result = $service->calculateCoverage(
    insurancePlanId: $plan->id,
    category: 'drug',
    itemCode: 'PMOL',
    amount: 20.00,      // Standard price
    quantity: 2
);

// Returns:
// [
//     'insurance_tariff' => 10.00,
//     'insurance_pays' => 20.00,    // 10 * 2
//     'patient_pays' => 30.00,      // 15 * 2
//     'subtotal' => 20.00,
//     ...
// ]
```

## Benefits

1. **Flexibility**: Supports multiple pricing scenarios
2. **Revenue Protection**: Hospital can charge copays to cover tariff gaps
3. **Transparency**: Clear separation of tariff vs copay
4. **Backward Compatible**: Existing rules work without changes
5. **Per-Item Control**: Different tariffs/copays per drug/service

## Files Modified

1. `database/migrations/2025_11_20_233741_add_tariff_and_copay_to_insurance_coverage_rules_table.php`
2. `app/Models/InsuranceCoverageRule.php`
3. `app/Services/InsuranceCoverageService.php`
4. `app/Http/Controllers/Admin/InsuranceCoverageImportController.php`
5. `app/Exports/CoverageExceptionTemplate.php`
6. `database/factories/InsuranceCoverageRuleFactory.php`
7. `tests/Feature/Services/InsuranceTariffAndCopayTest.php` (new)

## Next Steps

Consider:
1. Update UI to show tariff and copay fields in coverage management
2. Add validation to prevent copay > standard price warnings
3. Add reporting to show tariff vs standard price differences
4. Document for end users in help section
