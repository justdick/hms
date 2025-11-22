<?php

use App\Models\BillAdjustment;
use App\Models\Charge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'billing.waive-charges']);
    Permission::create(['name' => 'billing.create']);
});

describe('Bill Waiver', function () {
    it('allows authorized user to waive charge', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.waive-charges');

        $charge = Charge::factory()->create([
            'amount' => 150.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.waive', $charge), [
                'reason' => 'Indigent patient - unable to pay medical bills',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Charge waived successfully');

        $charge->refresh();
        expect($charge->is_waived)->toBeTrue()
            ->and($charge->status)->toBe('waived')
            ->and($charge->waived_by)->toBe($user->id)
            ->and($charge->waived_reason)->toBe('Indigent patient - unable to pay medical bills')
            ->and($charge->waived_at)->not->toBeNull();
    });

    it('creates bill adjustment record when waiving charge', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.waive-charges');

        $charge = Charge::factory()->create([
            'amount' => 150.00,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('billing.charges.waive', $charge), [
                'reason' => 'Indigent patient - unable to pay medical bills',
            ]);

        expect(BillAdjustment::count())->toBe(1);

        $adjustment = BillAdjustment::first();
        expect($adjustment->charge_id)->toBe($charge->id)
            ->and($adjustment->adjustment_type)->toBe('waiver')
            ->and((float) $adjustment->original_amount)->toBe(150.00)
            ->and((float) $adjustment->adjustment_amount)->toBe(150.00)
            ->and((float) $adjustment->final_amount)->toBe(0.00)
            ->and($adjustment->adjusted_by)->toBe($user->id)
            ->and($adjustment->reason)->toBe('Indigent patient - unable to pay medical bills');
    });

    it('prevents unauthorized user from waiving charge', function () {
        $user = User::factory()->create();
        // User does not have billing.waive-charges permission

        $charge = Charge::factory()->create([
            'amount' => 150.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.waive', $charge), [
                'reason' => 'Indigent patient - unable to pay medical bills',
            ]);

        $response->assertForbidden();

        $charge->refresh();
        expect($charge->is_waived)->toBeFalse();
    });

    it('requires reason with minimum 10 characters', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.waive-charges');

        $charge = Charge::factory()->create([
            'amount' => 150.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.waive', $charge), [
                'reason' => 'Short',
            ]);

        $response->assertSessionHasErrors('reason');

        $charge->refresh();
        expect($charge->is_waived)->toBeFalse();
    });

    it('prevents waiving already paid charge', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.waive-charges');

        $charge = Charge::factory()->create([
            'amount' => 150.00,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.waive', $charge), [
                'reason' => 'Indigent patient - unable to pay medical bills',
            ]);

        $response->assertForbidden();

        $charge->refresh();
        expect($charge->is_waived)->toBeFalse();
    });

    it('prevents waiving already waived charge', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.waive-charges');

        $charge = Charge::factory()->create([
            'amount' => 150.00,
            'status' => 'waived',
            'is_waived' => true,
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.waive', $charge), [
                'reason' => 'Indigent patient - unable to pay medical bills',
            ]);

        $response->assertForbidden();
    });

    it('updates charge original amount when waiving', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.waive-charges');

        $charge = Charge::factory()->create([
            'amount' => 150.00,
            'status' => 'pending',
            'original_amount' => null,
        ]);

        $this->actingAs($user)
            ->post(route('billing.charges.waive', $charge), [
                'reason' => 'Indigent patient - unable to pay medical bills',
            ]);

        $charge->refresh();
        expect((float) $charge->original_amount)->toBe(150.00)
            ->and((float) $charge->adjustment_amount)->toBe(150.00);
    });
});
