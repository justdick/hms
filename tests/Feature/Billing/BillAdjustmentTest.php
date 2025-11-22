<?php

use App\Models\BillAdjustment;
use App\Models\Charge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'billing.adjust-charges']);
    Permission::create(['name' => 'billing.create']);
});

describe('Bill Adjustment', function () {
    it('allows authorized user to apply percentage discount', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.adjust-charges');

        $charge = Charge::factory()->create([
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.adjust', $charge), [
                'adjustment_type' => 'discount_percentage',
                'adjustment_value' => 20,
                'reason' => 'Staff discount - hospital employee',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Charge adjusted successfully');

        $charge->refresh();
        expect((float) $charge->amount)->toBe(80.00)
            ->and((float) $charge->original_amount)->toBe(100.00)
            ->and((float) $charge->adjustment_amount)->toBe(20.00);
    });

    it('allows authorized user to apply fixed discount', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.adjust-charges');

        $charge = Charge::factory()->create([
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.adjust', $charge), [
                'adjustment_type' => 'discount_fixed',
                'adjustment_value' => 25,
                'reason' => 'Goodwill discount for loyal patient',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Charge adjusted successfully');

        $charge->refresh();
        expect((float) $charge->amount)->toBe(75.00)
            ->and((float) $charge->original_amount)->toBe(100.00)
            ->and((float) $charge->adjustment_amount)->toBe(25.00);
    });

    it('creates bill adjustment record', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.adjust-charges');

        $charge = Charge::factory()->create([
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('billing.charges.adjust', $charge), [
                'adjustment_type' => 'discount_percentage',
                'adjustment_value' => 20,
                'reason' => 'Staff discount - hospital employee',
            ]);

        expect(BillAdjustment::count())->toBe(1);

        $adjustment = BillAdjustment::first();
        expect($adjustment->charge_id)->toBe($charge->id)
            ->and($adjustment->adjustment_type)->toBe('discount_percentage')
            ->and((float) $adjustment->original_amount)->toBe(100.00)
            ->and((float) $adjustment->adjustment_amount)->toBe(20.00)
            ->and((float) $adjustment->final_amount)->toBe(80.00)
            ->and($adjustment->adjusted_by)->toBe($user->id)
            ->and($adjustment->reason)->toBe('Staff discount - hospital employee');
    });

    it('prevents unauthorized user from adjusting charge', function () {
        $user = User::factory()->create();
        // User does not have billing.adjust-charges permission

        $charge = Charge::factory()->create([
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.adjust', $charge), [
                'adjustment_type' => 'discount_percentage',
                'adjustment_value' => 20,
                'reason' => 'Staff discount - hospital employee',
            ]);

        $response->assertForbidden();

        $charge->refresh();
        expect((float) $charge->amount)->toBe(100.00);
    });

    it('requires reason with minimum 10 characters', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.adjust-charges');

        $charge = Charge::factory()->create([
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.adjust', $charge), [
                'adjustment_type' => 'discount_percentage',
                'adjustment_value' => 20,
                'reason' => 'Short',
            ]);

        $response->assertSessionHasErrors('reason');

        $charge->refresh();
        expect((float) $charge->amount)->toBe(100.00);
    });

    it('prevents adjusting already paid charge', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.adjust-charges');

        $charge = Charge::factory()->create([
            'amount' => 100.00,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.adjust', $charge), [
                'adjustment_type' => 'discount_percentage',
                'adjustment_value' => 20,
                'reason' => 'Staff discount - hospital employee',
            ]);

        $response->assertForbidden();

        $charge->refresh();
        expect((float) $charge->amount)->toBe(100.00);
    });

    it('prevents fixed discount exceeding charge amount', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.adjust-charges');

        $charge = Charge::factory()->create([
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.adjust', $charge), [
                'adjustment_type' => 'discount_fixed',
                'adjustment_value' => 150,
                'reason' => 'Large discount attempt',
            ]);

        $response->assertSessionHasErrors('adjustment_value');

        $charge->refresh();
        expect((float) $charge->amount)->toBe(100.00);
    });

    it('prevents percentage discount over 100', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.adjust-charges');

        $charge = Charge::factory()->create([
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->post(route('billing.charges.adjust', $charge), [
                'adjustment_type' => 'discount_percentage',
                'adjustment_value' => 150,
                'reason' => 'Invalid percentage discount',
            ]);

        $response->assertSessionHasErrors('adjustment_value');

        $charge->refresh();
        expect((float) $charge->amount)->toBe(100.00);
    });

    it('handles decimal amounts correctly', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('billing.adjust-charges');

        $charge = Charge::factory()->create([
            'amount' => 123.45,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('billing.charges.adjust', $charge), [
                'adjustment_type' => 'discount_percentage',
                'adjustment_value' => 15,
                'reason' => 'Decimal amount discount test',
            ]);

        $charge->refresh();
        expect((float) $charge->amount)->toBe(104.93)
            ->and((float) $charge->original_amount)->toBe(123.45)
            ->and((float) $charge->adjustment_amount)->toBe(18.52);
    });
});
