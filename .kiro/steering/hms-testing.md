---
inclusion: manual
---

# HMS Testing Guide

## Testing Philosophy

The HMS is a medical system where data accuracy and reliability are critical. Every feature must be thoroughly tested before deployment. We prioritize:

1. **Feature Tests** - Test complete workflows (primary)
2. **Unit Tests** - Test isolated business logic (services, models)
3. **Browser Tests** - Test critical user workflows (Pest v4)

## Test Organization

```
tests/
├── Feature/                           # Feature tests (most important)
│   ├── Checkin/
│   │   ├── PatientCheckinTest.php
│   │   └── VitalsRecordingTest.php
│   ├── Consultation/
│   │   ├── ConsultationWorkflowTest.php
│   │   ├── DiagnosisManagementTest.php
│   │   └── LabOrderingTest.php
│   ├── Ward/
│   │   ├── AdmissionTest.php
│   │   └── WardManagementTest.php
│   ├── Pharmacy/
│   │   ├── DispensingTest.php
│   │   └── StockManagementTest.php
│   ├── Lab/
│   │   └── LabOrderProcessingTest.php
│   ├── Billing/
│   │   ├── ChargeCreationTest.php
│   │   └── PaymentProcessingTest.php
│   └── Insurance/
│       ├── CoverageTest.php
│       ├── ClaimVettingTest.php
│       └── ClaimSubmissionTest.php
├── Unit/                              # Unit tests
│   ├── Services/
│   │   ├── BillingServiceTest.php
│   │   ├── InsuranceCoverageServiceTest.php
│   │   └── MedicationScheduleServiceTest.php
│   └── Models/
│       ├── PatientTest.php
│       └── ConsultationTest.php
└── Browser/                           # Browser tests (Pest v4)
    ├── CheckinWorkflowTest.php
    ├── ConsultationFlowTest.php
    └── InsuranceClaimTest.php
```

## Writing Feature Tests

### Basic Structure

```php
<?php

use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\Department;
use App\Models\User;

it('allows receptionist to check in a patient', function () {
    // Arrange - Set up test data
    $receptionist = User::factory()->receptionist()->create();
    $patient = Patient::factory()->create();
    $department = Department::factory()->create(['type' => 'opd']);

    // Act - Perform the action
    $response = $this->actingAs($receptionist)
        ->post('/checkin', [
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);

    // Assert - Verify the outcome
    $response->assertRedirect();
    
    expect(PatientCheckin::count())->toBe(1);
    
    $checkin = PatientCheckin::first();
    expect($checkin->patient_id)->toBe($patient->id);
    expect($checkin->department_id)->toBe($department->id);
    expect($checkin->status)->toBe('checked_in');
});
```

### Testing Authorization

```php
it('prevents unauthorized users from checking in patients', function () {
    $user = User::factory()->create(); // No receptionist role
    $patient = Patient::factory()->create();
    $department = Department::factory()->create();

    $response = $this->actingAs($user)
        ->post('/checkin', [
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);

    $response->assertForbidden();
    expect(PatientCheckin::count())->toBe(0);
});

it('allows admin to check in patients', function () {
    $admin = User::factory()->admin()->create();
    $patient = Patient::factory()->create();
    $department = Department::factory()->create();

    $response = $this->actingAs($admin)
        ->post('/checkin', [
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);

    $response->assertRedirect();
    expect(PatientCheckin::count())->toBe(1);
});
```

### Testing Validation

```php
it('requires patient_id when checking in', function () {
    $receptionist = User::factory()->receptionist()->create();
    $department = Department::factory()->create();

    $response = $this->actingAs($receptionist)
        ->post('/checkin', [
            'department_id' => $department->id,
            // Missing patient_id
        ]);

    $response->assertSessionHasErrors('patient_id');
    expect(PatientCheckin::count())->toBe(0);
});

it('requires valid patient_id', function () {
    $receptionist = User::factory()->receptionist()->create();
    $department = Department::factory()->create();

    $response = $this->actingAs($receptionist)
        ->post('/checkin', [
            'patient_id' => 99999, // Non-existent
            'department_id' => $department->id,
        ]);

    $response->assertSessionHasErrors('patient_id');
});
```

### Testing Complete Workflows

