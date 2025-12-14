# Smart Prescription Input - Design Document

## Overview

The Smart Prescription Input feature adds a text-based prescription entry mode alongside the existing dropdown-based (Classic) form. Doctors can toggle between modes, with Smart mode providing a single text field where they type prescriptions using medical shorthand. The system parses input in real-time and displays an interpretation panel for confirmation before submission.

The feature is designed as an additive enhancement - Classic mode remains completely unchanged, and all backend prescription processing, billing events, and MAR workflows continue to work identically regardless of input mode.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    PrescriptionFormSection                       │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              Mode Toggle (Smart / Classic)               │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│              ┌───────────────┴───────────────┐                  │
│              ▼                               ▼                  │
│  ┌─────────────────────┐       ┌─────────────────────────┐     │
│  │   Smart Mode UI     │       │    Classic Mode UI      │     │
│  │                     │       │    (Existing Form)      │     │
│  │  ┌───────────────┐  │       │                         │     │
│  │  │ Drug Selector │  │       │  Drug Selector          │     │
│  │  └───────────────┘  │       │  Dose Quantity          │     │
│  │  ┌───────────────┐  │       │  Frequency Dropdown     │     │
│  │  │ Smart Input   │  │       │  Duration Dropdown      │     │
│  │  │ Text Field    │  │       │  Instructions           │     │
│  │  └───────────────┘  │       │                         │     │
│  │  ┌───────────────┐  │       └─────────────────────────┘     │
│  │  │ Interpretation│  │                                        │
│  │  │ Panel         │  │                                        │
│  │  └───────────────┘  │                                        │
│  │  ┌───────────────┐  │                                        │
│  │  │ Instructions  │  │                                        │
│  │  └───────────────┘  │                                        │
│  └─────────────────────┘                                        │
│                              │                                   │
│              ┌───────────────┴───────────────┐                  │
│              ▼                               ▼                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              Unified Form Submission                     │   │
│  │   (Same prescription data structure for both modes)      │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Backend Processing                            │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────────────┐                                        │
│  │ PrescriptionParser  │ ◄── Parses smart input text            │
│  │ Service (PHP)       │                                        │
│  └─────────────────────┘                                        │
│              │                                                   │
│              ▼                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              Existing Prescription Flow                  │   │
│  │   - StorePrescriptionRequest validation                  │   │
│  │   - Prescription model creation                          │   │
│  │   - PrescriptionCreated event                            │   │
│  │   - Charge creation via listener                         │   │
│  │   - MAR schedule generation (if admitted)                │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### Frontend Components

#### SmartPrescriptionInput Component
```typescript
interface SmartPrescriptionInputProps {
    drug: Drug | null;
    value: string;
    onChange: (value: string) => void;
    onParsedResult: (result: ParsedPrescription | null) => void;
    disabled?: boolean;
}

interface ParsedPrescription {
    isValid: boolean;
    doseQuantity: string;
    frequency: string;
    frequencyCode: string;
    duration: string;
    durationDays: number;
    quantityToDispense: number;
    scheduleType: 'standard' | 'split_dose' | 'custom_interval' | 'taper' | 'stat' | 'prn';
    schedulePattern?: {
        splitDose?: { morning: number; noon: number; evening: number };
        customIntervals?: number[];
        taperDoses?: number[];
    };
    displayText: string;
    errors?: string[];
    warnings?: string[];
}
```

#### InterpretationPanel Component
```typescript
interface InterpretationPanelProps {
    result: ParsedPrescription | null;
    drug: Drug | null;
    onSwitchToClassic?: () => void;
}
```

#### ModeToggle Component
```typescript
interface ModeToggleProps {
    mode: 'smart' | 'classic';
    onChange: (mode: 'smart' | 'classic') => void;
}
```

### Backend Services

