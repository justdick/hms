<?php

/**
 * Multi-Department Same-Day Check-in Tests
 *
 * These tests validate the multi-department check-in feature which allows
 * patients to check in to multiple departments on the same day while
 * preventing duplicate check-ins to the same department.
 *
 * @see .kiro/specs/multi-department-checkin/requirements.md
 * @see .kiro/specs/multi-department-checkin/design.md
 */

use App\Models\Consultation;
use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\User;
use App\Models\Ward;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $this->user->assignRole($adminRole);

    $this->patient = Patient::factory()->create();
    $this->departmentA = Department::factory()->create(['name' => 'General OPD', 'type' => 'opd']);
    $this->departmentB = Department::factory()->create(['name' => 'ANC', 'type' => 'opd']);
    $this->user->departments()->attach([$this->departmentA->id, $this->departmentB->id]);
});

describe('Property 1: Same-Department Same-Day Block', function () {
    /**
     * Property 1: Same-Department Same-Day Block
     *
     * For any patient and department, if the patient already has a non-cancelled
     * check-in to that department on a given day, attempting another check-in
     * to the same department on the same day SHALL be rejected with a specific
     * error message.
     *
     * **Validates: Requirements 1.2, 1.3**
     */
    it('blocks same-department same-day check-in with specific error message', function () {
        // Create first check-in to department A
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today(),
            'status' => 'checked_in',
        ]);

        // Attempt second check-in to same department
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
        ]);

        $response->assertSessionHasErrors('department_id');
        $errors = session('errors')->get('department_id');
        expect($errors[0])->toContain($this->departmentA->name)
            ->and($errors[0])->toContain('already checked in');
    });

    it('blocks same-department same-day check-in regardless of check-in status', function () {
        $statuses = ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation', 'completed'];

        foreach ($statuses as $status) {
            // Clean up previous check-ins
            PatientCheckin::where('patient_id', $this->patient->id)->delete();

            // Create check-in with specific status
            PatientCheckin::factory()->create([
                'patient_id' => $this->patient->id,
                'department_id' => $this->departmentA->id,
                'service_date' => today(),
                'status' => $status,
            ]);

            // Attempt second check-in to same department
            $response = $this->actingAs($this->user)->post('/checkin/checkins', [
                'patient_id' => $this->patient->id,
                'department_id' => $this->departmentA->id,
            ]);

            $response->assertSessionHasErrors('department_id');
        }
    });

    it('allows same-department check-in if previous was cancelled', function () {
        // Create cancelled check-in
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today(),
            'status' => 'cancelled',
        ]);

        // Attempt new check-in to same department
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    });

    it('allows same-department check-in on different days', function () {
        // Create check-in from yesterday
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today()->subDay(),
            'status' => 'completed',
        ]);

        // Attempt check-in today to same department
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    });
});

describe('Property 2: Different-Department Same-Day Allow', function () {
    /**
     * Property 2: Different-Department Same-Day Allow
     *
     * For any patient with an existing check-in to Department A on a given day,
     * checking in to Department B (where B ≠ A) on the same day SHALL succeed
     * and create a separate check-in record.
     *
     * **Validates: Requirements 1.1, 1.4**
     */
    it('allows different-department same-day check-in', function () {
        // Create first check-in to department A (completed)
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today(),
            'status' => 'completed',
        ]);

        // Attempt check-in to department B
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentB->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify two separate check-ins exist
        $checkins = PatientCheckin::where('patient_id', $this->patient->id)
            ->whereDate('service_date', today())
            ->get();

        expect($checkins)->toHaveCount(2);
        expect($checkins->pluck('department_id')->unique()->count())->toBe(2);
    });

    it('creates separate records for each department check-in', function () {
        // Create first check-in to department A (completed)
        $checkinA = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today(),
            'status' => 'completed',
        ]);

        // Check-in to department B
        $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentB->id,
        ]);

        $checkinB = PatientCheckin::where('patient_id', $this->patient->id)
            ->where('department_id', $this->departmentB->id)
            ->first();

        // Verify they are separate records
        expect($checkinA->id)->not->toBe($checkinB->id);
        expect($checkinA->department_id)->toBe($this->departmentA->id);
        expect($checkinB->department_id)->toBe($this->departmentB->id);
    });
});

