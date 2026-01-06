<?php

use App\Models\Bed;
use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardTransfer;

beforeEach(function () {
    // Create a user with transfer permission
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('admissions.transfer');
    $this->user->givePermissionTo('admissions.view');
});

it('can transfer a patient to another ward', function () {
    // Create source ward with bed
    $sourceWard = Ward::factory()->create([
        'name' => 'General Ward',
        'available_beds' => 5,
        'total_beds' => 10,
    ]);
    $sourceBed = Bed::factory()->create([
        'ward_id' => $sourceWard->id,
        'status' => 'occupied',
    ]);

    // Create destination ward
    $destWard = Ward::factory()->create([
        'name' => 'ICU',
        'available_beds' => 3,
        'total_beds' => 5,
    ]);

    // Create admission with bed assigned
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $sourceWard->id,
        'bed_id' => $sourceBed->id,
        'status' => 'admitted',
    ]);

    $response = $this->actingAs($this->user)
        ->post("/admissions/{$admission->id}/transfer", [
            'to_ward_id' => $destWard->id,
            'transfer_reason' => 'Patient condition requires ICU care',
            'transfer_notes' => 'Monitor closely',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify admission was updated
    $admission->refresh();
    expect($admission->ward_id)->toBe($destWard->id);
    expect($admission->bed_id)->toBeNull();
    expect($admission->bed_assigned_by_id)->toBeNull();

    // Verify source bed was released
    $sourceBed->refresh();
    expect($sourceBed->status)->toBe('available');

    // Verify destination ward bed count decreased
    $destWard->refresh();
    expect($destWard->available_beds)->toBe(2);

    // Verify transfer record was created
    expect(WardTransfer::count())->toBe(1);
    $transfer = WardTransfer::first();
    expect($transfer->from_ward_id)->toBe($sourceWard->id);
    expect($transfer->from_bed_id)->toBe($sourceBed->id);
    expect($transfer->to_ward_id)->toBe($destWard->id);
    expect($transfer->transfer_reason)->toBe('Patient condition requires ICU care');
});

it('cannot transfer to same ward', function () {
    $ward = Ward::factory()->create(['available_beds' => 5]);
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $response = $this->actingAs($this->user)
        ->post("/admissions/{$admission->id}/transfer", [
            'to_ward_id' => $ward->id,
            'transfer_reason' => 'Test reason',
        ]);

    $response->assertSessionHasErrors('to_ward_id');
});

it('cannot transfer to ward with no available beds', function () {
    $sourceWard = Ward::factory()->create(['available_beds' => 5]);
    $destWard = Ward::factory()->create(['available_beds' => 0]);

    $admission = PatientAdmission::factory()->create([
        'ward_id' => $sourceWard->id,
        'status' => 'admitted',
    ]);

    $response = $this->actingAs($this->user)
        ->post("/admissions/{$admission->id}/transfer", [
            'to_ward_id' => $destWard->id,
            'transfer_reason' => 'Test reason',
        ]);

    $response->assertSessionHasErrors('to_ward_id');
});

it('cannot transfer discharged patient', function () {
    $sourceWard = Ward::factory()->create(['available_beds' => 5]);
    $destWard = Ward::factory()->create(['available_beds' => 3]);

    $admission = PatientAdmission::factory()->create([
        'ward_id' => $sourceWard->id,
        'status' => 'discharged',
    ]);

    $response = $this->actingAs($this->user)
        ->post("/admissions/{$admission->id}/transfer", [
            'to_ward_id' => $destWard->id,
            'transfer_reason' => 'Test reason',
        ]);

    $response->assertSessionHasErrors('error');
});

it('requires transfer permission', function () {
    $userWithoutPermission = User::factory()->create();

    $sourceWard = Ward::factory()->create(['available_beds' => 5]);
    $destWard = Ward::factory()->create(['available_beds' => 3]);

    $admission = PatientAdmission::factory()->create([
        'ward_id' => $sourceWard->id,
        'status' => 'admitted',
    ]);

    $response = $this->actingAs($userWithoutPermission)
        ->post("/admissions/{$admission->id}/transfer", [
            'to_ward_id' => $destWard->id,
            'transfer_reason' => 'Test reason',
        ]);

    $response->assertForbidden();
});

it('requires transfer reason', function () {
    $sourceWard = Ward::factory()->create(['available_beds' => 5]);
    $destWard = Ward::factory()->create(['available_beds' => 3]);

    $admission = PatientAdmission::factory()->create([
        'ward_id' => $sourceWard->id,
        'status' => 'admitted',
    ]);

    $response = $this->actingAs($this->user)
        ->post("/admissions/{$admission->id}/transfer", [
            'to_ward_id' => $destWard->id,
            'transfer_reason' => '',
        ]);

    $response->assertSessionHasErrors('transfer_reason');
});

it('can transfer patient without bed assignment', function () {
    $sourceWard = Ward::factory()->create(['available_beds' => 5]);
    $destWard = Ward::factory()->create(['available_beds' => 3]);

    // Admission without bed assigned
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $sourceWard->id,
        'bed_id' => null,
        'status' => 'admitted',
    ]);

    $response = $this->actingAs($this->user)
        ->post("/admissions/{$admission->id}/transfer", [
            'to_ward_id' => $destWard->id,
            'transfer_reason' => 'Moving to different ward',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $admission->refresh();
    expect($admission->ward_id)->toBe($destWard->id);

    // Verify transfer record has null from_bed_id
    $transfer = WardTransfer::first();
    expect($transfer->from_bed_id)->toBeNull();
});
