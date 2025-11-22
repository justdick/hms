<?php

use App\Models\PatientCheckin;
use App\Models\ServiceAccessOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'billing.emergency-override']);
});

describe('Service Access Override', function () {
    it('allows authorized user to activate override', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.emergency-override');

        $checkin = PatientCheckin::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('billing.override.activate', $checkin), [
                'service_type' => 'laboratory',
                'reason' => 'Emergency - patient unconscious, urgent blood work needed immediately',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        expect(ServiceAccessOverride::count())->toBe(1);

        $override = ServiceAccessOverride::first();
        expect($override->patient_checkin_id)->toBe($checkin->id)
            ->and($override->service_type)->toBe('laboratory')
            ->and($override->authorized_by)->toBe($user->id)
            ->and($override->is_active)->toBeTrue()
            ->and($override->reason)->toBe('Emergency - patient unconscious, urgent blood work needed immediately');
    });

    it('sets expiry time to 2 hours by default', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.emergency-override');

        $checkin = PatientCheckin::factory()->create();

        $this->actingAs($user)
            ->post(route('billing.override.activate', $checkin), [
                'service_type' => 'laboratory',
                'reason' => 'Emergency - patient unconscious, urgent blood work needed immediately',
            ]);

        $override = ServiceAccessOverride::first();
        $expectedExpiry = now()->addHours(2);

        expect($override->expires_at->diffInMinutes($expectedExpiry))->toBeLessThan(1);
    });

    it('allows custom duration hours', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.emergency-override');

        $checkin = PatientCheckin::factory()->create();

        $this->actingAs($user)
            ->post(route('billing.override.activate', $checkin), [
                'service_type' => 'laboratory',
                'duration_hours' => 4,
                'reason' => 'Extended emergency override for complex case requiring multiple tests',
            ]);

        $override = ServiceAccessOverride::first();
        $expectedExpiry = now()->addHours(4);

        expect($override->expires_at->diffInMinutes($expectedExpiry))->toBeLessThan(1);
    });

    it('prevents unauthorized user from activating override', function () {
        $user = User::factory()->create();
        // User does not have billing.emergency-override permission

        $checkin = PatientCheckin::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('billing.override.activate', $checkin), [
                'service_type' => 'laboratory',
                'reason' => 'Emergency - patient unconscious, urgent blood work needed immediately',
            ]);

        $response->assertForbidden();

        expect(ServiceAccessOverride::count())->toBe(0);
    });

    it('requires reason with minimum 20 characters', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.emergency-override');

        $checkin = PatientCheckin::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('billing.override.activate', $checkin), [
                'service_type' => 'laboratory',
                'reason' => 'Short reason',
            ]);

        $response->assertSessionHasErrors('reason');

        expect(ServiceAccessOverride::count())->toBe(0);
    });

    it('prevents duplicate active override for same service', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.emergency-override');

        $checkin = PatientCheckin::factory()->create();

        // Create first override
        ServiceAccessOverride::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'service_type' => 'laboratory',
            'is_active' => true,
            'expires_at' => now()->addHours(2),
        ]);

        // Try to create second override
        $response = $this->actingAs($user)
            ->post(route('billing.override.activate', $checkin), [
                'service_type' => 'laboratory',
                'reason' => 'Emergency - patient unconscious, urgent blood work needed immediately',
            ]);

        $response->assertSessionHasErrors('error');

        expect(ServiceAccessOverride::count())->toBe(1);
    });

    it('allows override for different service types', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.emergency-override');

        $checkin = PatientCheckin::factory()->create();

        // Create laboratory override
        ServiceAccessOverride::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'service_type' => 'laboratory',
            'is_active' => true,
            'expires_at' => now()->addHours(2),
        ]);

        // Create pharmacy override (should succeed)
        $response = $this->actingAs($user)
            ->post(route('billing.override.activate', $checkin), [
                'service_type' => 'pharmacy',
                'reason' => 'Emergency medication needed for critical patient condition',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        expect(ServiceAccessOverride::count())->toBe(2);
    });

    it('allows deactivating override early', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.emergency-override');

        $checkin = PatientCheckin::factory()->create();

        $override = ServiceAccessOverride::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'service_type' => 'laboratory',
            'is_active' => true,
            'expires_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.override.deactivate', $override));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Service access override deactivated');

        $override->refresh();
        expect($override->is_active)->toBeFalse();
    });

    it('returns active overrides for patient checkin', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.emergency-override');

        $checkin = PatientCheckin::factory()->create();

        // Create active override
        ServiceAccessOverride::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'service_type' => 'laboratory',
            'is_active' => true,
            'expires_at' => now()->addHours(2),
        ]);

        // Create inactive override
        ServiceAccessOverride::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'service_type' => 'pharmacy',
            'is_active' => false,
            'expires_at' => now()->addHours(2),
        ]);

        // Create expired override
        ServiceAccessOverride::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'service_type' => 'consultation',
            'is_active' => true,
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('billing.override.index', $checkin));

        $response->assertSuccessful();
        $response->assertJson([
            'overrides' => [
                [
                    'service_type' => 'laboratory',
                ],
            ],
        ]);

        $data = $response->json();
        expect($data['overrides'])->toHaveCount(1);
    });

    it('validates service type is one of allowed types', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.emergency-override');

        $checkin = PatientCheckin::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('billing.override.activate', $checkin), [
                'service_type' => 'invalid_service',
                'reason' => 'Emergency - patient unconscious, urgent blood work needed immediately',
            ]);

        $response->assertSessionHasErrors('service_type');

        expect(ServiceAccessOverride::count())->toBe(0);
    });
});