```php
it('completes full consultation workflow', function () {
    // Setup
    $doctor = User::factory()->doctor()->create();
    $patient = Patient::factory()->create();
    $department = Department::factory()->create();
    
    // 1. Check-in
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'status' => 'checked_in',
    ]);

    // 2. Record vitals
    $this->actingAs($doctor)
        ->post("/vitals/{$checkin->id}", [
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'temperature' => 37.0,
            'pulse_rate' => 72,
        ]);

    $checkin->refresh();
    expect($checkin->status)->toBe('vitals_taken');

    // 3. Start consultation
    $response = $this->actingAs($doctor)
        ->post('/consultation/start', [
            'patient_checkin_id' => $checkin->id,
        ]);

    $response->assertRedirect();
    $consultation = Consultation::first();
    expect($consultation)->not->toBeNull();
    expect($consultation->status)->toBe('in_progress');

    // 4. Add diagnosis
    $diagnosis = Diagnosis::factory()->create();
    $this->actingAs($doctor)
        ->post("/consultation/{$consultation->id}/diagnoses", [
            'diagnosis_id' => $diagnosis->id,
            'type' => 'primary',
        ]);

    expect($consultation->diagnoses()->count())->toBe(1);

    // 5. Order lab test
    $labService = LabService::factory()->create();
    $this->actingAs($doctor)
        ->post("/consultation/{$consultation->id}/lab-orders", [
            'lab_service_id' => $labService->id,
            'priority' => 'routine',
        ]);

    expect($consultation->labOrders()->count())->toBe(1);

    // 6. Complete consultation
    $this->actingAs($doctor)
        ->post("/consultation/{$consultation->id}/complete");

    $consultation->refresh();
    expect($consultation->status)->toBe('completed');
    expect($consultation->completed_at)->not->toBeNull();
});
```

### Testing Event-Driven Billing

```php
use App\Events\PrescriptionCreated;
use App\Models\Charge;
use Illuminate\Support\Facades\Event;

it('creates charge when prescription is created', function () {
    Event::fake([PrescriptionCreated::class]);

    $consultation = Consultation::factory()->create();
    $drug = Drug::factory()->create(['unit_price' => 10.00]);

    $prescription = Prescription::factory()->create([
        'consultation_id' => $consultation->id,
        'drug_id' => $drug->id,
        'quantity' => 5,
    ]);

    Event::assertDispatched(PrescriptionCreated::class);

    // Manually dispatch to test listener
    event(new PrescriptionCreated($prescription));

    $charge = Charge::where('prescription_id', $prescription->id)->first();
    expect($charge)->not->toBeNull();
    expect($charge->amount)->toBe(50.00); // 10 × 5
    expect($charge->service_type)->toBe('medication');
});
```

### Testing Insurance Coverage

```php
it('applies correct coverage for covered drug', function () {
    $plan = InsurancePlan::factory()->create();
    
    // Create category default: 80% coverage
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drugs',
        'item_code' => null,
        'coverage_value' => 80.00,
    ]);

    $drug = Drug::factory()->create([
        'drug_code' => 'DRG001',
        'unit_price' => 100.00,
    ]);

    $service = app(InsuranceCoverageService::class);
    $coverage = $service->determineCoverage(
        $plan->id,
        'drugs',
        $drug->drug_code,
        100.00
    );

    expect($coverage['is_covered'])->toBeTrue();
    expect($coverage['coverage_percentage'])->toBe(80.0);
    expect($coverage['insurance_pays'])->toBe(80.0);
    expect($coverage['patient_pays'])->toBe(20.0);
});

it('applies exception coverage over category default', function () {
    $plan = InsurancePlan::factory()->create();
    
    // Category default: 80%
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drugs',
        'item_code' => null,
        'coverage_value' => 80.00,
    ]);

    // Exception: 100% for specific drug
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drugs',
        'item_code' => 'DRG001',
        'coverage_value' => 100.00,
    ]);

    $service = app(InsuranceCoverageService::class);
    $coverage = $service->determineCoverage(
        $plan->id,
        'drugs',
        'DRG001',
        100.00
    );

    expect($coverage['coverage_percentage'])->toBe(100.0);
    expect($coverage['insurance_pays'])->toBe(100.0);
    expect($coverage['patient_pays'])->toBe(0.0);
});
```

## Writing Unit Tests

### Testing Services

