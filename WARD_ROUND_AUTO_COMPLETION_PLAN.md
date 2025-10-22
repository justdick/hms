# Ward Round Auto-Completion Workflow Plan

## Overview
Implement an improved ward rounds workflow where starting a new ward round automatically completes the previous in-progress round. This creates a more natural clinical workflow aligned with daily ward round practices.

## Current State

### Existing Implementation
- Ward rounds have `status` field: `in_progress` | `completed`
- Manual completion via "Complete Ward Round" button
- Auto-save every 3 seconds for in-progress rounds
- Status checks prevent editing completed rounds
- Location: `WardRoundController.php`, `WardRoundCreate.tsx`

### Current Issues
- Doctors may forget to manually complete rounds
- Multiple abandoned `in_progress` rounds possible per patient
- No clear visual history of all ward rounds
- Unclear which round is "current"

## Proposed Workflow

### Core Concept
**Starting a new ward round automatically completes the previous in-progress round**

### User Flow
1. Doctor visits patient detail page `/wards/{ward}/patients/{admission}`
2. Views **Ward Rounds DataTable** showing all rounds with:
   - Day Number (auto-calculated from admission date)
   - Date/Time of round
   - Doctor Name
   - Status Badge (`in_progress` | `completed`)
   - Click row to view/edit specific round
3. Clicks **"Start New Ward Round"** button
4. System shows confirmation if there's an existing `in_progress` round:
   - "Starting a new round will complete the current in-progress round. Continue?"
5. On confirm:
   - Auto-complete previous `in_progress` round with current data
   - Create new `in_progress` round
   - Redirect to new round edit page

### Benefits
✅ Natural clinical workflow (daily rounds)
✅ Prevents orphaned/abandoned rounds
✅ Only ONE `in_progress` round at a time per patient
✅ Clear visual history with status indicators
✅ No data loss (auto-completed with existing data)
✅ Simpler UX (no manual completion needed)

## Implementation Tasks

### 1. Frontend - Ward Rounds DataTable Component
**File:** `resources/js/components/Ward/WardRoundsTable.tsx`

**Features:**
- DataTable showing all ward rounds for a patient admission
- Columns:
  - Day Number
  - Date/Time
  - Doctor Name
  - Status (badge with color coding)
  - Actions (view/edit icon)
- Click row to navigate to ward round detail
- Status badge colors:
  - `in_progress`: Yellow/Amber
  - `completed`: Green

**Props Interface:**
```typescript
interface WardRoundsTableProps {
    admission: PatientAdmission;
    wardRounds: WardRound[];
    onRowClick: (wardRoundId: number) => void;
}
```

### 2. Frontend - Start New Ward Round Button
**Location:** `resources/js/pages/Ward/PatientShow.tsx` (Ward Rounds tab)

**Features:**
- Prominent "Start New Ward Round" button
- Check if there's existing `in_progress` round
- Show confirmation dialog if yes:
  - "Complete Current Round and Start New?"
  - "This will save the current round (Day X) and create a new round (Day Y). Continue?"
- On confirm: POST to create endpoint
- Redirect to new round edit page

### 3. Backend - Auto-Complete Logic
**File:** `app/Http/Controllers/Ward/WardRoundController.php`

**Update `store()` method:**
```php
public function store(StoreWardRoundRequest $request, PatientAdmission $admission)
{
    $this->authorize('create', WardRound::class);

    // Auto-complete any existing in-progress ward rounds for this admission
    $existingInProgress = WardRound::where('patient_admission_id', $admission->id)
        ->where('status', 'in_progress')
        ->get();

    foreach ($existingInProgress as $round) {
        $round->update(['status' => 'completed']);
    }

    // Calculate day number based on admission date
    $dayNumber = $this->calculateDayNumber($admission);

    // Create new ward round
    $wardRound = WardRound::create([
        'patient_admission_id' => $admission->id,
        'doctor_id' => auth()->id(),
        'day_number' => $dayNumber,
        'round_type' => $request->round_type ?? 'daily',
        'status' => 'in_progress',
        'round_datetime' => now(),
    ]);

    return redirect()->route('admissions.ward-rounds.edit', [
        'admission' => $admission,
        'wardRound' => $wardRound
    ])->with('success', 'New ward round started.');
}

private function calculateDayNumber(PatientAdmission $admission): int
{
    $admissionDate = Carbon::parse($admission->admission_date);
    $today = Carbon::today();
    return $admissionDate->diffInDays($today) + 1;
}
```

### 4. Keep Manual Completion Option (Optional)
**Rationale:** May still be useful for:
- Completing round without starting a new one
- Explicit sign-off before end of shift
- Regulatory/compliance requirements

