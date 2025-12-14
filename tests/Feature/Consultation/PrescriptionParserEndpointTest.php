<?php

use App\Models\Department;
use App\Models\Drug;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $adminRole = Role::create(['name' => 'Admin']);
    $this->user->assignRole($adminRole);

    $this->department = Department::factory()->create();
    $this->department->users()->attach($this->user->id);
});

describe('Prescription Parse Endpoint', function () {
    it('parses valid standard prescription input', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => '2 BD x 5 days',
            ]);

        $response->assertOk()
            ->assertJson([
                'isValid' => true,
                'doseQuantity' => '2',
                'frequencyCode' => 'BD',
                'durationDays' => 5,
                'quantityToDispense' => 20,
                'scheduleType' => 'standard',
            ]);
    });

    it('parses split dose pattern', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => '1-0-1 x 30 days',
            ]);

        $response->assertOk()
            ->assertJson([
                'isValid' => true,
                'doseQuantity' => '1-0-1',
                'frequencyCode' => 'SPLIT',
                'durationDays' => 30,
                'quantityToDispense' => 60,
                'scheduleType' => 'split_dose',
            ]);
    });

    it('parses STAT prescription', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => '2 STAT',
            ]);

        $response->assertOk()
            ->assertJson([
                'isValid' => true,
                'doseQuantity' => '2',
                'frequencyCode' => 'STAT',
                'scheduleType' => 'stat',
            ]);
    });

    it('parses PRN prescription', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => '2 PRN',
            ]);

        $response->assertOk()
            ->assertJson([
                'isValid' => true,
                'doseQuantity' => '2',
                'frequencyCode' => 'PRN',
                'scheduleType' => 'prn',
            ]);
    });

    it('parses taper pattern', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => '4-3-2-1 taper',
            ]);

        $response->assertOk()
            ->assertJson([
                'isValid' => true,
                'frequencyCode' => 'TAPER',
                'quantityToDispense' => 10,
                'scheduleType' => 'taper',
            ]);
    });

    it('returns error feedback for invalid input', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => 'invalid prescription text',
            ]);

        $response->assertOk()
            ->assertJson([
                'isValid' => false,
            ])
            ->assertJsonStructure([
                'isValid',
                'errors',
            ]);

        $data = $response->json();
        expect($data['errors'])->not->toBeEmpty();
    });

    it('returns validation error for empty input', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['input']);
    });

    it('returns validation error for missing input', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['input']);
    });

    it('parses prescription with drug_id for quantity calculation', function () {
        $drug = Drug::factory()->create([
            'name' => 'Paracetamol',
            'form' => 'tablet',
            'unit_price' => 0.50,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => '2 BD x 5 days',
                'drug_id' => $drug->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'isValid' => true,
                'doseQuantity' => '2',
                'frequencyCode' => 'BD',
                'durationDays' => 5,
                'quantityToDispense' => 20,
            ]);
    });

    it('calculates bottles for liquid drugs', function () {
        $drug = Drug::factory()->create([
            'name' => 'Amoxicillin Suspension',
            'form' => 'syrup',
            'bottle_size' => 100,
            'unit_price' => 15.00,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => '5ml TDS x 7 days',
                'drug_id' => $drug->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'isValid' => true,
                'doseQuantity' => '5 ml',
                'frequencyCode' => 'TDS',
                'durationDays' => 7,
            ]);

        // 5ml x 3 times x 7 days = 105ml, needs 2 bottles of 100ml
        $data = $response->json();
        expect($data['quantityToDispense'])->toBe(2);
    });

    it('returns validation error for invalid drug_id', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => '2 BD x 5 days',
                'drug_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['drug_id']);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/consultation/prescriptions/parse', [
            'input' => '2 BD x 5 days',
        ]);

        // Web routes redirect to login page for unauthenticated requests
        $response->assertRedirect();
    });
});
