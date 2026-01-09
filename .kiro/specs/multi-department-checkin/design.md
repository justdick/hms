# Design Document

## Overview

This design enables multi-department same-day check-ins while maintaining data integrity, improves error messaging for check-in failures, and enhances the claims vetting interface with CCC grouping and date filtering.

The implementation modifies the existing check-in validation logic to allow same-day check-ins to different departments while blocking duplicates to the same department. It also adds a confirmation flow for patients with active admissions and improves error messages throughout.

## Architecture

### Component Interaction

```
┌─────────────────────────────────────────────────────────────────┐
│                      Check-in Flow                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  CheckinModal.tsx                                                │
│       │                                                          │
│       ▼                                                          │
│  ┌─────────────────┐    ┌──────────────────────┐                │
│  │ Validate Dept   │───▶│ Show Admission       │                │
│  │ (same-day)      │    │ Warning Dialog       │                │
│  └────────┬────────┘    └──────────┬───────────┘                │
│           │                        │                             │
│           ▼                        ▼                             │
│  ┌─────────────────┐    ┌──────────────────────┐                │
│  │ CheckinController│◀──│ User Confirms/Cancel │                │
│  │ store()         │    └──────────────────────┘                │
│  └────────┬────────┘                                            │
│           │                                                      │
│           ▼                                                      │
│  ┌─────────────────────────────────────────────┐                │
│  │ Validation Rules:                            │                │
│  │ - Block same department same day             │                │
│  │ - Allow different department same day        │                │
│  │ - Warn if active admission (allow proceed)   │                │
│  │ - Return specific error messages             │                │
│  └─────────────────────────────────────────────┘                │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    Claims List Flow                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ClaimsDataTable.tsx                                             │
│       │                                                          │
│       ├──▶ DateFilterPresets.tsx (new component)                │
│       │         │                                                │
│       │         ▼                                                │
│       │    ┌─────────────────────────────────┐                  │
│       │    │ Presets: Today, Yesterday,      │                  │
│       │    │ This Week, Last Week,           │                  │
│       │    │ This Month, Last Month, Custom  │                  │
│       │    └─────────────────────────────────┘                  │
│       │                                                          │
│       ▼                                                          │
│  InsuranceClaimController.php                                    │
│       │                                                          │
│       ▼                                                          │
│  ┌─────────────────────────────────────────────┐                │
│  │ Query with:                                  │                │
│  │ - orderBy('claim_check_code')               │                │
│  │ - orderBy('date_of_attendance')             │                │
│  │ - Date range filter                          │                │
│  └─────────────────────────────────────────────┘                │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### 1. CheckinController Modifications

**File:** `app/Http/Controllers/Checkin/CheckinController.php`

```php
// New validation logic in store() method
public function store(Request $request)
{
    // ... existing validation ...
    
    // Check for same-day same-department check-in (BLOCK)
    $sameDeptCheckin = PatientCheckin::where('patient_id', $validated['patient_id'])
        ->where('department_id', $validated['department_id'])
        ->whereDate('service_date', $serviceDate)
        ->whereNotIn('status', ['cancelled'])
        ->first();
    
    if ($sameDeptCheckin) {
        $department = Department::find($validated['department_id']);
        return back()->withErrors([
            'department_id' => "Patient already checked in to {$department->name} today. Please select a different department.",
        ])->withInput();
    }
    
    // Check for active admission (WARN, allow proceed)
    $activeAdmission = $patient->activeAdmission;
    if ($activeAdmission && !$request->boolean('confirm_admission_override')) {
        return back()->withErrors([
            'admission_warning' => true,
        ])->with('admission_details', [
            'id' => $activeAdmission->id,
            'admission_number' => $activeAdmission->admission_number,
            'ward' => $activeAdmission->ward->name,
            'admitted_at' => $activeAdmission->admitted_at,
        ])->withInput();
    }
    
    // Create check-in with admission flag if applicable
    $checkin = PatientCheckin::create([
        // ... existing fields ...
        'created_during_admission' => $activeAdmission ? true : false,
    ]);
    
    // ... rest of method ...
}
```

### 2. CheckinModal Modifications

**File:** `resources/js/components/Checkin/CheckinModal.tsx`

```typescript
// Add admission warning dialog state
const [admissionWarning, setAdmissionWarning] = useState<{
    show: boolean;
    admission: {
        id: number;
        admission_number: string;
        ward: string;
        admitted_at: string;
    } | null;
}>({ show: false, admission: null });

