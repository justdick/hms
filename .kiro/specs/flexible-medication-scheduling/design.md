# Design Document

## Overview

This design implements a flexible medication scheduling system where nurses configure administration times for each prescription using a day-based approach. The system provides smart default times based on frequency and current time, allows Day 1 (admission day) to have different times than subsequent days, supports complex regimens with multiple custom days, and tracks all changes with audit trails. The solution enhances the existing `MedicationScheduleService` and adds new database tables and UI components to support these features.

## Architecture

### High-Level Components

1. **Enhanced MedicationScheduleService** - Core scheduling logic with interval-based calculations
2. **MedicationScheduleAdjustment Model** - Audit trail for schedule changes
3. **API Controllers** - RESTful endpoints for schedule management
4. **React UI Components** - Interface for viewing and adjusting schedules
5. **Policy Authorization** - Permission checks for schedule modifications

### Data Flow

```
Prescription Created (by doctor)
    ↓
Status: "pending_schedule"
    ↓
Nurse opens "Configure Times" modal
    ↓
System populates smart default times
    ↓
Nurse adjusts times if needed
    ↓
Nurse clicks "Generate Schedule"
    ↓
MedicationScheduleService.generateScheduleFromPattern()
    ↓
Create MedicationAdministration records based on day patterns
    ↓
Schedule appears in MAR
    ↓
User adjusts individual times (optional)
    ↓
Update MedicationAdministration.scheduled_time
    ↓
Create MedicationScheduleAdjustment audit record
```

## Components and Interfaces

### 1. Database Schema Changes

#### New Table: `medication_schedule_adjustments`

Tracks all manual adjustments to medication schedules for audit purposes.

```php
Schema::create('medication_schedule_adjustments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('medication_administration_id')->constrained()->onDelete('cascade');
    $table->foreignId('adjusted_by_id')->constrained('users');
    $table->dateTime('original_time');
    $table->dateTime('adjusted_time');
    $table->text('reason')->nullable();
    $table->timestamps();
    
    $table->index(['medication_administration_id', 'created_at']);
});
```

#### Modify Table: `prescriptions`

Add fields to support discontinuation and day-based schedule configuration.

```php
Schema::table('prescriptions', function (Blueprint $table) {
    $table->json('schedule_pattern')->nullable()->after('frequency');
    $table->dateTime('discontinued_at')->nullable()->after('status');
    $table->foreignId('discontinued_by_id')->nullable()->constrained('users')->after('discontinued_at');
    $table->text('discontinuation_reason')->nullable()->after('discontinued_by_id');
});
```

**schedule_pattern format:**
```json
{
  "day_1": ["10:30", "18:00"],
  "day_2": ["10:00", "22:00"],
  "subsequent": ["06:00", "18:00"]
}
```

#### Modify Table: `medication_administrations`

Add field to track if schedule was manually adjusted.

```php
Schema::table('medication_administrations', function (Blueprint $table) {
    $table->boolean('is_adjusted')->default(false)->after('scheduled_time');
});
```

### 2. Enhanced MedicationScheduleService

#### New Methods

**`generateSmartDefaults(string $frequency, Carbon $currentTime, int $duration): array`**
- Generate smart default time patterns based on frequency and current time
- For BID: Day 1 = [current time, next standard time], Subsequent = [06:00, 18:00]
- For TID: Day 1 = [next available from 06:00/14:00/22:00], Subsequent = [06:00, 14:00, 22:00]
- For QID: Subsequent = [06:00, 12:00, 18:00, 00:00]
- For Q4H/Q6H/Q2H: Calculate from current time rounded to nearest hour
- Return array with day_1, subsequent keys

**`generateScheduleFromPattern(Prescription $prescription): void`**
- Read schedule_pattern from prescription
- For each day in duration:
  - If day_1 pattern exists, use for Day 1
  - If day_X pattern exists, use for Day X
  - Otherwise use subsequent pattern
- Create MedicationAdministration records with calculated times
- Skip PRN prescriptions

**`reconfigureSchedule(Prescription $prescription, array $newPattern, User $user): void`**
- Cancel all future scheduled administrations (status = 'cancelled')
- Preserve administrations already given
- Update prescription schedule_pattern
- Call generateScheduleFromPattern() to create new schedule
- Create audit record of reconfiguration

