<?php

use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $this->user->assignRole($adminRole);

    $this->patient = Patient::factory()->create();
    $this->department = Department::factory()->create(['type' => 'opd']);
    $this->user->departments()->attach($this->department);
});

describe('Check-in Service Date', function () {
    it('defaults service_date to today when not provided', function () {
        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
        ]);

        $response->assertRedirect();

        $checkin = PatientCheckin::latest()->first();
        expect($checkin->service_date->toDateString())->toBe(now()->toDateString());
    });

    it('allows backdating service_date', function () {
        $backdatedDate = now()->subDays(2)->toDateString();

        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'service_date' => $backdatedDate,
        ]);

        $response->assertRedirect();

        $checkin = PatientCheckin::latest()->first();
        expect($checkin->service_date->toDateString())->toBe($backdatedDate);
    });

    it('rejects future service_date', function () {
        $futureDate = now()->addDays(1)->toDateString();

        $response = $this->actingAs($this->user)->post('/checkin/checkins', [
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'service_date' => $futureDate,
        ]);

        $response->assertSessionHasErrors('service_date');
    });

    it('identifies backdated check-ins correctly', function () {
        // Create a backdated check-in
        $checkin = PatientCheckin::factory()->create([
            'service_date' => now()->subDays(3),
            'created_at' => now(),
        ]);

        expect($checkin->isBackdated())->toBeTrue();

        // Create a same-day check-in
        $todayCheckin = PatientCheckin::factory()->create([
            'service_date' => now()->toDateString(),
            'created_at' => now(),
        ]);

        expect($todayCheckin->isBackdated())->toBeFalse();
    });
});

describe('Consultation Service Date', function () {
    it('inherits service_date from check-in when not provided', function () {
        $backdatedDate = now()->subDays(2)->toDateString();

        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'service_date' => $backdatedDate,
            'status' => 'vitals_taken',
        ]);

        $response = $this->actingAs($this->user)->post('/consultation', [
            'patient_checkin_id' => $checkin->id,
        ]);

        $response->assertRedirect();

        $consultation = $checkin->fresh()->consultation;
        expect($consultation->service_date->toDateString())->toBe($backdatedDate);
    });

    it('rejects consultation service_date before check-in service_date', function () {
        $checkinDate = now()->subDays(2)->toDateString();
        $beforeCheckinDate = now()->subDays(5)->toDateString();

        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'service_date' => $checkinDate,
            'status' => 'vitals_taken',
        ]);

        $response = $this->actingAs($this->user)->post('/consultation', [
            'patient_checkin_id' => $checkin->id,
            'service_date' => $beforeCheckinDate,
        ]);

        $response->assertSessionHasErrors('service_date');
    });
});