describe('Property 3: Admission Warning Flow', function () {
    /**
     * Property 3: Admission Warning Flow
     *
     * For any patient with an active admission, attempting OPD check-in SHALL
     * return an admission warning. If the user confirms to proceed, the check-in
     * SHALL be created with `created_during_admission = true`.
     *
     * **Validates: Requirements 2.1, 2.3, 2.4**
     */
    it('returns admission warning for patient with active admission', function () {
        // Create ward for admission
        $ward = Ward::factory()->create();

        // Create consultation for admission (linked to patient via checkin)
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'status' => 'completed',
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
        ]);

        // Create active admission
        PatientAdmission::factory()->create([
            'patient_id' => $this->patient->id,
            'consultation_id' => $consultation->id,
            'ward_id' => $ward->id,
            'status' => 'admitted',
        ]);

        // Attempt check-in without confirmation
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
        ]);

        $response->assertSessionHasErrors('admission_warning');
        $response->assertSessionHas('admission_details');

        $admissionDetails = session('admission_details');
        expect($admissionDetails)->toHaveKey('admission_number')
            ->and($admissionDetails)->toHaveKey('ward')
            ->and($admissionDetails)->toHaveKey('admitted_at');
    });

    it('allows check-in with admission when confirmed', function () {
        // Create ward for admission
        $ward = Ward::factory()->create();

        // Create consultation for admission (linked to patient via checkin)
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'status' => 'completed',
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
        ]);

        // Create active admission
        PatientAdmission::factory()->create([
            'patient_id' => $this->patient->id,
            'consultation_id' => $consultation->id,
            'ward_id' => $ward->id,
            'status' => 'admitted',
        ]);

        // Attempt check-in with confirmation
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'confirm_admission_override' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify check-in was created with flag
        $newCheckin = PatientCheckin::where('patient_id', $this->patient->id)
            ->where('department_id', $this->departmentA->id)
            ->latest()
            ->first();

        expect($newCheckin->created_during_admission)->toBeTrue();
    });

    it('sets created_during_admission flag correctly', function () {
        // Create ward for admission
        $ward = Ward::factory()->create();

        // Create consultation for admission (linked to patient via checkin)
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'status' => 'completed',
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
        ]);

        // Create active admission
        PatientAdmission::factory()->create([
            'patient_id' => $this->patient->id,
            'consultation_id' => $consultation->id,
            'ward_id' => $ward->id,
            'status' => 'admitted',
        ]);

        // Check-in with confirmation
        $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'confirm_admission_override' => true,
        ]);

        $newCheckin = PatientCheckin::where('patient_id', $this->patient->id)
            ->where('department_id', $this->departmentA->id)
            ->latest()
            ->first();

        expect($newCheckin->created_during_admission)->toBeTrue();
    });

    it('does not set flag for patient without active admission', function () {
        // Check-in without any admission
        $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
        ]);

        $newCheckin = PatientCheckin::where('patient_id', $this->patient->id)
            ->where('department_id', $this->departmentA->id)
            ->latest()
            ->first();

        expect($newCheckin->created_during_admission)->toBeFalse();
    });

    it('does not warn for discharged admission', function () {
        // Create ward for admission
        $ward = Ward::factory()->create();

        // Create consultation for admission (linked to patient via checkin)
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'status' => 'completed',
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
        ]);

        // Create discharged admission
        PatientAdmission::factory()->discharged()->create([
            'patient_id' => $this->patient->id,
            'consultation_id' => $consultation->id,
            'ward_id' => $ward->id,
        ]);

        // Attempt check-in without confirmation
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    });
});