**`adjustScheduleTime(MedicationAdministration $administration, Carbon $newTime, User $user, ?string $reason = null): void`**
- Validate administration is not already given
- Update scheduled_time
- Set is_adjusted = true
- Create audit record in MedicationScheduleAdjustment

**`discontinuePrescription(Prescription $prescription, User $user, ?string $reason = null): void`**
- Set discontinued_at, discontinued_by_id, discontinuation_reason
- Cancel all future scheduled administrations (status = 'cancelled')
- Preserve administrations already given

### 3. Models

#### MedicationScheduleAdjustment

```php
class MedicationScheduleAdjustment extends Model
{
    protected $fillable = [
        'medication_administration_id',
        'adjusted_by_id',
        'original_time',
        'adjusted_time',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'original_time' => 'datetime',
            'adjusted_time' => 'datetime',
        ];
    }

    public function medicationAdministration(): BelongsTo
    {
        return $this->belongsTo(MedicationAdministration::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by_id');
    }
}
```

#### Updated Prescription Model

Add methods:
- `discontinue(User $user, ?string $reason = null): void`
- `isDiscontinued(): bool`
- `canBeDiscontinued(): bool`
- `scopeActive($query): void` - Exclude discontinued prescriptions

#### Updated MedicationAdministration Model

Add relationships and methods:
- `scheduleAdjustments(): HasMany`
- `latestAdjustment(): HasOne`
- `isAdjusted(): bool`
- `canBeAdjusted(): bool`

### 4. API Controllers

#### MedicationScheduleController

**Endpoints:**

```php
// GET /api/prescriptions/{prescription}/smart-defaults
public function smartDefaults(Prescription $prescription): JsonResponse
// Returns smart default time patterns based on frequency and current time

// POST /api/prescriptions/{prescription}/configure-schedule
public function configureSchedule(
    Prescription $prescription,
    ConfigureScheduleRequest $request
): JsonResponse
// Saves schedule pattern and generates medication administrations

// POST /api/prescriptions/{prescription}/reconfigure-schedule
public function reconfigureSchedule(
    Prescription $prescription,
    ConfigureScheduleRequest $request
): JsonResponse
// Reconfigures existing schedule with new pattern

// GET /api/prescriptions/{prescription}/schedule
public function index(Prescription $prescription): JsonResponse
// Returns all medication administrations with adjustment history

// PATCH /api/medication-administrations/{administration}/adjust-time
public function adjustTime(
    MedicationAdministration $administration,
    AdjustScheduleTimeRequest $request
): JsonResponse
// Adjusts scheduled time and creates audit record

// GET /api/medication-administrations/{administration}/adjustment-history
public function adjustmentHistory(MedicationAdministration $administration): JsonResponse
// Returns all adjustments for a specific administration

// POST /api/prescriptions/{prescription}/discontinue
public function discontinue(
    Prescription $prescription,
    DiscontinuePrescriptionRequest $request
): JsonResponse
// Discontinues prescription and cancels future administrations
```

### 5. Form Request Validation

#### ConfigureScheduleRequest

```php
public function rules(): array
{
    return [
        'schedule_pattern' => ['required', 'array'],
        'schedule_pattern.day_1' => ['required', 'array'],
        'schedule_pattern.day_1.*' => ['required', 'date_format:H:i'],
        'schedule_pattern.day_*' => ['nullable', 'array'],
        'schedule_pattern.day_*.*' => ['required', 'date_format:H:i'],
        'schedule_pattern.subsequent' => ['required', 'array'],
        'schedule_pattern.subsequent.*' => ['required', 'date_format:H:i'],
    ];
}

public function authorize(): bool
{
    return $this->user()->can('configureSchedule', $this->route('prescription'));
}
```

#### AdjustScheduleTimeRequest

```php
public function rules(): array
{
    return [
        'scheduled_time' => ['required', 'date', 'after:now'],
        'reason' => ['nullable', 'string', 'max:500'],
    ];
}

public function authorize(): bool
{
    return $this->user()->can('adjustSchedule', $this->route('administration'));
}
```

#### DiscontinuePrescriptionRequest

```php
public function rules(): array
{
    return [
        'reason' => ['required', 'string', 'max:500'],
    ];
}

public function authorize(): bool
{
    return $this->user()->can('discontinue', $this->route('prescription'));
}
```

### 6. Policy Authorization

#### MedicationAdministrationPolicy

