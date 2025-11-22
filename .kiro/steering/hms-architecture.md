---
inclusion: always
---

# Hospital Management System (HMS) - Architecture & Conventions

## Project Overview

This is a comprehensive Hospital Management System built with Laravel 12, React 19, and Inertia.js v2. The system manages complete hospital operations including patient registration, check-in, consultations, ward management, pharmacy, laboratory, billing, and insurance claims processing.

## Core Modules

### 1. Patient Management
- Patient registration with auto-generated patient numbers (PAT2025000001)
- Multi-visit history tracking
- Patient demographics and medical history
- Insurance information management

### 2. Check-in & OPD
- Walk-in patient check-in workflow
- Department-based queue management
- Vitals recording with BMI auto-calculation
- Status tracking: checked_in → vitals_taken → awaiting_consultation

### 3. Consultation
- SOAP notes format (Subjective, Objective, Assessment, Plan)
- Multiple diagnosis support with ICD-10 codes
- Lab test ordering
- Prescription management
- Department-based collaborative access

### 4. Ward Management & Admissions
- Physical ward and bed management
- Patient admission workflow
- Ward rounds with SOAP documentation
- Medication administration records (MAR)
- Nursing notes and vitals scheduling

### 5. Pharmacy
- Drug inventory with batch tracking
- Prescription dispensing
- Stock management with expiry alerts
- Automatic billing integration

### 6. Laboratory
- Lab service catalog with configurable test parameters
- Lab order management with priority levels
- Dynamic result entry forms
- Sample tracking and result reporting

### 7. Billing & Revenue
- Itemized billing for all services
- Multiple payment methods
- Partial payments and advance deposits
- Service-specific charge rules
- Automatic charge generation via events

### 8. Insurance Management
- Insurance provider and plan management
- Coverage rules by category (consultation, drugs, labs, procedures, diagnostics, consumables)
- Coverage exceptions with custom tariffs
- Claims submission and vetting workflow
- Batch claim processing

### 9. Minor Procedures
- Dedicated department for minor procedures (wound care, suturing, dressing changes, etc.)
- Procedure type catalog with individual pricing
- Diagnosis association with procedures
- Supply request and dispensing workflow
- Automatic billing for procedures and supplies
- Integration with patient history

## Architecture Patterns

### Domain-Driven Structure

Controllers are organized by domain, not by resource type:

```
app/Http/Controllers/
├── Patient/
│   └── PatientController.php          # All patient operations
├── Checkin/
│   └── CheckinController.php          # Check-in workflow
├── Consultation/
│   ├── ConsultationController.php     # Consultation management
│   ├── DiagnosisController.php        # Diagnosis operations
│   └── LabOrderController.php         # Lab ordering
├── Ward/
│   └── WardController.php             # Ward CRUD
├── Admission/
│   └── AdmissionController.php        # Admission workflow
├── Pharmacy/
│   ├── DrugController.php             # Drug management
│   └── DispensingController.php       # Dispensing operations
├── Lab/
│   ├── LabServiceController.php       # Lab service management
│   └── LabOrderController.php         # Lab order processing
├── Billing/
│   └── BillingController.php          # Billing operations
├── Insurance/
│   ├── ProviderController.php         # Insurance providers
│   ├── PlanController.php             # Insurance plans
│   ├── CoverageController.php         # Coverage management
│   └── ClaimController.php            # Claims processing
└── MinorProcedure/
    └── MinorProcedureController.php   # Minor procedures workflow
```

### Event-Driven Billing

Charges are automatically created via Laravel events:

```php
// Events
PatientCheckedIn → CreateConsultationCharge
LabTestOrdered → CreateLabTestCharge
PrescriptionCreated → CreateMedicationCharge
PatientAdmitted → CreateWardCharge
MinorProcedurePerformed → CreateMinorProcedureCharge

// Listeners handle charge creation
class CreateConsultationCharge
{
    public function handle(PatientCheckedIn $event)
    {
        Charge::create([
            'patient_checkin_id' => $event->checkin->id,
            'service_type' => 'consultation',
            'amount' => $departmentBilling->consultation_fee,
            // ...
        ]);
    }
}
```

### Service Layer Pattern

Complex business logic lives in service classes:

```php
app/Services/
├── BillingService.php                 # Billing calculations
├── InsuranceClaimService.php          # Claim processing
├── InsuranceCoverageService.php       # Coverage determination
├── DispensingService.php              # Pharmacy dispensing
├── PharmacyBillingService.php         # Pharmacy charge calculations
├── PharmacyStockService.php           # Stock management
├── MedicationScheduleService.php      # MAR scheduling
└── VitalsScheduleService.php          # Vitals alerts
```