**Implementation:** Keep existing `complete()` method and button, but make it less prominent

### 5. Update Policies
**File:** `app/Policies/WardRoundPolicy.php`

Ensure:
- Only doctors can start new ward rounds
- Only the doctor who created the round can edit it (or authorized staff)
- Completed rounds are view-only (or editable with audit trail)

### 6. Database Considerations
**Current Migration:** Already has `status` field
**Index Recommendation:** Add index for faster queries
```php
$table->index(['patient_admission_id', 'status']);
```

## UI/UX Specifications

### Ward Rounds DataTable
```
┌─────────────────────────────────────────────────────────────┐
│  Ward Rounds                    [Start New Ward Round] ←──┐ │
├─────────────────────────────────────────────────────────────┤
│ Day │ Date/Time         │ Doctor        │ Status      │    │ │
├─────┼───────────────────┼───────────────┼─────────────┼────┤ │
│  3  │ Jan 18, 2025 9:00 │ Dr. Smith     │ In Progress │ →  │ │
│  2  │ Jan 17, 2025 8:30 │ Dr. Smith     │ Completed   │ →  │ │
│  1  │ Jan 16, 2025 9:15 │ Dr. Johnson   │ Completed   │ →  │ │
└─────────────────────────────────────────────────────────────┘
```

### Confirmation Dialog
```
┌─────────────────────────────────────────────────┐
│  Complete Current Round and Start New?          │
│                                                  │
│  This will complete Ward Round Day 3 and        │
│  create a new Ward Round Day 4.                 │
│                                                  │
│  All current data will be saved.                │
│                                                  │
│              [Cancel]  [Start New Round]        │
└─────────────────────────────────────────────────┘
```

## Questions to Address

1. **Confirmation Dialog**: Required or optional?
   - Recommended: **Required** to prevent accidental completion

2. **Completed Rounds**: View-only or editable?
   - Recommended: **View-only** for data integrity
   - Alternative: Editable with audit trail (track who edited and when)

3. **Day Number**: Auto-calculate or manual entry?
   - Recommended: **Auto-calculate** based on admission date
   - Allow override if needed (e.g., multiple rounds per day)

4. **Multiple Rounds Per Day**: Support?
   - Example: Morning round, Evening round
   - If yes, add `round_type` field: `morning` | `afternoon` | `evening` | `emergency`

5. **Manual Completion**: Keep or remove?
   - Recommended: **Keep** but make less prominent
   - Use case: Complete without starting new round

## Testing Checklist

- [ ] Starting new round auto-completes previous in-progress round
- [ ] Only one in-progress round exists per patient at a time
- [ ] Day number calculates correctly from admission date
- [ ] Confirmation dialog shows when in-progress round exists
- [ ] DataTable displays all rounds with correct status badges
- [ ] Clicking row navigates to correct ward round detail
- [ ] Auto-save still works for in-progress rounds
- [ ] Completed rounds are locked from editing
- [ ] Policies prevent unauthorized access
- [ ] Multiple doctors can create rounds for same patient
- [ ] Browser back button doesn't cause issues

## Migration Path

### Phase 1: DataTable Implementation
1. Create `WardRoundsTable.tsx` component
2. Update `PatientShow.tsx` to use new table
3. Test display of existing rounds

### Phase 2: Auto-Completion Logic
1. Update `WardRoundController@store` with auto-complete
2. Add confirmation dialog
3. Test round creation workflow

### Phase 3: Cleanup
1. Update routes if needed
2. Add database indexes
3. Update tests
4. Remove old manual completion button (optional)

## Related Files

### Controllers
- `app/Http/Controllers/Ward/WardRoundController.php`

### Frontend Components
- `resources/js/pages/Ward/PatientShow.tsx`
- `resources/js/pages/Ward/WardRoundCreate.tsx`
- `resources/js/components/Ward/WardRoundsTable.tsx` (NEW)

### Models
- `app/Models/WardRound.php`
- `app/Models/PatientAdmission.php`

### Routes
- `routes/wards.php`

### Migrations
- `database/migrations/*_add_status_to_ward_rounds_table.php`

## Notes

- This workflow aligns with standard hospital ward round practices
- Reduces cognitive load on doctors (no manual completion)
- Maintains data integrity (auto-save + auto-complete)
- Provides clear audit trail via status and timestamps
- Extensible for future features (e.g., co-signing, amendments)

## Implementation Priority

**High Priority:**
1. Ward Rounds DataTable
2. Auto-complete logic
3. Start New Round button with confirmation

**Medium Priority:**
4. Day number auto-calculation
5. Status badge styling
6. Row click navigation

**Low Priority:**
7. Keep manual completion option
8. Edit completed rounds with audit trail
9. Multiple rounds per day support