#### PrescriptionParserService
```php
class PrescriptionParserService
{
    public function parse(string $input, ?Drug $drug = null): ParsedPrescriptionResult;
    public function parseFrequency(string $input): ?FrequencyResult;
    public function parseDuration(string $input): ?DurationResult;
    public function parseSplitDose(string $input): ?SplitDoseResult;
    public function parseCustomIntervals(string $input): ?CustomIntervalResult;
    public function parseTaper(string $input): ?TaperResult;
    public function calculateQuantity(ParsedPrescriptionResult $result, Drug $drug): int;
    public function toSchedulePattern(ParsedPrescriptionResult $result): ?array;
    public function format(ParsedPrescriptionResult $result): string;
}
```

#### ParsedPrescriptionResult Value Object
```php
class ParsedPrescriptionResult
{
    public bool $isValid;
    public ?string $doseQuantity;
    public ?string $frequency;
    public ?string $frequencyCode;
    public ?string $duration;
    public ?int $durationDays;
    public ?int $quantityToDispense;
    public string $scheduleType; // 'standard', 'split_dose', 'custom_interval', 'taper', 'stat', 'prn'
    public ?array $schedulePattern;
    public ?string $displayText;
    public array $errors = [];
    public array $warnings = [];
}
```

### API Endpoints

#### Parse Prescription (Real-time)
```
POST /api/prescriptions/parse
Request: { input: string, drug_id?: number }
Response: ParsedPrescriptionResult
```

This endpoint is called on each keystroke (debounced) to provide real-time feedback.

## Data Models

### Prescription Model Updates

No schema changes required. The existing fields support all smart input scenarios:

| Field | Usage for Smart Input |
|-------|----------------------|
| `dose_quantity` | Parsed dose (e.g., "2", "5ml") |
| `frequency` | Mapped frequency description (e.g., "Twice daily (BID)") |
| `duration` | Parsed duration (e.g., "5 days") |
| `quantity_to_dispense` | Calculated total quantity |
| `schedule_pattern` | JSON for split dose, custom intervals, or taper patterns |
| `instructions` | Additional instructions from doctor |

### Schedule Pattern JSON Structure