### Policy-Based Authorization

All authorization uses Laravel policies with granular permissions:

```php
// In Controller
$this->authorize('viewAny', PatientCheckin::class);
$this->authorize('create', Consultation::class);

// In Policy
public function viewAny(User $user): bool
{
    return $user->can('checkins.view-all') 
        || $user->can('checkins.view-dept');
}

// Query Scopes for filtering
$consultations = Consultation::accessibleTo($user)->get();
```

### Permission Naming Convention

Format: `resource.action-scope`

Examples:
- `patients.view-all` - View all patients
- `patients.view-dept` - View department patients only
- `consultations.view-own` - View only own consultations
- `consultations.create` - Create consultations
- `billing.manage` - Manage billing
- `insurance.vet-claims` - Vet insurance claims
- `minor-procedures.perform` - Perform minor procedures
- `minor-procedures.view-dept` - View department procedure queue

## Database Conventions

### Auto-Generated IDs

Models use auto-generated unique identifiers:

```php
// Patient: PAT2025000001
// Admission: ADM2025000001
// Claim Check Code: CC-20250115-0001
```

### Polymorphic Relationships

Used for flexible associations:

```php
// Prescriptions can belong to Consultation or PatientAdmission
prescribable_type, prescribable_id

// Lab orders can belong to Consultation or PatientAdmission
orderable_type, orderable_id
```

### Status Enums

Consistent status tracking across modules:

```php
// Patient Check-in
'checked_in', 'vitals_taken', 'awaiting_consultation', 
'in_consultation', 'completed', 'admitted'

// Consultation
'in_progress', 'completed'

// Prescription
'pending', 'dispensed', 'partially_dispensed', 'discontinued'

// Lab Order
'pending', 'sample_collected', 'in_progress', 'completed'

// Insurance Claim
'draft', 'pending_vetting', 'vetted', 'submitted', 
'approved', 'rejected', 'paid'

// Minor Procedure
'completed'
```

### JSON Columns

Used for flexible data storage:

```php
// Lab test parameters configuration
test_parameters: {
    "parameters": [
        {
            "name": "hemoglobin",
            "label": "Hemoglobin",
            "type": "numeric",
            "unit": "g/dL",
            "normal_range": {"min": 12.0, "max": 16.0}
        }
    ]
}

// Medication schedule pattern
schedule_pattern: {
    "times": ["08:00", "14:00", "20:00"],
    "days": ["monday", "tuesday", "wednesday"]
}
```

## Frontend Conventions

### Component Organization

```
resources/js/
├── pages/                             # Inertia pages
│   ├── Checkin/
│   │   └── Index.tsx                  # Main check-in page
│   ├── Consultation/
│   │   ├── Index.tsx                  # Doctor dashboard
│   │   └── Show.tsx                   # Consultation interface
│   ├── Ward/
│   │   ├── Index.tsx                  # Ward list
│   │   └── Show.tsx                   # Ward details
│   ├── Insurance/
│   │   ├── Plans/
│   │   ├── Coverage/
│   │   └── Claims/
│   └── MinorProcedure/
│       ├── Index.tsx                  # Procedure queue
│       └── Configuration/             # Procedure type management
├── components/                        # Reusable components
│   ├── Patient/
│   │   ├── SearchForm.tsx
│   │   └── RegistrationForm.tsx
│   ├── Checkin/
│   │   ├── TodaysList.tsx
│   │   └── VitalsModal.tsx
│   ├── MinorProcedure/
│   │   └── ProcedureForm.tsx          # Procedure documentation form
│   └── ui/                            # Shadcn/ui components
└── lib/
    └── utils.ts                       # Utility functions
```

### Form Handling

Use Inertia `<Form>` component for multi-field forms:

```tsx
import { Form } from '@inertiajs/react'

<Form action="/checkin" method="post">
    {({ errors, processing }) => (
        <>
            <Input name="patient_id" />
            {errors.patient_id && <span>{errors.patient_id}</span>}
            <Button disabled={processing}>Submit</Button>
        </>
    )}
</Form>
```

Use `router.post()` for simple single-action submissions:

```tsx
import { router } from '@inertiajs/react'

const handleStart = () => {
    router.post('/consultation/start', {
        patient_checkin_id: checkin.id
    });
};
```

### Inertia Response Pattern

Controllers must return Inertia redirects, never JSON:

```php
// ✅ Correct
return redirect()->back()->with('success', 'Patient checked in');

// ❌ Wrong
return response()->json(['message' => 'Success']);
```

## Business Logic Patterns

