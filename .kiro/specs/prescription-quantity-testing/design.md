# Prescription Quantity Testing - Design Document

## Overview

This design document specifies the testing strategy for comprehensive verification of prescription quantity calculations across all drug forms. The testing approach combines property-based tests for calculation logic and Playwright browser tests for end-to-end UI verification.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Testing Architecture                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              Property-Based Tests (Pest)                 │   │
│  │                                                          │   │
│  │  • PrescriptionParserService unit tests                  │   │
│  │  • Quantity calculation verification                     │   │
│  │  • 100+ iterations per property                          │   │
│  │  • Covers all drug forms and patterns                    │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│                              ▼                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              Playwright Browser Tests                    │   │
│  │                                                          │   │
│  │  • End-to-end UI verification                            │   │
│  │  • Smart input → Interpretation panel → Submission       │   │
│  │  • Real drug selection and form interaction              │   │
│  │  • Visual verification of calculated quantities          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### Test Data Generators

```php
// Drug form generators for property tests
class DrugFormGenerator
{
    public static function pieceBasedDrug(): Drug;      // tablet, capsule, vial, etc.
    public static function volumeBasedDrug(): Drug;     // syrup, suspension
    public static function intervalBasedDrug(): Drug;   // patch
    public static function fixedUnitDrug(): Drug;       // cream, drops, inhaler
}


// Prescription input generators
class PrescriptionInputGenerator
{
    public static function standardFrequency(): array;   // OD, BD, TDS, QDS, Q6H, Q8H, Q12H
    public static function splitDosePattern(): array;    // 1-0-1, 2-1-1, etc.
    public static function taperPattern(): array;        // 4-3-2-1, etc.
    public static function customIntervals(): array;     // 0h, 8h, 24h, etc.
}
```

### Playwright Test Helpers

```typescript
// Browser test utilities
interface PrescriptionTestHelpers {
    navigateToConsultation(patientId: number): Promise<void>;
    selectDrug(drugName: string): Promise<void>;
    enterSmartInput(input: string): Promise<void>;
    getInterpretedQuantity(): Promise<number>;
    submitPrescription(): Promise<void>;
    verifyPrescriptionInList(expectedQuantity: number): Promise<void>;
}
```

## Data Models

### Test Drug Fixtures

| Drug Name | Form | Unit | Bottle Size | Notes |
|-----------|------|------|-------------|-------|
| Amlodipine 5mg | tablet | tablet | - | Piece-based |
| Amoxicillin 500mg | capsule | capsule | - | Piece-based |
| Paracetamol Syrup 120mg/5ml | syrup | ml | 125ml | Volume-based |
| Amoxicillin Suspension 125mg/5ml | suspension | ml | 100ml | Volume-based |
| Ceftriaxone 1g | injection | vial | - | Piece-based |
| Fentanyl Patch 25mcg/hr | patch | patch | - | Interval-based |
| Clotrimazole Cream 1% | cream | tube | - | Fixed-unit |
| Chloramphenicol Eye Drops 0.5% | drops | bottle | - | Fixed-unit |
| Salbutamol Inhaler 100mcg | inhaler | device | - | Fixed-unit |
| Prednisolone 5mg | tablet | tablet | - | For tapers |
| Coartem (AL) | combination | pack | - | Fixed-unit |

### Frequency Multiplier Map

| Code | Description | Multiplier |
|------|-------------|------------|
| OD | Once daily | 1 |
| BD | Twice daily | 2 |
| TDS | Three times daily | 3 |
| QDS | Four times daily | 4 |
| Q6H | Every 6 hours | 4 |
| Q8H | Every 8 hours | 3 |
| Q12H | Every 12 hours | 2 |

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Piece-based quantity calculation
*For any* piece-based drug (tablet, capsule, suppository, sachet, vial, lozenge, pessary, enema, IV bag) with dose N, frequency F, and duration D days, the quantity should equal N × frequency_multiplier(F) × D.
**Validates: Requirements 1.1, 1.9**