```php
public function adjustSchedule(User $user, MedicationAdministration $administration): bool
{
    // Check if user has permission to manage medications
    // Check if administration is not already given
    // Check if user has access to the ward/patient
    return $user->can('manage-medications') 
        && $administration->isScheduled()
        && $user->hasAccessToWard($administration->patientAdmission->ward_id);
}
```

#### PrescriptionPolicy

```php
public function discontinue(User $user, Prescription $prescription): bool
{
    // Check if user has permission to manage prescriptions
    // Check if prescription is not already discontinued
    return $user->can('manage-prescriptions') 
        && !$prescription->isDiscontinued();
}
```

### 7. React UI Components

#### ConfigureScheduleTimesModal Component

Modal for nurses to configure administration times before generating schedule.

**Location:** Opened from Medication History tab or Medication Administration tab when prescription has no schedule

**Features:**
- Auto-populated smart defaults based on frequency and current time
- Day 1 section for admission day (immediate start)
- Ability to add custom days (Day 2, Day 3, etc.) for complex regimens
- Subsequent Days section for repeating pattern
- Add/remove doses for any day
- Real-time schedule preview showing total doses
- Clear visual separation between day sections

**Props:**
```typescript
interface ConfigureScheduleTimesModalProps {
    prescription: Prescription;
    isOpen: boolean;
    onClose: () => void;
    onGenerate: (pattern: SchedulePattern) => void;
}

interface SchedulePattern {
    day_1: string[];  // ["10:30", "18:00"]
    day_2?: string[]; // Optional custom days
    day_3?: string[];
    subsequent: string[]; // ["06:00", "18:00"]
}
```

**UI Structure:**
```
┌──────────────────────────────────────────────────────┐
│ Configure Medication Schedule                        │
│                                                      │
│ [Drug Name] - [Frequency]                           │
│ Duration: X days                                     │
│                                                      │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│                                                      │
│ 📅 DAY 1 (Today - Admission)                        │
│ Dose 1: [HH:MM] 🕐  [+ Add dose]                   │
│ Dose 2: [HH:MM] 🕐                                  │
│                                                      │
│ [+ Add another custom day]                           │
│                                                      │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│                                                      │
│ 📅 SUBSEQUENT DAYS (Days 2-X)                       │
│ Dose 1: [HH:MM] 🕐  [+ Add dose]                   │
│ Dose 2: [HH:MM] 🕐                                  │
│                                                      │
│ ℹ️ These times will repeat for remaining days       │
│                                                      │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│                                                      │
│ 📊 Schedule Preview:                                 │
│ • Day 1: [times] (X doses)                          │
│ • Days 2-X: [times] (Y doses per day)               │
│ • Total: Z doses                                     │
│                                                      │
│ [Cancel]  [Generate Schedule]                        │
└──────────────────────────────────────────────────────┘
```

#### Tab Structure on Ward Patient Page

**Two New Tabs:**

1. **"Medication Administration" Tab**
   - Primary view for nurses managing scheduled doses
   - Shows today's and upcoming scheduled administrations
   - Actions: Give, Hold, Refuse, Adjust Time
   - Grouped by time slots (e.g., Morning 06:00, Afternoon 14:00, Evening 22:00)
   - Visual indicators for due, overdue, adjusted times

2. **"Medication History" Tab**
   - Shows all prescriptions (active and discontinued)
   - Filter: All / Active / Discontinued
   - Each prescription shows: drug name, frequency, duration, status
   - Actions: View Schedule, Discontinue (for active only)
   - Discontinued prescriptions show: reason, who discontinued, when

#### MedicationAdministrationTab Component

Primary tab for nurses to manage scheduled doses.

**Location:** Ward Patient page → "Medication Administration" tab

**Tab Badge:**
- Red badge showing count of pending medications for TODAY only
- Count includes: scheduled and overdue statuses
- Excludes: given, held, refused, cancelled, and future days
- Updates in real-time as medications are administered

**Features:**
- Timeline view grouped by scheduled times
- Default view: Today's medications only
- Each administration card shows: drug name, dose, route, status
- Visual indicators: due (yellow), overdue (red), adjusted (blue badge)
- Quick actions: Give, Hold, Refuse
- Click time to adjust (opens modal)
- Filter: Today / Upcoming / All / Given

**Props:**
```typescript
interface MedicationAdministrationTabProps {
    patientAdmissionId: number;
    administrations: MedicationAdministration[];
    onAdminister: (administrationId: number, data: AdministerData) => void;
    onAdjustTime: (administrationId: number, newTime: string, reason?: string) => void;
}
```