```json
// Split Dose (1-0-1)
{
    "type": "split_dose",
    "pattern": { "morning": 1, "noon": 0, "evening": 1 },
    "daily_total": 2
}

// Custom Intervals (antimalarial)
{
    "type": "custom_interval",
    "intervals_hours": [0, 8, 24, 36, 48, 60],
    "dose_per_interval": 4,
    "total_doses": 6
}

// Taper
{
    "type": "taper",
    "doses": [4, 3, 2, 1],
    "duration_days": 4
}

// Standard (stored for MAR reference)
{
    "type": "standard",
    "frequency_code": "BD",
    "times_per_day": 2
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Mode switching preserves drug selection
*For any* prescription form state with a selected drug, when the mode is switched from Smart to Classic or vice versa, the selected drug should remain unchanged while other form fields are cleared.
**Validates: Requirements 1.4**

### Property 2: Frequency abbreviation mapping consistency
*For any* valid frequency abbreviation (OD, BD, TDS, QDS, Q6H, Q8H, Q12H), parsing should produce a consistent frequency description and times-per-day value that matches the medical standard.
**Validates: Requirements 3.5**

### Property 3: Duration format parsing consistency
*For any* valid duration format ("x N days", "x N/7", "x N weeks"), parsing should produce the correct number of days.
**Validates: Requirements 3.6**

### Property 4: Split dose quantity calculation
*For any* split dose pattern (e.g., "a-b-c x N days"), the total quantity should equal (a + b + c) × N.
**Validates: Requirements 4.3**

### Property 5: Custom interval quantity calculation
*For any* custom interval schedule with D dose per interval and N intervals, the total quantity should equal D × N.
**Validates: Requirements 5.1, 5.3**

### Property 6: Taper quantity calculation
*For any* taper pattern with doses [d1, d2, ..., dn], the total quantity should equal sum(d1 + d2 + ... + dn).
**Validates: Requirements 7.2**

### Property 7: Quantity calculation correctness by drug type
*For any* valid prescription with a piece-based drug (tablet/capsule), the quantity should equal dose × frequency_per_day × duration_days. For liquid drugs, bottles needed should equal ceil(ml_per_dose × frequency_per_day × duration_days / bottle_size).
**Validates: Requirements 8.1, 8.2, 8.3**

### Property 8: Smart mode produces same data structure as Classic mode
*For any* valid smart input that can be expressed in Classic mode, the resulting prescription data (dose_quantity, frequency, duration, quantity_to_dispense) should be identical to what Classic mode would produce for the same prescription.
**Validates: Requirements 10.1**

### Property 9: Parsing round-trip consistency
*For any* valid ParsedPrescriptionResult, formatting it back to a display string and re-parsing should produce an equivalent result.
**Validates: Requirements 2.2, 9.1**

### Property 10: Invalid input produces helpful feedback
*For any* input that cannot be fully parsed, the parser should return isValid=false with at least one error message describing the issue.
**Validates: Requirements 12.1, 12.2, 12.4**

### Property 11: STAT and PRN don't require duration
*For any* input containing STAT or PRN, the parser should not require a duration component and should produce a valid result.
**Validates: Requirements 6.4**

### Property 12: Schedule pattern storage for MAR
*For any* prescription with a non-standard schedule (split dose, custom interval, or taper), the schedule_pattern field should contain the pattern data for MAR reference.
**Validates: Requirements 4.4, 5.3, 11.1**

## Error Handling

### Parser Error Categories

1. **Unrecognized Format**: Input doesn't match any known pattern
   - Display: "Could not parse prescription. Try formats like '2 BD x 5 days' or '1-0-1 x 7 days'"

2. **Missing Components**: Partial match but missing required parts
   - Display: "Found [dose/frequency], but missing [duration]. Add 'x N days' to complete."

3. **Invalid Values**: Recognized format but invalid values
   - Display: "Duration must be a positive number of days"

4. **Ambiguous Input**: Multiple valid interpretations
   - Display: "Interpreted as [interpretation]. Did you mean something else?"

### Fallback Behavior

- If parsing fails completely, show a prominent "Switch to Classic Mode" button
- Partial parsing results are shown with yellow warning styling
- Valid results are shown with green success styling

## Testing Strategy

### Dual Testing Approach

The feature requires both unit tests and property-based tests:

- **Unit tests**: Verify specific parsing examples, UI rendering, and integration points
- **Property-based tests**: Verify universal properties hold across all valid inputs

### Property-Based Testing Library

Use **Pest** with **pest-plugin-faker** for generating test inputs. For more complex property testing, use **QuickCheck-style** generators with custom input generation.

### Test Organization

```
tests/
├── Feature/
│   └── Prescription/
│       └── SmartPrescriptionInputTest.php    # Integration tests
└── Unit/
    └── Services/
        └── PrescriptionParserServiceTest.php  # Parser unit + property tests
```

### Property Test Annotations

Each property-based test must be tagged with:
```php
/**
 * Feature: smart-prescription-input, Property 2: Frequency abbreviation mapping consistency
 */
```

### Test Configuration

Property-based tests should run a minimum of 100 iterations to ensure adequate coverage of the input space.

### Key Test Scenarios

1. **Standard Frequencies**: All abbreviations (OD, BD, TDS, QDS, Q6H, Q8H, Q12H)
2. **Duration Formats**: "x N days", "x N/7", "x N weeks"
3. **Split Doses**: Various patterns (1-0-1, 2-1-1, 1-1-1, etc.)
4. **Custom Intervals**: Antimalarial patterns, arbitrary hour lists
5. **Tapers**: Various decreasing patterns
6. **Special Instructions**: STAT, PRN, combinations
7. **Edge Cases**: Empty input, whitespace, case variations
8. **Invalid Inputs**: Malformed patterns, negative values, missing components