describe('Property 4: Specific Error Messages', function () {
    /**
     * Property 4: Specific Error Messages
     *
     * For any check-in validation failure, the error response SHALL contain
     * a specific, actionable error message (not a generic "failed to check in"
     * message).
     *
     * **Validates: Requirements 3.4, 3.5**
     */
    it('returns specific error for same-department same-day check-in', function () {
        // Create first check-in
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today(),
            'status' => 'checked_in',
        ]);

        // Attempt duplicate check-in
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
        ]);

        $response->assertSessionHasErrors('department_id');
        $errors = session('errors')->get('department_id');

        // Error message should be specific and contain department name
        expect($errors[0])
            ->toContain($this->departmentA->name)
            ->toContain('already checked in')
            ->not->toContain('Failed to check in');
    });

    it('returns specific error for invalid department', function () {
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => 99999, // Non-existent department
        ]);

        $response->assertSessionHasErrors('department_id');
        $errors = session('errors')->get('department_id');

        // Should have a specific error, not generic
        expect($errors[0])->not->toBeEmpty();
    });

    it('returns specific error for missing patient', function () {
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => 99999, // Non-existent patient
            'department_id' => $this->departmentA->id,
        ]);

        $response->assertSessionHasErrors('patient_id');
        $errors = session('errors')->get('patient_id');

        // Should have a specific error
        expect($errors[0])->not->toBeEmpty();
    });

    it('returns specific error for duplicate CCC in active claim', function () {
        // Create an existing check-in with CCC and active claim
        $existingCheckin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today()->subDay(),
            'status' => 'completed',
            'claim_check_code' => 'CC-TEST-001',
        ]);

        // Create an active insurance claim with the same CCC
        \App\Models\InsuranceClaim::factory()->create([
            'patient_id' => $this->patient->id,
            'patient_checkin_id' => $existingCheckin->id,
            'claim_check_code' => 'CC-TEST-001',
            'status' => 'pending_vetting', // Active status
        ]);

        // Attempt check-in with same CCC
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentB->id,
            'has_insurance' => true,
            'claim_check_code' => 'CC-TEST-001',
        ]);

        $response->assertSessionHasErrors('claim_check_code');
        $errors = session('errors')->get('claim_check_code');

        // Error should be specific about CCC being in use
        expect($errors[0])
            ->toContain('CCC')
            ->not->toContain('Failed to check in');
    });

    it('returns specific error for incomplete check-in at different department', function () {
        // Create incomplete check-in at department A
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today(),
            'status' => 'awaiting_consultation', // Incomplete status
        ]);

        // Attempt check-in to department B
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentB->id,
        ]);

        $response->assertSessionHasErrors('patient_id');
        $errors = session('errors')->get('patient_id');

        // Error should mention incomplete check-in and department
        expect($errors[0])
            ->toContain('incomplete check-in')
            ->toContain($this->departmentA->name)
            ->not->toContain('Failed to check in');
    });

    it('all validation errors contain actionable information', function () {
        // Test various validation scenarios and ensure none return generic errors
        $scenarios = [
            // Missing required fields
            ['patient_id' => null, 'department_id' => $this->departmentA->id],
            ['patient_id' => $this->patient->id, 'department_id' => null],
        ];

        foreach ($scenarios as $data) {
            $response = $this->actingAs($this->user)->post('/checkin/checkins', $data);

            // Should have validation errors
            $response->assertSessionHasErrors();

            // Get all error messages
            $allErrors = session('errors')->all();

            // None should be generic "failed" messages
            foreach ($allErrors as $error) {
                expect($error)
                    ->not->toBe('Failed to check in patient')
                    ->not->toBe('Check-in failed');
            }
        }
    });
});