#### MedicationHistoryTab Component

Shows all prescriptions with schedule configuration and discontinuation capabilities.

**Location:** Ward Patient page → "Medication History" tab

**Features:**
- List of all prescriptions (active and discontinued)
- Filter dropdown: All / Active / Discontinued / Pending Schedule
- Each prescription card shows:
  - Drug name, frequency, duration, start date
  - Status badge (Active / Discontinued / Pending Schedule)
  - For pending schedule: "⚠️ Configure Times" button
  - For discontinued: reason, discontinued by, date
- Three-dot menu (⋮) with actions:
  - Configure Times (if no schedule)
  - View Full Schedule (if schedule exists)
  - Reconfigure Times (if schedule exists)
  - Discontinue (active only)
- Discontinued prescriptions styled with reduced opacity

**Props:**
```typescript
interface MedicationHistoryTabProps {
    patientAdmissionId: number;
    prescriptions: Prescription[];
    onConfigureTimes: (prescriptionId: number) => void;
    onReconfigureTimes: (prescriptionId: number) => void;
    onViewSchedule: (prescriptionId: number) => void;
    onDiscontinue: (prescriptionId: number, reason: string) => void;
}
```

#### PrescriptionScheduleModal Component

Modal showing full schedule for a prescription (opened from Medication History tab).

**Features:**
- List all scheduled administrations (past and future)
- Status indicators: given, scheduled, cancelled
- Adjusted times highlighted with badge
- Adjustment history on hover
- Close button

#### AdjustScheduleTimeModal Component

Modal for adjusting medication time.

**Features:**
- DateTime picker for new time
- Optional reason textarea
- Validation (must be future time)
- Show current time and new time side-by-side
- Confirmation button

#### DiscontinueMedicationModal Component

Modal for discontinuing a prescription (opened from Medication History tab).

**Features:**
- Required reason textarea
- Warning message about cancelling future doses
- List of doses that will be cancelled (count and next few times)
- Confirmation button with "Discontinue Medication" text
- Cancel button

#### ScheduleAdjustmentBadge Component

Visual indicator for adjusted schedules.

**Features:**
- Icon (e.g., clock with edit symbol)
- Tooltip showing adjustment history (who adjusted, when, reason)
- Blue color to distinguish from auto-generated times

## Data Models

### MedicationAdministration (Enhanced)

```typescript
interface MedicationAdministration {
    id: number;
    prescription_id: number;
    patient_admission_id: number;
    administered_by_id: number | null;
    scheduled_time: string; // ISO datetime
    administered_at: string | null; // ISO datetime
    status: 'scheduled' | 'given' | 'held' | 'refused' | 'omitted' | 'cancelled';
    dosage_given: string;
    route: string | null;
    notes: string | null;
    is_adjusted: boolean;
    created_at: string;
    updated_at: string;
    
    // Relationships
    prescription?: Prescription;
    administered_by?: User;
    schedule_adjustments?: MedicationScheduleAdjustment[];
    latest_adjustment?: MedicationScheduleAdjustment;
}
```

### MedicationScheduleAdjustment

```typescript
interface MedicationScheduleAdjustment {
    id: number;
    medication_administration_id: number;
    adjusted_by_id: number;
    original_time: string; // ISO datetime
    adjusted_time: string; // ISO datetime
    reason: string | null;
    created_at: string;
    updated_at: string;
    
    // Relationships
    adjusted_by?: User;
}
```

### Prescription (Enhanced)

```typescript
interface Prescription {
    // ... existing fields
    discontinued_at: string | null; // ISO datetime
    discontinued_by_id: number | null;
    discontinuation_reason: string | null;
    
    // Relationships
    discontinued_by?: User;
}
```

## Error Handling

### Validation Errors

1. **Adjusting past time**: Return 422 with message "Cannot adjust to a past time"
2. **Adjusting given medication**: Return 422 with message "Cannot adjust medication that has already been administered"
3. **Unauthorized access**: Return 403 with message "You do not have permission to adjust medication schedules"
4. **Discontinuing already discontinued**: Return 422 with message "This prescription has already been discontinued"

### Service Errors

1. **Invalid frequency code**: Log warning, default to OD (once daily)
2. **Missing admission**: Skip schedule generation (existing behavior)
3. **Database constraint violations**: Rollback transaction, return 500 error