### Property 2: Frequency multiplier consistency
*For any* frequency code in {OD, BD, TDS, QDS, Q6H, Q8H, Q12H}, the parser should return a consistent multiplier value that matches the medical standard (1, 2, 3, 4, 4, 3, 2 respectively).
**Validates: Requirements 1.2-1.8**

### Property 3: Volume-based bottle calculation
*For any* volume-based drug (syrup, suspension) with dose M ml, frequency F, duration D days, and bottle_size B ml, the quantity should equal ceil((M × frequency_multiplier(F) × D) / B) bottles.
**Validates: Requirements 2.1, 2.2, 2.5**

### Property 4: Interval-based patch calculation
*For any* patch drug with change interval I days and duration D days, the quantity should equal ceil(D / I) patches.
**Validates: Requirements 3.1, 3.4**

### Property 5: Fixed-unit drug defaults
*For any* fixed-unit drug (cream, ointment, gel, drops, inhaler, combination pack), the default quantity should be 1 unit regardless of frequency and duration.
**Validates: Requirements 4.1-4.5**

### Property 6: Context-aware drops interpretation
*For any* drops drug with input "N F x D days", the system should interpret N as drops per application (not bottles) and dispense 1 bottle.
**Validates: Requirements 4.8**

### Property 7: Split dose quantity calculation
*For any* split dose pattern A-B-C with duration D days, the quantity should equal (A + B + C) × D.
**Validates: Requirements 5.1**

### Property 8: STAT dose quantity
*For any* STAT prescription with dose N, the quantity should equal N (no duration multiplication).
**Validates: Requirements 6.1, 6.2**

### Property 9: PRN with max daily calculation
*For any* PRN prescription with max daily M and duration D days, the quantity should equal M × D.
**Validates: Requirements 7.1**

### Property 10: Taper pattern quantity calculation
*For any* taper pattern [D1, D2, ..., Dn], the quantity should equal sum(D1 + D2 + ... + Dn).
**Validates: Requirements 8.1**

### Property 11: Custom interval quantity calculation
*For any* custom interval schedule with dose N per interval and M intervals, the quantity should equal N × M.
**Validates: Requirements 9.1**

### Property 12: Custom interval pattern storage
*For any* prescription with custom intervals, the schedule_pattern field should contain the interval hours for MAR reference.
**Validates: Requirements 9.3**

## Error Handling

### Invalid Input Scenarios

| Scenario | Expected Behavior |
|----------|-------------------|
| Zero dose | Error: "Dose must be positive" |
| Negative duration | Error: "Duration must be positive" |
| Missing frequency | Warning: "Please specify frequency" |
| Unknown frequency code | Error: "Unrecognized frequency" |
| Missing bottle_size for syrup | Default to 1 bottle with warning |

## Testing Strategy

### Property-Based Testing

- **Library**: Pest with custom generators
- **Iterations**: Minimum 100 per property
- **Coverage**: All drug forms, all frequency codes, edge cases

### Playwright Browser Testing

- **Tool**: Playwright MCP
- **Scope**: End-to-end UI verification
- **Test Cases**:
  1. Tablet prescription with standard frequency
  2. Syrup prescription with bottle calculation
  3. Drops prescription (context-aware)
  4. Split dose pattern
  5. STAT dose
  6. Taper pattern
  7. Custom interval (antimalarial)

### Test Annotation Format

Each property-based test must be tagged:
```php
/**
 * Feature: prescription-quantity-testing, Property N: [property name]
 * Validates: Requirements X.Y
 */
```

### Test Organization

```
tests/
├── Feature/
│   └── Prescription/
│       └── QuantityCalculationTest.php    # Property-based tests
└── Browser/
    └── Prescription/
        └── SmartInputQuantityTest.php     # Playwright tests
```