```php
use App\Services\MedicationScheduleService;

it('generates correct schedule for TDS frequency', function () {
    $service = new MedicationScheduleService();
    
    $schedule = $service->generateSchedule(
        frequency: 'TDS',        // 3 times daily
        duration: '3 days',
        startDate: '2025-01-15 08:00:00'
    );

    expect($schedule)->toHaveCount(9); // 3 times × 3 days

    // Check first day times
    expect($schedule[0]->format('H:i'))->toBe('08:00');
    expect($schedule[1]->format('H:i'))->toBe('14:00');
    expect($schedule[2]->format('H:i'))->toBe('20:00');
});

it('generates correct schedule for BD frequency', function () {
    $service = new MedicationScheduleService();
    
    $schedule = $service->generateSchedule(
        frequency: 'BD',         // 2 times daily
        duration: '5 days',
        startDate: '2025-01-15 08:00:00'
    );

    expect($schedule)->toHaveCount(10); // 2 times × 5 days
});
```

### Testing Model Methods

```php
use App\Models\Patient;

it('generates correct patient number', function () {
    $patient = Patient::factory()->create();
    
    expect($patient->patient_number)->toMatch('/^PAT\d{10}$/');
});

it('calculates age correctly', function () {
    $patient = Patient::factory()->create([
        'date_of_birth' => now()->subYears(30)->toDateString(),
    ]);

    expect($patient->age)->toBe(30);
});

it('returns full name', function () {
    $patient = Patient::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    expect($patient->full_name)->toBe('John Doe');
});
```

## Browser Testing (Pest v4)

### Basic Browser Test

```php
use function Pest\Laravel\visit;

it('allows user to check in a patient via browser', function () {
    $receptionist = User::factory()->receptionist()->create();
    $patient = Patient::factory()->create();
    $department = Department::factory()->create();

    $this->actingAs($receptionist);

    $page = visit('/checkin');

    $page->assertSee('Check-in')
        ->assertNoJavascriptErrors()
        ->click('Search Patient')
        ->fill('query', $patient->patient_number)
        ->click('Search')
        ->assertSee($patient->full_name)
        ->click('Check In')
        ->select('department_id', $department->id)
        ->click('Confirm Check-in')
        ->assertSee('Patient checked in successfully');

    expect(PatientCheckin::count())->toBe(1);
});
```

### Testing Complex Interactions

```php
it('completes consultation workflow in browser', function () {
    $doctor = User::factory()->doctor()->create();
    $checkin = PatientCheckin::factory()
        ->withVitals()
        ->create(['status' => 'awaiting_consultation']);

    $this->actingAs($doctor);

    $page = visit('/consultation');

    $page->assertSee('Patients Awaiting Consultation')
        ->click("Start Consultation for {$checkin->patient->full_name}")
        ->assertSee('SOAP Notes')
        ->fill('presenting_complaint', 'Headache for 3 days')
        ->fill('examination_findings', 'BP: 120/80, Temp: 37.0')
        ->click('Save Notes')
        ->assertSee('Notes saved')
        ->click('Add Diagnosis')
        ->fill('diagnosis_search', 'Tension headache')
        ->click('Select Diagnosis')
        ->click('Complete Consultation')
        ->assertSee('Consultation completed');

    $consultation = Consultation::where('patient_checkin_id', $checkin->id)->first();
    expect($consultation->status)->toBe('completed');
});
```

## Using Factories

### Basic Factory Usage

```php
// Create single model
$patient = Patient::factory()->create();

// Create multiple models
$patients = Patient::factory()->count(5)->create();

// Override attributes
$patient = Patient::factory()->create([
    'first_name' => 'John',
    'last_name' => 'Doe',
]);
```

### Factory States

```php
// In PatientFactory.php
public function male(): static
{
    return $this->state(fn (array $attributes) => [
        'gender' => 'male',
    ]);
}

public function withInsurance(): static
{
    return $this->afterCreating(function (Patient $patient) {
        PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'active',
        ]);
    });
}

// Usage
$patient = Patient::factory()->male()->withInsurance()->create();
```

### Factory Relationships

```php
// Create patient with check-in
$checkin = PatientCheckin::factory()
    ->for(Patient::factory())
    ->for(Department::factory())
    ->create();

// Create consultation with related data
$consultation = Consultation::factory()
    ->for(PatientCheckin::factory())
    ->for(User::factory()->doctor(), 'doctor')
    ->has(Prescription::factory()->count(3))
    ->has(LabOrder::factory()->count(2))
    ->create();
```

## Testing Best Practices

### 1. Use Descriptive Test Names

```php
// ✅ Good
it('prevents doctor from starting consultation without vitals')

// ❌ Bad
it('test consultation')
```

