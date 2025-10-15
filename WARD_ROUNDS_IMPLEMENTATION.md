# Ward Rounds Implementation Progress

## Overview
Implementation of a ward rounds system that uses a consultation-style interface for documenting daily patient reviews during hospital admission. This allows doctors to add new diagnoses, order labs, prescribe medications, and document clinical progress without creating full consultations.

## Implementation Date
Started: October 15, 2025

---

## ✅ Completed Tasks

### 1. Database Schema & Models

#### Ward Rounds Table (`ward_rounds`)
**Migration:** `2025_10_14_002353_create_ward_rounds_table.php`

Fields mirror consultation structure for UI component reuse:
- `id` - Primary key
- `patient_admission_id` - Foreign key to patient_admissions
- `doctor_id` - Foreign key to users (doctor performing round)
- `day_number` - Auto-calculated (Day 1, Day 2, etc.)
- `round_type` - Enum: daily_round, specialist_consult, procedure_note
- **Clinical Documentation Fields** (matching consultations):
  - `presenting_complaint` - Interval update or new concerns
  - `history_presenting_complaint` - Progress since last review
  - `on_direct_questioning` - Review of systems
  - `examination_findings` - Physical examination
  - `assessment_notes` - Clinical assessment
  - `plan_notes` - Management plan
- `patient_status` - Enum: improving, stable, deteriorating, discharge_ready, critical
- `round_datetime` - Timestamp of the round
- Indexes on: admission_id + round_datetime, doctor_id, day_number

**Model:** `app/Models/WardRound.php`
- Fillable fields matching migration
- Relationships:
  - `patientAdmission()` - BelongsTo
  - `doctor()` - BelongsTo User
  - `labOrders()` - MorphMany (polymorphic)
  - `prescriptions()` - MorphMany (polymorphic)
  - `diagnoses()` - MorphMany AdmissionDiagnosis (polymorphic)
- Helper methods: `isImproving()`, `isStable()`, `isDeteriorating()`, etc.
- Scopes: `recent()`, `byDoctor()`

#### Admission Diagnoses Table (`admission_diagnoses`)
**Migration:** `2025_10_15_110029_create_admission_diagnoses_table.php`

Purpose: Track all diagnoses throughout a patient's admission from multiple sources (consultations, ward rounds, etc.)

Fields:
- `id` - Primary key
- `patient_admission_id` - Foreign key to patient_admissions
- `icd_code` - ICD-10 or ICD-11 code
- `icd_version` - Version (10 or 11)
- `diagnosis_name` - Full diagnosis name
- `diagnosis_type` - Enum:
  - `admission` - Initial admission diagnosis
  - `working` - Current active diagnosis
  - `complication` - Complication that developed
  - `comorbidity` - Pre-existing condition discovered
  - `discharge` - Final discharge diagnosis
- `source_type` & `source_id` - Polymorphic (Consultation, WardRound, etc.)
- `diagnosed_by` - Foreign key to users
- `diagnosed_at` - Timestamp when diagnosed
- `is_active` - Boolean (for filtering active diagnoses)
- `clinical_notes` - Notes about the diagnosis
- Indexes on: admission_id + is_active, diagnosed_by

**Model:** `app/Models/AdmissionDiagnosis.php`
- Relationships:
  - `patientAdmission()` - BelongsTo
  - `source()` - MorphTo (Consultation or WardRound)
  - `diagnosedBy()` - BelongsTo User
- Scopes: `active()`, `byType()`

#### Updated PatientAdmission Model
**File:** `app/Models/PatientAdmission.php`

Added relationships:
```php
public function diagnoses(): HasMany
{
    return $this->hasMany(AdmissionDiagnosis::class);
}

public function activeDiagnoses(): HasMany
{
    return $this->diagnoses()->where('is_active', true);
}

public function admissionConsultation(): BelongsTo
{
    return $this->consultation(); // Alias for clarity
}
```

### 2. Backend Controllers

#### WardRoundController
**File:** `app/Http/Controllers/Ward/WardRoundController.php`

**Methods Implemented:**

