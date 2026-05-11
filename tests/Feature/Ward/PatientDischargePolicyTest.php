<?php

use App\Models\BillingConfiguration;
use App\Models\Charge;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\Ward;
use App\Services\DischargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'admissions.discharge', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => DischargeService::OVERRIDE_PERMISSION, 'guard_name' => 'web']);

    $this->ward = Ward::factory()->create();
    $this->patient = Patient::factory()->create();
    $this->admission = PatientAdmission::factory()->create([
        'patient_id' => $this->patient->id,
        'ward_id' => $this->ward->id,
        'status' => 'admitted',
    ]);

    $this->doctor = User::factory()->create();
    $this->doctor->givePermissionTo('admissions.discharge');
});

/**
 * Create an unpaid cash charge tied to the admission's checkin.
 */
function attachUnpaidCharge(PatientAdmission $admission, float $amount = 250.00): Charge
{
    return Charge::factory()->create([
        'patient_checkin_id' => $admission->consultation->patient_checkin_id,
        'amount' => $amount,
        'paid_amount' => 0,
        'paid_at' => null,
        'status' => 'pending',
        'is_insurance_claim' => false,
    ]);
}

it('allows discharge freely when policy is allow even with outstanding balance', function () {
    BillingConfiguration::setValue('admission.discharge_policy', 'allow', 'admissions');
    attachUnpaidCharge($this->admission, 500);

    $response = $this->actingAs($this->doctor)
        ->post("/wards/{$this->ward->id}/patients/{$this->admission->id}/discharge", [
            'discharge_notes' => 'Went home against advice.',
        ]);

    $response->assertRedirect("/wards/{$this->ward->id}");

    $this->admission->refresh();
    expect($this->admission->status)->toBe('discharged')
        // Balance is recorded for auditing regardless of policy
        ->and((float) $this->admission->discharge_outstanding_balance)->toBe(500.00)
        // No acknowledgement workflow ran under allow policy
        ->and($this->admission->discharge_ack_reason)->toBeNull()
        ->and($this->admission->discharge_ack_note)->toBeNull();
});

it('records balance and reason when discharging under warn policy', function () {
    BillingConfiguration::setValue('admission.discharge_policy', 'warn', 'admissions');
    attachUnpaidCharge($this->admission, 750);

    $response = $this->actingAs($this->doctor)
        ->post("/wards/{$this->ward->id}/patients/{$this->admission->id}/discharge", [
            'discharge_notes' => 'Stable, follow-up in 1 week.',
            'discharge_ack_reason' => 'follow_up_arrangement',
            'discharge_ack_note' => 'Patient agreed to pay next visit.',
        ]);

    $response->assertRedirect("/wards/{$this->ward->id}");

    $this->admission->refresh();
    expect($this->admission->status)->toBe('discharged')
        ->and((float) $this->admission->discharge_outstanding_balance)->toBe(750.00)
        ->and($this->admission->discharge_ack_reason)->toBe('follow_up_arrangement')
        ->and($this->admission->discharge_ack_note)->toBe('Patient agreed to pay next visit.');
});

it('rejects discharge under warn policy when reason is missing', function () {
    BillingConfiguration::setValue('admission.discharge_policy', 'warn', 'admissions');
    attachUnpaidCharge($this->admission, 300);

    $response = $this->actingAs($this->doctor)
        ->post("/wards/{$this->ward->id}/patients/{$this->admission->id}/discharge", [
            'discharge_notes' => 'Trying to discharge without reason.',
        ]);

    $response->assertSessionHasErrors('discharge');

    $this->admission->refresh();
    expect($this->admission->status)->toBe('admitted');
});

it('allows discharge under warn policy without balance and without reason', function () {
    BillingConfiguration::setValue('admission.discharge_policy', 'warn', 'admissions');
    // No outstanding charges

    $response = $this->actingAs($this->doctor)
        ->post("/wards/{$this->ward->id}/patients/{$this->admission->id}/discharge");

    $response->assertRedirect("/wards/{$this->ward->id}");

    $this->admission->refresh();
    expect($this->admission->status)->toBe('discharged')
        ->and($this->admission->discharge_outstanding_balance)->toBeNull();
});

it('blocks discharge under block policy without override permission', function () {
    BillingConfiguration::setValue('admission.discharge_policy', 'block', 'admissions');
    attachUnpaidCharge($this->admission, 1200);

    $response = $this->actingAs($this->doctor)
        ->post("/wards/{$this->ward->id}/patients/{$this->admission->id}/discharge", [
            'discharge_ack_reason' => 'hardship_waiver',
        ]);

    $response->assertSessionHasErrors('discharge');

    $this->admission->refresh();
    expect($this->admission->status)->toBe('admitted');
});

it('allows discharge under block policy when user has override permission', function () {
    BillingConfiguration::setValue('admission.discharge_policy', 'block', 'admissions');
    attachUnpaidCharge($this->admission, 1200);

    $this->doctor->givePermissionTo(DischargeService::OVERRIDE_PERMISSION);

    $response = $this->actingAs($this->doctor)
        ->post("/wards/{$this->ward->id}/patients/{$this->admission->id}/discharge", [
            'discharge_ack_reason' => 'hardship_waiver',
            'discharge_ack_note' => 'Approved by finance officer.',
        ]);

    $response->assertRedirect("/wards/{$this->ward->id}");

    $this->admission->refresh();
    expect($this->admission->status)->toBe('discharged')
        ->and((float) $this->admission->discharge_outstanding_balance)->toBe(1200.00)
        ->and($this->admission->discharge_ack_reason)->toBe('hardship_waiver');
});

it('defaults to warn policy when configuration value is missing or invalid', function () {
    BillingConfiguration::where('key', 'admission.discharge_policy')->delete();

    $service = app(DischargeService::class);
    expect($service->getPolicy())->toBe(DischargeService::POLICY_WARN);

    BillingConfiguration::setValue('admission.discharge_policy', 'nonsense', 'admissions');
    expect($service->getPolicy())->toBe(DischargeService::POLICY_WARN);
});

it('evaluate returns correct state for patient with no balance', function () {
    BillingConfiguration::setValue('admission.discharge_policy', 'block', 'admissions');

    $service = app(DischargeService::class);
    $state = $service->evaluate($this->admission, $this->doctor);

    expect($state['has_balance'])->toBeFalse()
        ->and($state['blocked'])->toBeFalse()
        ->and($state['requires_acknowledgement'])->toBeFalse()
        ->and($state['outstanding_balance'])->toBe(0.0);
});

it('evaluate marks block policy as blocked for user without override', function () {
    BillingConfiguration::setValue('admission.discharge_policy', 'block', 'admissions');
    attachUnpaidCharge($this->admission, 400);

    $service = app(DischargeService::class);
    $state = $service->evaluate($this->admission->refresh(), $this->doctor);

    expect($state['has_balance'])->toBeTrue()
        ->and($state['blocked'])->toBeTrue()
        ->and($state['requires_acknowledgement'])->toBeFalse()
        ->and($state['can_override'])->toBeFalse()
        ->and($state['outstanding_balance'])->toBe(400.0);
});