### 2. Follow AAA Pattern

```php
it('creates charge when prescription is dispensed', function () {
    // Arrange
    $prescription = Prescription::factory()->create();
    
    // Act
    $this->post("/pharmacy/dispense/{$prescription->id}");
    
    // Assert
    expect(Charge::where('prescription_id', $prescription->id)->exists())
        ->toBeTrue();
});
```

### 3. Test One Thing Per Test

```php
// ✅ Good - Separate tests
it('validates required fields')
it('validates field formats')
it('validates business rules')

// ❌ Bad - Testing everything
it('validates form')
```

### 4. Use Datasets for Similar Tests

```php
it('validates required fields', function ($field) {
    $data = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => 'male',
    ];
    
    unset($data[$field]);
    
    $response = $this->post('/patients', $data);
    
    $response->assertSessionHasErrors($field);
})->with(['first_name', 'last_name', 'gender']);
```

### 5. Clean Up After Tests

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Database is automatically reset after each test
```

### 6. Mock External Services

```php
use Illuminate\Support\Facades\Http;

it('submits claim to insurance API', function () {
    Http::fake([
        'insurance-api.com/*' => Http::response(['status' => 'success'], 200),
    ]);

    $claim = InsuranceClaim::factory()->create();
    
    $this->post("/insurance/claims/{$claim->id}/submit");
    
    Http::assertSent(function ($request) use ($claim) {
        return $request->url() === 'https://insurance-api.com/claims' &&
               $request['claim_id'] === $claim->id;
    });
});
```

## Running Tests

### Run All Tests

```bash
php artisan test
```

### Run Specific Test File

```bash
php artisan test tests/Feature/Consultation/ConsultationWorkflowTest.php
```

### Run Tests by Filter

```bash
php artisan test --filter=consultation
php artisan test --filter="allows doctor to start consultation"
```

### Run Tests with Coverage

```bash
php artisan test --coverage
php artisan test --coverage --min=80
```

### Run Parallel Tests

```bash
php artisan test --parallel
```

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: mbstring, pdo_mysql
      
      - name: Install Dependencies
        run: composer install
      
      - name: Copy .env
        run: cp .env.example .env
      
      - name: Generate Key
        run: php artisan key:generate
      
      - name: Run Tests
        run: php artisan test
      
      - name: Run Pint
        run: vendor/bin/pint --test
```

## Test Coverage Goals

Aim for these coverage levels:

- **Critical Paths**: 100% (billing, insurance, prescriptions)
- **Business Logic**: 90%+ (services, models)
- **Controllers**: 80%+ (feature tests)
- **Overall**: 80%+

## Common Testing Patterns

### Testing Permissions

```php
it('allows only authorized users', function ($role, $canAccess) {
    $user = User::factory()->$role()->create();
    
    $response = $this->actingAs($user)->get('/admin/settings');
    
    if ($canAccess) {
        $response->assertOk();
    } else {
        $response->assertForbidden();
    }
})->with([
    ['admin', true],
    ['doctor', false],
    ['nurse', false],
    ['receptionist', false],
]);
```

### Testing Timestamps

```php
use Illuminate\Support\Facades\Date;

it('records timestamp correctly', function () {
    Date::setTestNow('2025-01-15 10:00:00');
    
    $checkin = PatientCheckin::factory()->create();
    
    expect($checkin->checked_in_at->toDateTimeString())
        ->toBe('2025-01-15 10:00:00');
});
```

### Testing Queued Jobs

```php
use Illuminate\Support\Facades\Queue;

it('dispatches notification job', function () {
    Queue::fake();
    
    $claim = InsuranceClaim::factory()->create();
    
    $this->post("/insurance/claims/{$claim->id}/submit");
    
    Queue::assertPushed(SendClaimSubmittedNotification::class);
});
```

## Debugging Tests

### Use dump() and dd()

```php
it('debugs test data', function () {
    $patient = Patient::factory()->create();
    
    dump($patient->toArray()); // Print and continue
    // dd($patient->toArray());   // Print and stop
    
    expect($patient->status)->toBe('active');
});
```

### Use Ray for Debugging

```php
it('debugs with ray', function () {
    $patient = Patient::factory()->create();
    
    ray($patient);
    ray($patient->checkins);
    
    expect($patient->status)->toBe('active');
});
```

---

**Remember**: Tests are documentation. Write clear, descriptive tests that explain what the system should do. Test the happy path, failure paths, and edge cases.