1. **`index(PatientAdmission $admission)`** - List ward rounds
   - Returns JSON of all ward rounds for an admission
   - Includes doctor information

2. **`create(PatientAdmission $admission)`** - Show ward round form
   - Renders Inertia page: `Ward/WardRoundCreate`
   - Loads: patient, ward, admission consultation, active diagnoses, recent vitals
   - Calculates day number automatically
   - Passes `encounterType: 'ward_round'` for form customization

3. **`store(StoreWardRoundRequest $request, PatientAdmission $admission)`** - Save ward round
   - Auto-calculates day number
   - Creates ward round with clinical documentation
   - **Handles related data:**
     - Lab orders (polymorphic to ward round)
     - Prescriptions (polymorphic to ward round)
     - New diagnoses (linked to admission, sourced from ward round)
   - Returns redirect with success message

4. **`update()`** & **`destroy()`** - Update/delete ward rounds (existing)

### 3. Routes

#### Ward Round Routes
**File:** `routes/wards.php`

Added under `/admissions/{admission}/ward-rounds` prefix:
```php
Route::get('/{admission}/ward-rounds', [WardRoundController::class, 'index'])
    ->name('admissions.ward-rounds.index');

Route::get('/{admission}/ward-rounds/create', [WardRoundController::class, 'create'])
    ->name('admissions.ward-rounds.create');

Route::post('/{admission}/ward-rounds', [WardRoundController::class, 'store'])
    ->name('admissions.ward-rounds.store');

Route::put('/{admission}/ward-rounds/{wardRound}', [WardRoundController::class, 'update'])
    ->name('admissions.ward-rounds.update');

Route::delete('/{admission}/ward-rounds/{wardRound}', [WardRoundController::class, 'destroy'])
    ->name('admissions.ward-rounds.destroy');
```

**Wayfinder Routes Generated:**
- Location: `resources/js/routes/admissions/ward-rounds/index.ts`
- Automatically generated by Laravel Wayfinder
- Includes TypeScript type definitions

### 4. Frontend Integration

#### Ward Patient Show Page Updates
**File:** `resources/js/pages/Ward/PatientShow.tsx`

**Changes Made:**

1. **Import Wayfinder Routes:**
```tsx
import admissions from '@/routes/admissions';
```

2. **Updated "Start Ward Round" Buttons** (2 locations):
   - Overview tab - Recent Ward Rounds card
   - Ward Rounds tab

**Before:**
```tsx
<Button
    size="sm"
    onClick={() => setWardRoundModalOpen(true)}
>
    <Stethoscope className="mr-2 h-4 w-4" />
    Record Round
</Button>
```

**After:**
```tsx
<Link href={admissions.wardRounds.create.url(admission)}>
    <Button size="sm">
        <Stethoscope className="mr-2 h-4 w-4" />
        Start Ward Round
    </Button>
</Link>
```

**How Wayfinder Works:**
- Routes are auto-generated from Laravel route definitions
- Import: `import admissions from '@/routes/admissions'`
- Usage: `admissions.wardRounds.create.url(admission)`
- Generates URL: `/admissions/{admission_id}/ward-rounds/create`
- Type-safe with TypeScript

---

## 🔄 In Progress / Needs Completion

### 1. Form Request Validation
**TODO:** Create `app/Http/Requests/StoreWardRoundRequest.php`