// Handle specific errors from backend
const handleCheckinError = (errors: Record<string, string>) => {
    if (errors.admission_warning) {
        // Show admission warning dialog
        setAdmissionWarning({
            show: true,
            admission: flash.admission_details,
        });
        return;
    }
    
    // Display specific error messages
    if (errors.department_id) {
        toast.error(errors.department_id);
    } else if (errors.claim_check_code) {
        toast.error(errors.claim_check_code);
    } else if (errors.patient_id) {
        toast.error(errors.patient_id);
    } else {
        // Fallback - should rarely happen
        const firstError = Object.values(errors)[0];
        toast.error(firstError || 'Check-in failed. Please try again.');
    }
};
```

### 3. AdmissionWarningDialog Component (New)

**File:** `resources/js/components/Checkin/AdmissionWarningDialog.tsx`

```typescript
interface AdmissionWarningDialogProps {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    admission: {
        admission_number: string;
        ward: string;
        admitted_at: string;
    };
}

export default function AdmissionWarningDialog({
    open,
    onClose,
    onConfirm,
    admission,
}: AdmissionWarningDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onClose}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 text-amber-500" />
                        Patient Has Active Admission
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        This patient is currently admitted:
                        <div className="mt-2 rounded-md bg-muted p-3">
                            <p><strong>Admission:</strong> {admission.admission_number}</p>
                            <p><strong>Ward:</strong> {admission.ward}</p>
                            <p><strong>Since:</strong> {formatDate(admission.admitted_at)}</p>
                        </div>
                        <p className="mt-2">
                            Do you want to proceed with OPD check-in anyway?
                            This will create a separate outpatient visit.
                        </p>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel onClick={onClose}>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm}>
                        Proceed with Check-in
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
```

### 4. DateFilterPresets Component (New)

**File:** `resources/js/components/ui/date-filter-presets.tsx`

```typescript
interface DateFilterPresetsProps {
    value: { from?: string; to?: string; preset?: string };
    onChange: (value: { from?: string; to?: string; preset?: string }) => void;
}

const presets = [
    { label: 'Today', value: 'today' },
    { label: 'Yesterday', value: 'yesterday' },
    { label: 'This Week', value: 'this_week' },
    { label: 'Last Week', value: 'last_week' },
    { label: 'This Month', value: 'this_month' },
    { label: 'Last Month', value: 'last_month' },
    { label: 'Custom Range', value: 'custom' },
];