### Department-Based Access

Doctors can access all patients in their assigned departments:

```php
// User can have multiple departments
$user->departments()->attach([1, 2, 3]);

// Check access
if ($user->departments->contains($patient->department_id)) {
    // Allow access
}

// Query scope
Consultation::whereHas('patientCheckin', function ($query) use ($user) {
    $query->whereIn('department_id', $user->departments->pluck('id'));
})->get();
```

### Insurance Coverage Determination

Coverage is determined by category-level rules and item-specific exceptions:

```php
// 1. Check for item-specific exception
$exception = InsuranceCoverageRule::where('insurance_plan_id', $planId)
    ->where('coverage_category', 'drugs')
    ->where('item_code', $drugCode)
    ->first();

if ($exception) {
    return $exception->coverage_value; // Use exception
}

// 2. Fall back to category default
$categoryRule = InsuranceCoverageRule::where('insurance_plan_id', $planId)
    ->where('coverage_category', 'drugs')
    ->whereNull('item_code')
    ->first();

return $categoryRule->coverage_value ?? 0; // Use default or 0%
```

### Automatic Billing Integration

Services automatically create charges via events:

```php
// When prescription is created
event(new PrescriptionCreated($prescription));

// Listener creates charge
Charge::create([
    'patient_checkin_id' => $prescription->consultation->patient_checkin_id,
    'prescription_id' => $prescription->id,
    'service_type' => 'medication',
    'service_code' => $prescription->drug->drug_code,
    'description' => $prescription->drug->name,
    'amount' => $prescription->drug->unit_price * $prescription->quantity,
    'status' => 'pending',
]);
```

### Medication Administration Scheduling

Prescriptions generate scheduled administration times:

```php
// Service generates schedule based on frequency
$schedule = MedicationScheduleService::generateSchedule(
    $prescription->frequency,      // "TDS" (3 times daily)
    $prescription->duration,       // "7 days"
    $admission->admitted_at
);

// Creates MedicationAdministration records
foreach ($schedule as $scheduledTime) {
    MedicationAdministration::create([
        'prescription_id' => $prescription->id,
        'scheduled_time' => $scheduledTime,
        'status' => 'pending',
    ]);
}
```

## Testing Conventions

### Test Organization

```
tests/
├── Feature/                           # Feature tests (primary)
│   ├── Checkin/
│   │   └── CheckinWorkflowTest.php
│   ├── Consultation/
│   │   └── ConsultationTest.php
│   └── Insurance/
│       ├── CoverageTest.php
│       └── ClaimTest.php
└── Unit/                              # Unit tests (for services)
    ├── Services/
    │   ├── BillingServiceTest.php
    │   └── CoverageServiceTest.php
    └── Models/
        └── PatientTest.php
```

### Test Patterns

Use factories for model creation:

```php
it('creates a consultation', function () {
    $checkin = PatientCheckin::factory()->create();
    $doctor = User::factory()->doctor()->create();
    
    $response = $this->actingAs($doctor)
        ->post('/consultation/start', [
            'patient_checkin_id' => $checkin->id,
        ]);
    
    $response->assertRedirect();
    expect(Consultation::count())->toBe(1);
});
```

Test all paths (happy, failure, edge cases):

```php
it('prevents consultation without vitals', function () {
    $checkin = PatientCheckin::factory()
        ->withoutVitals()
        ->create();
    
    $response = $this->actingAs($doctor)
        ->post('/consultation/start', [
            'patient_checkin_id' => $checkin->id,
        ]);
    
    $response->assertForbidden();
});
```

## Key Business Rules

### 1. Check-in Workflow
- Patient must be registered before check-in
- Vitals must be recorded before consultation
- One active check-in per patient per day

### 2. Consultation Access
- Any doctor in the department can access patient consultations
- Admin users have access to all consultations
- Consultation must be started before adding diagnoses/prescriptions

### 3. Ward Admissions
- Doctor selects ward during admission
- Nurse assigns specific bed when patient arrives
- Bed count automatically decrements on admission
- Cannot delete ward with active admissions

### 4. Pharmacy Dispensing
- Prescription must exist before dispensing
- Stock must be available (FIFO by expiry date)
- Automatic charge creation on dispensing
- Batch tracking for all dispensed medications

### 5. Insurance Claims
- Claims must be vetted before submission
- Coverage determined by category rules + exceptions
- Tariffs can be plan-specific or use standard pricing
- Claims can be submitted individually or in batches

### 6. Billing
- Charges created automatically via events
- Multiple payment methods supported
- Partial payments allowed
- Service-specific payment rules (before/after service)