The controller references this but it needs to be created with validation rules:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWardRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Or implement authorization logic
    }

    public function rules(): array
    {
        return [
            'round_type' => 'nullable|in:daily_round,specialist_consult,procedure_note',
            'presenting_complaint' => 'nullable|string',
            'history_presenting_complaint' => 'nullable|string',
            'on_direct_questioning' => 'nullable|string',
            'examination_findings' => 'nullable|string',
            'assessment_notes' => 'nullable|string',
            'plan_notes' => 'nullable|string',
            'patient_status' => 'nullable|in:improving,stable,deteriorating,discharge_ready,critical',
            'round_datetime' => 'nullable|date',

            // Lab orders
            'lab_orders' => 'nullable|array',
            'lab_orders.*.test_id' => 'required|exists:lab_tests,id',
            'lab_orders.*.notes' => 'nullable|string',

            // Prescriptions
            'prescriptions' => 'nullable|array',
            'prescriptions.*.drug_id' => 'required|exists:drugs,id',
            'prescriptions.*.dosage' => 'required|string',
            'prescriptions.*.frequency' => 'required|string',
            'prescriptions.*.route' => 'required|string',
            'prescriptions.*.duration' => 'nullable|string',

            // Diagnoses
            'diagnoses' => 'nullable|array',
            'diagnoses.*.icd_code' => 'required|string',
            'diagnoses.*.icd_version' => 'required|in:10,11',
            'diagnoses.*.diagnosis_name' => 'required|string',
            'diagnoses.*.diagnosis_type' => 'nullable|in:working,complication,comorbidity',
            'diagnoses.*.clinical_notes' => 'nullable|string',
        ];
    }
}
```

### 2. Ward Round Create Page
**TODO:** Create `resources/js/pages/Ward/WardRoundCreate.tsx`

This page should:
- Reuse consultation form components where possible
- Show pre-filled patient information
- Display active diagnoses from admission
- Allow adding new diagnoses
- Include lab ordering interface
- Include prescription interface
- Show recent vitals for reference
- Display day number prominently (e.g., "Ward Round - Day 3")

**Suggested Structure:**
```tsx
export default function WardRoundCreate({ admission, dayNumber, encounterType }) {
    return (
        <AppLayout>
            <Head title={`Ward Round - Day ${dayNumber}`} />

            <div className="container">
                {/* Patient Header */}
                <PatientHeader patient={admission.patient} />

                {/* Day Number Badge */}
                <Badge>Day {dayNumber}</Badge>

                {/* Active Diagnoses Display */}
                <ActiveDiagnosesCard diagnoses={admission.active_diagnoses} />

                {/* Recent Vitals */}
                <VitalsCard vitals={admission.vital_signs} />

                {/* Ward Round Form */}
                <Form action={admissions.wardRounds.store(admission)}>
                    {/* Reuse consultation form fields */}
                    <ClinicalDocumentationFields />

                    {/* Add New Diagnosis */}
                    <DiagnosisSelector />

                    {/* Lab Orders */}
                    <LabOrdersSection />

                    {/* Prescriptions */}
                    <PrescriptionSection />

                    {/* Patient Status */}
                    <PatientStatusSelect />

                    <Button type="submit">Complete Ward Round</Button>
                </Form>
            </div>
        </AppLayout>
    );
}
```

### 3. Reusable Form Components

**TODO:** Extract shared components from consultation forms:

1. **`resources/js/components/Clinical/ClinicalDocumentationFields.tsx`**
   - Presenting complaint
   - History
   - Review of systems
   - Examination findings
   - Assessment
   - Plan

2. **`resources/js/components/Clinical/DiagnosisSelector.tsx`**
   - ICD code search
   - Diagnosis type selection
   - Clinical notes

3. **`resources/js/components/Clinical/LabOrdersSection.tsx`**
   - Lab test selection
   - Order notes

4. **`resources/js/components/Clinical/PrescriptionSection.tsx`**
   - Drug selection
   - Dosage, frequency, route
   - Duration

5. **`resources/js/components/Clinical/PatientStatusSelect.tsx`**
   - Status dropdown: improving, stable, deteriorating, etc.

### 4. Polymorphic Relationships Setup

**VERIFY:** Ensure LabOrder and Prescription models support morphing:

**LabOrder Model:**
```php
public function orderable()
{
    return $this->morphTo();
}
```

**Prescription Model:**
```php
public function prescribable()
{
    return $this->morphTo();
}
```

If these don't exist, migrations may need to be created/updated.

### 5. Authorization Policies

**TODO:** Create `app/Policies/WardRoundPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WardRound;

class WardRoundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view ward rounds');
    }

    public function create(User $user): bool
    {
        return $user->can('create ward rounds');
    }

    public function update(User $user, WardRound $wardRound): bool
    {
        // Only the doctor who created it or admins can update
        return $wardRound->doctor_id === $user->id
            || $user->can('update any ward rounds');
    }

    public function delete(User $user, WardRound $wardRound): bool
    {
        return $user->can('delete ward rounds');
    }
}
```

Register in `AuthServiceProvider` or `bootstrap/providers.php`.

---

## 🧪 Testing Requirements

### 1. Database Tests
**TODO:** Create tests for models and relationships

```php
// tests/Feature/WardRoundTest.php

it('auto-calculates day number when creating ward round', function () {
    $admission = PatientAdmission::factory()->create();

    $round1 = WardRound::factory()->create([
        'patient_admission_id' => $admission->id,
    ]);

    expect($round1->day_number)->toBe(1);

    $round2 = WardRound::factory()->create([
        'patient_admission_id' => $admission->id,
    ]);

    expect($round2->day_number)->toBe(2);
});

it('can add diagnosis from ward round', function () {
    $admission = PatientAdmission::factory()->create();
    $wardRound = WardRound::factory()->create([
        'patient_admission_id' => $admission->id,
    ]);

    $diagnosis = AdmissionDiagnosis::create([
        'patient_admission_id' => $admission->id,
        'icd_code' => 'J18.9',
        'icd_version' => '10',
        'diagnosis_name' => 'Pneumonia',
        'diagnosis_type' => 'working',
        'source_type' => WardRound::class,
        'source_id' => $wardRound->id,
        'diagnosed_by' => $wardRound->doctor_id,
        'diagnosed_at' => now(),
    ]);

    expect($wardRound->diagnoses)->toHaveCount(1);
    expect($admission->diagnoses)->toHaveCount(1);
});
```

### 2. Browser Tests
**TODO:** Test complete workflow with Pest browser testing

```php
// tests/Browser/WardRoundTest.php

it('allows doctor to complete ward round', function () {
    $doctor = User::factory()->create();
    $admission = PatientAdmission::factory()->create();

    $page = $this->actingAs($doctor)
        ->visit("/wards/{$admission->ward_id}/patients/{$admission->id}");

    $page->click('Start Ward Round')
        ->assertSee('Ward Round - Day 1')
        ->fill('assessment_notes', 'Patient improving, responding well to treatment')
        ->select('patient_status', 'improving')
        ->click('Complete Ward Round')
        ->assertSee('Ward round recorded successfully');

    expect($admission->wardRounds)->toHaveCount(1);
});
```

### 3. Integration Tests
- Test ward round creation saves all related data
- Test polymorphic relationships work correctly
- Test day number calculation
- Test authorization rules

---

## 📋 Design Decisions & Rationale

### Why Separate WardRound from Consultation?

1. **Different Clinical Context:**
   - Consultations = Initial assessment & diagnosis
   - Ward Rounds = Ongoing monitoring & adjustment

2. **Workflow Efficiency:**
   - Ward rounds are briefer, more focused
   - Don't require full consultation overhead
   - Optimized for daily review pattern

3. **Data Organization:**
   - Easier to track daily progress
   - Clear audit trail of clinical decisions
   - Better reporting (rounds per day, etc.)

### Why Admission-Centric Diagnoses?

1. **Clinical Accuracy:**
   - Diagnoses evolve throughout admission
   - Need complete picture at discharge
   - Supports billing/coding requirements

2. **Flexibility:**
   - Can add diagnoses from multiple sources
   - Track when and why diagnoses change
   - Distinguish admission vs discharge diagnoses

3. **Audit Trail:**
   - Know who added each diagnosis
   - Know which consultation/round led to diagnosis
   - Track active vs resolved diagnoses

### Why Reuse Consultation Fields?

1. **Code Reuse:**
   - Same UI components
   - Consistent UX for doctors
   - Easier maintenance

2. **Familiarity:**
   - Doctors already know the interface
   - No learning curve
   - Natural workflow

3. **Flexibility:**
   - Can conditionally show/hide fields
   - Can pre-fill based on context
   - Can customize labels per encounter type

---

## 🔗 Related Files & References

### Key Files Created/Modified:
- ✅ `database/migrations/2025_10_14_002353_create_ward_rounds_table.php`
- ✅ `database/migrations/2025_10_15_110029_create_admission_diagnoses_table.php`
- ✅ `app/Models/WardRound.php`
- ✅ `app/Models/AdmissionDiagnosis.php`
- ✅ `app/Models/PatientAdmission.php` (updated)
- ✅ `app/Http/Controllers/Ward/WardRoundController.php` (updated)
- ✅ `routes/wards.php` (updated)
- ✅ `resources/js/pages/Ward/PatientShow.tsx` (updated)
- ⏳ `app/Http/Requests/StoreWardRoundRequest.php` (needs creation)
- ⏳ `resources/js/pages/Ward/WardRoundCreate.tsx` (needs creation)
- ⏳ `app/Policies/WardRoundPolicy.php` (needs creation)

### Existing Files to Reference:
- `resources/js/pages/Consultation/Show.tsx` - Consultation form structure
- `app/Http/Controllers/Consultation/ConsultationController.php` - Consultation logic
- `app/Models/Consultation.php` - Consultation model structure

### Documentation:
- Main implementation doc: `WARD_INPATIENT_CARE_IMPLEMENTATION.md`
- Project guidelines: `CLAUDE.md`

---

## 🚀 Quick Start Guide for Continuing

### To Continue Implementation:

1. **Create Form Request:**
   ```bash
   php artisan make:request StoreWardRoundRequest
   ```
   Then add validation rules as outlined above.

2. **Create Ward Round Page:**
   - Copy structure from consultation pages
   - Adjust for ward round context
   - Reuse existing form components where possible

3. **Test the Flow:**
   ```bash
   npm run dev
   ```
   Navigate to ward patient view → Click "Start Ward Round" → Should show form

4. **Run Migrations (if not done):**
   ```bash
   php artisan migrate
   ```

5. **Check Routes:**
   ```bash
   php artisan route:list --name=ward-rounds
   ```

### Common Commands:
```bash
# Frontend
npm run dev              # Start Vite dev server
npm run build           # Build for production