export function DateFilterPresets({ value, onChange }: DateFilterPresetsProps) {
    const [showCustom, setShowCustom] = useState(value.preset === 'custom');
    
    const handlePresetChange = (preset: string) => {
        if (preset === 'custom') {
            setShowCustom(true);
            onChange({ preset: 'custom' });
            return;
        }
        
        setShowCustom(false);
        const range = calculateDateRange(preset);
        onChange({ ...range, preset });
    };
    
    return (
        <div className="flex items-center gap-2">
            <Select value={value.preset || ''} onValueChange={handlePresetChange}>
                <SelectTrigger className="w-[160px]">
                    <SelectValue placeholder="Filter by date" />
                </SelectTrigger>
                <SelectContent>
                    {presets.map((preset) => (
                        <SelectItem key={preset.value} value={preset.value}>
                            {preset.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            
            {showCustom && (
                <div className="flex items-center gap-2">
                    <Input
                        type="date"
                        value={value.from || ''}
                        onChange={(e) => onChange({ ...value, from: e.target.value })}
                        className="w-[140px]"
                    />
                    <span className="text-muted-foreground">to</span>
                    <Input
                        type="date"
                        value={value.to || ''}
                        onChange={(e) => onChange({ ...value, to: e.target.value })}
                        className="w-[140px]"
                    />
                </div>
            )}
            
            {(value.preset || value.from || value.to) && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onChange({})}
                >
                    <X className="h-4 w-4" />
                </Button>
            )}
        </div>
    );
}

function calculateDateRange(preset: string): { from: string; to: string } {
    const today = new Date();
    const formatDate = (d: Date) => d.toISOString().split('T')[0];
    
    switch (preset) {
        case 'today':
            return { from: formatDate(today), to: formatDate(today) };
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            return { from: formatDate(yesterday), to: formatDate(yesterday) };
        case 'this_week':
            const weekStart = new Date(today);
            weekStart.setDate(today.getDate() - today.getDay());
            return { from: formatDate(weekStart), to: formatDate(today) };
        case 'last_week':
            const lastWeekEnd = new Date(today);
            lastWeekEnd.setDate(today.getDate() - today.getDay() - 1);
            const lastWeekStart = new Date(lastWeekEnd);
            lastWeekStart.setDate(lastWeekEnd.getDate() - 6);
            return { from: formatDate(lastWeekStart), to: formatDate(lastWeekEnd) };
        case 'this_month':
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            return { from: formatDate(monthStart), to: formatDate(today) };
        case 'last_month':
            const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
            const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            return { from: formatDate(lastMonthStart), to: formatDate(lastMonthEnd) };
        default:
            return { from: '', to: '' };
    }
}
```

### 5. Claims List CCC Grouping

**File:** `app/Http/Controllers/Admin/InsuranceClaimController.php`

```php
// Modify index() method sorting
$query->orderBy('claim_check_code', 'asc')
      ->orderBy('date_of_attendance', 'desc')
      ->orderBy('id', 'asc');
```

**File:** `resources/js/pages/Admin/Insurance/Claims/claims-data-table.tsx`

```typescript
// Add visual indicator for same-CCC rows
const getRowClassName = (row: Row<InsuranceClaim>, index: number, data: InsuranceClaim[]) => {
    const currentCcc = row.original.claim_check_code;
    const prevCcc = index > 0 ? data[index - 1].claim_check_code : null;
    const nextCcc = index < data.length - 1 ? data[index + 1].claim_check_code : null;
    
    const hasSameCccNeighbor = currentCcc === prevCcc || currentCcc === nextCcc;
    
    return cn(
        'hover:bg-muted/50',
        hasSameCccNeighbor && 'bg-blue-50/50 dark:bg-blue-950/20 border-l-2 border-l-blue-400'
    );
};
```

## Data Models

### PatientCheckin Model Updates

**File:** `app/Models/PatientCheckin.php`

Add new field:
```php
protected $fillable = [
    // ... existing fields ...
    'created_during_admission',
];

protected function casts(): array
{
    return [
        // ... existing casts ...
        'created_during_admission' => 'boolean',
    ];
}
```

### Migration

**File:** `database/migrations/xxxx_add_created_during_admission_to_patient_checkins.php`

```php
public function up(): void
{
    Schema::table('patient_checkins', function (Blueprint $table) {
        $table->boolean('created_during_admission')->default(false)->after('notes');
    });
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Same-Department Same-Day Block

*For any* patient and department, if the patient already has a non-cancelled check-in to that department on a given day, attempting another check-in to the same department on the same day SHALL be rejected with a specific error message.

**Validates: Requirements 1.2, 1.3**

### Property 2: Different-Department Same-Day Allow

*For any* patient with an existing check-in to Department A on a given day, checking in to Department B (where B ≠ A) on the same day SHALL succeed and create a separate check-in record.

**Validates: Requirements 1.1, 1.4**

### Property 3: Admission Warning Flow

*For any* patient with an active admission, attempting OPD check-in SHALL return an admission warning. If the user confirms to proceed, the check-in SHALL be created with `created_during_admission = true`.

**Validates: Requirements 2.1, 2.3, 2.4**

### Property 4: Specific Error Messages

*For any* check-in validation failure, the error response SHALL contain a specific, actionable error message (not a generic "failed to check in" message).

**Validates: Requirements 3.4, 3.5**

### Property 5: Claims CCC Grouping

*For any* set of claims with the same CCC, when the claims list is queried, those claims SHALL appear in consecutive rows (grouped together).

**Validates: Requirements 4.1, 4.2**

### Property 6: Date Filter Accuracy

*For any* date filter selection (preset or custom range), the returned claims SHALL have `date_of_attendance` within the specified range, and no claims outside the range SHALL be returned.

**Validates: Requirements 5.4, 5.5, 5.7**

### Property 7: CCC Sharing for Same-Day Check-ins

*For any* patient with multiple check-ins on the same day where the first check-in has a CCC, subsequent check-ins SHALL either inherit the same CCC or display a warning if a different CCC is entered.

**Validates: Requirements 6.1, 6.3, 6.4**

## Error Handling

### Check-in Error Messages

| Scenario | Error Key | Message |
|----------|-----------|---------|
| Same department same day | `department_id` | "Patient already checked in to {Department Name} today. Please select a different department." |
| Missing CCC for NHIS | `claim_check_code` | "CCC (Claim Check Code) is required for NHIS patients" |
| Invalid department | `department_id` | "Invalid department selected" |
| Active admission | `admission_warning` | Returns `true` with `admission_details` in session |
| Duplicate CCC in use | `claim_check_code` | "This CCC is currently in use by an active claim that has not been submitted yet." |
| Incomplete check-in exists | `patient_id` | "Patient has an incomplete check-in (Status: {status}) that needs to be completed or cancelled first." |

### Frontend Error Display

```typescript
// Error handling priority in CheckinModal
onError: (errors) => {
    // 1. Check for admission warning (special case - show dialog)
    if (errors.admission_warning) {
        showAdmissionWarningDialog();
        return;
    }
    
    // 2. Display specific field errors
    const errorMessages = {
        department_id: errors.department_id,
        claim_check_code: errors.claim_check_code,
        patient_id: errors.patient_id,
    };
    
    for (const [field, message] of Object.entries(errorMessages)) {
        if (message) {
            toast.error(message);
            return;
        }
    }
    
    // 3. Fallback to first error (should rarely reach here)
    const firstError = Object.values(errors)[0];
    toast.error(firstError || 'An unexpected error occurred. Please try again.');
}
```

## Testing Strategy

### Unit Tests

- Test `calculateDateRange()` function for all presets
- Test CCC grouping logic in claims query
- Test error message generation for each validation scenario

### Feature Tests (Pest)

```php
// Check-in validation tests
it('blocks same-department same-day check-in', function () {
    $patient = Patient::factory()->create();
    $department = Department::factory()->create();
    
    // First check-in succeeds
    PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'service_date' => today(),
    ]);
    
    // Second check-in to same department fails
    $response = $this->actingAs($receptionist)
        ->post('/checkin/checkins', [
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
    
    $response->assertSessionHasErrors('department_id');
    expect($response->session()->get('errors')->get('department_id')[0])
        ->toContain($department->name);
});

it('allows different-department same-day check-in', function () {
    $patient = Patient::factory()->create();
    $deptA = Department::factory()->create(['name' => 'ANC']);
    $deptB = Department::factory()->create(['name' => 'General OPD']);
    
    // First check-in to ANC
    PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $deptA->id,
        'service_date' => today(),
        'status' => 'completed',
    ]);
    
    // Second check-in to General OPD succeeds
    $response = $this->actingAs($receptionist)
        ->post('/checkin/checkins', [
            'patient_id' => $patient->id,
            'department_id' => $deptB->id,
        ]);
    
    $response->assertRedirect();
    expect(PatientCheckin::where('patient_id', $patient->id)->count())->toBe(2);
});

it('warns when patient has active admission', function () {
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'status' => 'admitted',
    ]);
    
    $response = $this->actingAs($receptionist)
        ->post('/checkin/checkins', [
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
    
    $response->assertSessionHasErrors('admission_warning');
    $response->assertSessionHas('admission_details');
});

it('allows check-in with admission when confirmed', function () {
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'status' => 'admitted',
    ]);
    
    $response = $this->actingAs($receptionist)
        ->post('/checkin/checkins', [
            'patient_id' => $patient->id,
            'department_id' => $department->id,
            'confirm_admission_override' => true,
        ]);
    
    $response->assertRedirect();
    $checkin = PatientCheckin::latest()->first();
    expect($checkin->created_during_admission)->toBeTrue();
});
```

### Property-Based Tests

```php
// Using Pest with faker for property-based testing
it('groups claims with same CCC together', function () {
    $ccc = 'CC-' . fake()->date('Ymd') . '-' . fake()->randomNumber(4);
    
    // Create multiple claims with same CCC
    $claims = InsuranceClaim::factory()
        ->count(3)
        ->create(['claim_check_code' => $ccc]);
    
    // Create claims with different CCCs
    InsuranceClaim::factory()->count(5)->create();
    
    $response = $this->actingAs($vettingOfficer)
        ->get('/admin/insurance/claims?status=all');
    
    $data = $response->original->getData()['claims']['data'];
    
    // Find indices of our CCC claims
    $indices = collect($data)
        ->map(fn ($claim, $i) => $claim['claim_check_code'] === $ccc ? $i : null)
        ->filter()
        ->values();
    
    // Verify they are consecutive
    for ($i = 1; $i < $indices->count(); $i++) {
        expect($indices[$i] - $indices[$i - 1])->toBe(1);
    }
});
```