describe('Property 7: CCC Sharing for Same-Day Check-ins', function () {
    /**
     * Property 7: CCC Sharing for Same-Day Check-ins
     *
     * For any patient with multiple check-ins on the same day where the first
     * check-in has a CCC, subsequent check-ins SHALL either inherit the same CCC
     * or display a warning if a different CCC is entered.
     *
     * **Validates: Requirements 6.1, 6.3, 6.4**
     */
    it('returns existing same-day CCC when checking patient insurance', function () {
        // Create first check-in with CCC
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today(),
            'status' => 'completed',
            'claim_check_code' => 'CC-SAMEDAY-001',
        ]);

        // Query same-day CCC endpoint
        $response = $this->actingAs($this->user)
            ->get("/checkin/checkins/patients/{$this->patient->id}/same-day-ccc");

        $response->assertOk();
        $response->assertJson([
            'has_same_day_ccc' => true,
            'claim_check_code' => 'CC-SAMEDAY-001',
            'department' => $this->departmentA->name,
        ]);
    });

    it('returns no same-day CCC when patient has no check-ins today', function () {
        // Create check-in from yesterday
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today()->subDay(),
            'status' => 'completed',
            'claim_check_code' => 'CC-YESTERDAY-001',
        ]);

        // Query same-day CCC endpoint
        $response = $this->actingAs($this->user)
            ->get("/checkin/checkins/patients/{$this->patient->id}/same-day-ccc");

        $response->assertOk();
        $response->assertJson([
            'has_same_day_ccc' => false,
        ]);
    });

    it('returns no same-day CCC when check-in has no CCC', function () {
        // Create check-in without CCC (cash payment)
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today(),
            'status' => 'completed',
            'claim_check_code' => null,
        ]);

        // Query same-day CCC endpoint
        $response = $this->actingAs($this->user)
            ->get("/checkin/checkins/patients/{$this->patient->id}/same-day-ccc");

        $response->assertOk();
        $response->assertJson([
            'has_same_day_ccc' => false,
        ]);
    });

    it('ignores cancelled check-ins when looking for same-day CCC', function () {
        // Create cancelled check-in with CCC
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today(),
            'status' => 'cancelled',
            'claim_check_code' => 'CC-CANCELLED-001',
        ]);

        // Query same-day CCC endpoint
        $response = $this->actingAs($this->user)
            ->get("/checkin/checkins/patients/{$this->patient->id}/same-day-ccc");

        $response->assertOk();
        $response->assertJson([
            'has_same_day_ccc' => false,
        ]);
    });

    it('returns same-day CCC for specific service date', function () {
        $specificDate = today()->subDays(3)->toDateString();

        // Create check-in with CCC on specific date
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => $specificDate,
            'status' => 'completed',
            'claim_check_code' => 'CC-SPECIFIC-001',
        ]);

        // Query same-day CCC endpoint with specific date
        $response = $this->actingAs($this->user)
            ->get("/checkin/checkins/patients/{$this->patient->id}/same-day-ccc?service_date={$specificDate}");

        $response->assertOk();
        $response->assertJson([
            'has_same_day_ccc' => true,
            'claim_check_code' => 'CC-SPECIFIC-001',
        ]);
    });

    it('allows same CCC for multiple same-day check-ins', function () {
        // Create first check-in with CCC (completed)
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today(),
            'status' => 'completed',
            'claim_check_code' => 'CC-SHARED-001',
        ]);

        // Create insurance for patient
        $insuranceProvider = \App\Models\InsuranceProvider::factory()->create(['is_nhis' => true]);
        $insurancePlan = \App\Models\InsurancePlan::factory()->create([
            'insurance_provider_id' => $insuranceProvider->id,
        ]);
        \App\Models\PatientInsurance::factory()->create([
            'patient_id' => $this->patient->id,
            'insurance_plan_id' => $insurancePlan->id,
            'status' => 'active',
            'coverage_start_date' => now()->subMonth(),
            'coverage_end_date' => now()->addYear(),
        ]);

        // Attempt second check-in with same CCC
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentB->id,
            'has_insurance' => true,
            'claim_check_code' => 'CC-SHARED-001',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify both check-ins have the same CCC
        $checkins = PatientCheckin::where('patient_id', $this->patient->id)
            ->whereDate('service_date', today())
            ->whereNotNull('claim_check_code')
            ->get();

        expect($checkins)->toHaveCount(2);
        expect($checkins->pluck('claim_check_code')->unique()->count())->toBe(1);
        expect($checkins->first()->claim_check_code)->toBe('CC-SHARED-001');
    });

    it('returns first CCC found when multiple same-day check-ins exist', function () {
        // Create first check-in with CCC
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentA->id,
            'service_date' => today(),
            'status' => 'completed',
            'claim_check_code' => 'CC-FIRST-001',
            'checked_in_at' => now()->subHours(2),
        ]);

        // Create second check-in with same CCC
        PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->departmentB->id,
            'service_date' => today(),
            'status' => 'completed',
            'claim_check_code' => 'CC-FIRST-001',
            'checked_in_at' => now()->subHour(),
        ]);

        // Query same-day CCC endpoint
        $response = $this->actingAs($this->user)
            ->get("/checkin/checkins/patients/{$this->patient->id}/same-day-ccc");

        $response->assertOk();
        $response->assertJson([
            'has_same_day_ccc' => true,
            'claim_check_code' => 'CC-FIRST-001',
        ]);
    });
});