# Backend
php artisan serve       # Start Laravel server
php artisan test        # Run tests
vendor/bin/pint         # Format PHP code

# Database
php artisan migrate     # Run migrations
php artisan tinker      # Test models/relationships
```

---

## 💡 Tips & Best Practices

1. **When Adding New Fields:**
   - Update migration
   - Update model fillable array
   - Update form request validation
   - Update frontend form

2. **When Testing:**
   - Use factories for test data
   - Test happy path AND error cases
   - Use browser tests for complex workflows

3. **When Reusing Components:**
   - Make them flexible with props
   - Document expected props
   - Consider TypeScript interfaces

4. **Dark Mode:**
   - Always test in both light and dark mode
   - Use Tailwind's `dark:` variant

---

## 📞 Questions to Resolve

1. **Lab Orders & Prescriptions:**
   - Do these models already exist?
   - Do they support polymorphic relationships?
   - What are the exact field names?

2. **ICD Code Search:**
   - Is there an existing ICD code database?
   - Is there an API for searching codes?
   - Or should we implement a simple search?

3. **Permissions:**
   - What roles can create ward rounds? (Doctors only? Nurses?)
   - Can ward rounds be edited after creation?
   - Time limit for editing?

4. **Workflow:**
   - Should ward rounds auto-save as drafts?
   - Can ward rounds be marked as incomplete?
   - Notifications for incomplete rounds?

---

## ✨ Future Enhancements

1. **Ward Round Templates:**
   - Pre-filled templates for common scenarios
   - Specialty-specific templates

2. **Ward Round Checklist:**
   - Auto-generated checklist based on patient status
   - Track completed items

3. **Handover Notes:**
   - Generate handover summaries from ward rounds
   - Export for shift changes

4. **Analytics:**
   - Average rounds per patient
   - Time spent on rounds
   - Patient status trends

5. **Mobile Optimization:**
   - Optimize for tablet use during rounds
   - Quick-entry mode for common notes

---

**Last Updated:** October 15, 2025
**Status:** Foundation Complete - Ready for Page Implementation
**Next Milestone:** Complete WardRoundCreate page and test end-to-end workflow