### 7. Minor Procedures
- Procedures performed in dedicated Minor Procedures department
- Each procedure type has individual pricing (separate from consultation fee)
- Supplies requested during procedure, dispensed by pharmacy
- Automatic charge creation for procedures and supplies
- Procedures appear in patient history

## Common Pitfalls to Avoid

### 1. Don't bypass events for charge creation
```php
// ❌ Wrong - creates charge manually
Charge::create([...]);

// ✅ Correct - dispatch event
event(new PrescriptionCreated($prescription));
```

### 2. Don't use direct permission checks
```php
// ❌ Wrong
if ($user->can('consultations.view-all')) { ... }

// ✅ Correct - use policies
$this->authorize('viewAny', Consultation::class);
```

### 3. Don't return JSON from Inertia controllers
```php
// ❌ Wrong
return response()->json(['success' => true]);

// ✅ Correct
return redirect()->back()->with('success', 'Operation completed');
```

### 4. Don't forget query scopes for department filtering
```php
// ❌ Wrong - shows all consultations
$consultations = Consultation::all();

// ✅ Correct - filters by user access
$consultations = Consultation::accessibleTo($user)->get();
```

### 5. Don't create models without factories in tests
```php
// ❌ Wrong
$patient = Patient::create([
    'first_name' => 'John',
    'last_name' => 'Doe',
    // ... many fields
]);

// ✅ Correct
$patient = Patient::factory()->create();
```

## Performance Considerations

### Eager Loading

Always eager load relationships to prevent N+1 queries:

```php
// ✅ Good
$checkins = PatientCheckin::with([
    'patient',
    'department',
    'vitalSigns',
    'consultation.diagnoses'
])->get();

// ❌ Bad - causes N+1
$checkins = PatientCheckin::all();
foreach ($checkins as $checkin) {
    echo $checkin->patient->name; // N+1 query
}
```

### Query Optimization

Use indexes for frequently queried columns:

```php
// Migrations include indexes for:
- patient_number (unique)
- phone_number
- department_id + status
- patient_id + checked_in_at
- status + priority (lab orders)
```

### Caching

Cache frequently accessed configuration:

```php
// System configurations
$config = Cache::remember('billing.config', 3600, function () {
    return BillingConfiguration::where('is_active', true)->get();
});
```

## Security Best Practices

### 1. Always use policies for authorization
### 2. Validate all user input with Form Requests
### 3. Use query scopes to filter data by user access
### 4. Never expose sensitive patient data in logs
### 5. Audit trail for critical operations (billing, insurance)

## Integration Points

### External Systems (Future)

The system is designed to integrate with:
- Insurance company APIs for real-time claim submission
- Laboratory equipment for automated result import
- Pharmacy management systems
- Payment gateways for online payments
- SMS/Email notification services

## Development Workflow

### 1. Feature Development
- Create migration for database changes
- Create/update models with relationships
- Create policy for authorization
- Create Form Request for validation
- Create controller with business logic
- Create service class if complex logic
- Create Inertia page/component
- Write feature tests
- Run tests and Pint formatter

### 2. Testing Before Commit
```bash
# Run affected tests
php artisan test --filter=ConsultationTest

# Format code
vendor/bin/pint

# Check for errors
php artisan test
```

### 3. Code Review Checklist
- [ ] Policies used for authorization
- [ ] Form Requests used for validation
- [ ] Events dispatched for billing
- [ ] Query scopes used for filtering
- [ ] Eager loading prevents N+1
- [ ] Tests cover happy and failure paths
- [ ] Inertia responses (not JSON)
- [ ] Code formatted with Pint

## Useful Commands

```bash
# Create domain controller
php artisan make:controller Patient/PatientController

# Create model with factory and migration
php artisan make:model PatientAdmission -mf

# Create policy
php artisan make:policy ConsultationPolicy --model=Consultation

# Create Form Request
php artisan make:request StoreConsultationRequest

# Create event and listener
php artisan make:event PrescriptionCreated
php artisan make:listener CreateMedicationCharge --event=PrescriptionCreated

# Run tests
php artisan test
php artisan test --filter=ConsultationTest

# Format code
vendor/bin/pint
```

## Resources

- Laravel 12 Documentation: https://laravel.com/docs/12.x
- Inertia.js v2 Documentation: https://inertiajs.com
- React 19 Documentation: https://react.dev
- Tailwind CSS v4 Documentation: https://tailwindcss.com
- Shadcn/ui Components: https://ui.shadcn.com

---

**Remember**: This is a medical system. Data accuracy, security, and audit trails are critical. Always test thoroughly and follow established patterns.