### UI Error Handling

1. **Failed adjustment**: Show toast notification with error message
2. **Network errors**: Show retry button
3. **Validation errors**: Display inline error messages on form fields

## Testing Strategy

### Unit Tests

1. **MedicationScheduleService**
   - Test `generateSmartDefaults()` for BID, TID, QID at various current times
   - Test `generateSmartDefaults()` for Q4H, Q6H, Q2H from current time
   - Test `generateScheduleFromPattern()` creates correct number of doses
   - Test `generateScheduleFromPattern()` uses day_1 for first day, subsequent for remaining
   - Test `generateScheduleFromPattern()` handles custom day_2, day_3 patterns
   - Test `reconfigureSchedule()` cancels future doses and creates new schedule
   - Test `adjustScheduleTime()` creates audit record
   - Test `discontinuePrescription()` cancels future doses only

2. **Models**
   - Test `Prescription::discontinue()` sets correct fields
   - Test `Prescription::hasSchedule()` checks for schedule_pattern
   - Test `MedicationAdministration::canBeAdjusted()` logic
   - Test relationship methods

### Feature Tests

1. **Smart Defaults API**
   - Test BID at 10:30 AM suggests Day 1: [10:30, 18:00], Subsequent: [06:00, 18:00]
   - Test TID at 10:00 AM suggests Day 1: [14:00, 22:00], Subsequent: [06:00, 14:00, 22:00]
   - Test QID suggests standard times [06:00, 12:00, 18:00, 00:00]
   - Test Q4H calculates from current time rounded to nearest hour
   - Test PRN returns empty defaults

2. **Schedule Configuration API**
   - Test authorized user can configure schedule
   - Test configuration creates correct number of MedicationAdministration records
   - Test Day 1 pattern used for first day
   - Test subsequent pattern used for remaining days
   - Test custom day patterns (day_2, day_3) are applied correctly
   - Test PRN prescriptions cannot be configured

3. **Schedule Reconfiguration API**
   - Test reconfiguration cancels future doses
   - Test reconfiguration preserves given doses
   - Test reconfiguration creates new schedule with new pattern
   - Test unauthorized user receives 403

4. **Individual Time Adjustment API**
   - Test authorized user can adjust individual times
   - Test unauthorized user receives 403
   - Test adjustment creates audit record
   - Test cannot adjust already given medication
   - Test cannot adjust to past time

5. **Discontinuation API**
   - Test authorized user can discontinue prescription
   - Test future doses are cancelled
   - Test given doses are preserved
   - Test audit fields are set correctly

6. **Policy Authorization**
   - Test doctors can configure schedules
   - Test nurses can configure schedules
   - Test users without permission cannot configure

### Integration Tests

1. **End-to-End Prescription Flow**
   - Create prescription → configure times → generate schedule → adjust individual time → administer → verify audit trail

2. **Complex Regimen Flow**
   - Create prescription → configure Day 1, Day 2, and subsequent → generate schedule → verify correct pattern application

3. **Reconfiguration Flow**
   - Create prescription → configure schedule → administer one dose → reconfigure times → verify only future doses regenerated

4. **Discontinuation Flow**
   - Create prescription → configure schedule → administer one dose → discontinue → verify only future doses cancelled

## Security Considerations

1. **Authorization**: All schedule modifications protected by policies
2. **Audit Trail**: Complete history of who changed what and when
3. **Data Integrity**: Cannot modify already administered medications
4. **Validation**: All inputs validated on both client and server
5. **Ward Access**: Users can only adjust schedules for patients in wards they have access to

## Performance Considerations

1. **Eager Loading**: Load schedule adjustments with administrations to avoid N+1 queries
2. **Indexing**: Index on `medication_administration_id` and `created_at` in adjustments table
3. **Caching**: Consider caching ward round times if made configurable
4. **Batch Operations**: When discontinuing, use bulk update for cancelling multiple administrations

## Future Enhancements

1. **Configurable Ward Round Times**: Allow different wards to set their own standard round times
2. **Bulk Schedule Adjustment**: Adjust multiple medications at once
3. **Schedule Templates**: Save common adjustment patterns as templates
4. **Notification System**: Alert nurses when schedules are adjusted
5. **Reporting**: Analytics on schedule adherence and adjustment frequency
6. **Mobile App**: Dedicated mobile interface for nurses to manage schedules
