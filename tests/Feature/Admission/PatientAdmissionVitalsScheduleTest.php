<?php

use App\Models\Consultation;
use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsSchedule;
use App\Models\Ward;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('automatically creates a vitals schedule with 4-hour interval when patient is admitted', function () {
    $ward = Ward::factory()->create(['available_beds' => 5]);

    // Create consultation owned by the test user
    $consultation = Consultation::factory()->create([
        'status' => 'in_progress',
        'doctor_id' => $this->user->id,
    ]);

    // Ensure no schedule exists before admission
    expect(VitalsSchedule::count())->toBe(0);

    $response = $this->post(route('consultation.admit', $consultation), [
        'ward_id' => $ward->id,
        'admission_reason' => 'Test admission',
        'admission_notes' => 'Test notes',
    ]);

    $response->assertRedirect(route('consultation.index'));

    // Verify admission was created
    $admission = PatientAdmission::latest()->first();
    expect($admission)->not->toBeNull();

    // Verify vitals schedule was automatically created
    $schedule = VitalsSchedule::where('patient_admission_id', $admission->id)->first();
    expect($schedule)->not->toBeNull();
    expect($schedule->interval_minutes)->toBe(240); // 4 hours
    expect($schedule->is_active)->toBeTrue();
    expect($schedule->created_by)->toBe($this->user->id);
    expect($schedule->next_due_at)->not->toBeNull();
});

it('sets the next due time to 4 hours from admission time', function () {
    $ward = Ward::factory()->create(['available_beds' => 5]);
    $consultation = Consultation::factory()->create([
        'status' => 'in_progress',
        'doctor_id' => $this->user->id,
    ]);

    $beforeAdmission = now();

    $this->post(route('consultation.admit', $consultation), [
        'ward_id' => $ward->id,
        'admission_reason' => 'Test admission',
    ]);

    $admission = PatientAdmission::latest()->first();
    $schedule = VitalsSchedule::where('patient_admission_id', $admission->id)->first();

    // Next due should be approximately 4 hours from now
    $expectedDueTime = $beforeAdmission->copy()->addMinutes(240);
    $actualDueTime = $schedule->next_due_at;

    // Allow 1 minute tolerance for test execution time
    expect($actualDueTime->diffInMinutes($expectedDueTime, false))->toBeLessThanOrEqual(1);
});

it('allows ward staff to edit the default vitals schedule after admission', function () {
    $ward = Ward::factory()->create(['available_beds' => 5]);
    $consultation = Consultation::factory()->create(['status' => 'in_progress']);

    // Create admission with default schedule
    $this->post(route('consultation.admit', $consultation), [
        'ward_id' => $ward->id,
        'admission_reason' => 'Test admission',
    ]);

    $admission = PatientAdmission::latest()->first();
    $schedule = VitalsSchedule::where('patient_admission_id', $admission->id)->first();

    // Verify default is 4 hours
    expect($schedule->interval_minutes)->toBe(240);

    // Edit schedule to 2 hours
    $response = $this->put(
        route('wards.patients.vitals-schedule.update', [
            'ward' => $ward->id,
            'admission' => $admission->id,
            'schedule' => $schedule->id,
        ]),
        ['interval_minutes' => 120]
    );

    $response->assertRedirect();

    // Verify schedule was updated
    $schedule->refresh();
    expect($schedule->interval_minutes)->toBe(120); // 2 hours
});

it('includes schedule creation message in success notification', function () {
    $ward = Ward::factory()->create(['available_beds' => 5]);
    $consultation = Consultation::factory()->create(['status' => 'in_progress']);

    $response = $this->post(route('consultation.admit', $consultation), [
        'ward_id' => $ward->id,
        'admission_reason' => 'Test admission',
    ]);

    $response->assertSessionHas('success');
    $successMessage = session('success');

    expect($successMessage)->toContain('Default vitals schedule');
    expect($successMessage)->toContain('4 hours');
});
