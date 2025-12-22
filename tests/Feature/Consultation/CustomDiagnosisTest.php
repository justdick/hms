<?php

use App\Models\Diagnosis;
use App\Models\User;

describe('Custom Diagnosis', function () {
    it('can create a custom diagnosis with ICD-10 code', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/consultation/diagnoses/custom', [
                'diagnosis' => 'Rare Tropical Disease XYZ',
                'icd_10' => 'B99.9',
            ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'id',
            'name',
            'icd_code',
            'is_custom',
        ]);

        expect($response->json('is_custom'))->toBeTrue();
        expect($response->json('name'))->toBe('Rare Tropical Disease XYZ');
        expect($response->json('icd_code'))->toBe('B99.9');

        $this->assertDatabaseHas('diagnoses', [
            'diagnosis' => 'Rare Tropical Disease XYZ',
            'icd_10' => 'B99.9',
            'is_custom' => true,
            'created_by' => $user->id,
        ]);
    });

    it('requires ICD-10 code for custom diagnosis', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/consultation/diagnoses/custom', [
                'diagnosis' => 'Some Custom Diagnosis',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['icd_10']);
    });

    it('uppercases ICD-10 code', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/consultation/diagnoses/custom', [
                'diagnosis' => 'Another Custom Diagnosis',
                'icd_10' => 'a00.1',
            ]);

        $response->assertSuccessful();
        expect($response->json('icd_code'))->toBe('A00.1');
    });

    it('prevents duplicate custom diagnoses', function () {
        $user = User::factory()->create();

        Diagnosis::create([
            'diagnosis' => 'Existing Custom Diagnosis',
            'code' => 'CUSTOM-12345678',
            'icd_10' => 'Z99.9',
            'is_custom' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/consultation/diagnoses/custom', [
                'diagnosis' => 'Existing Custom Diagnosis',
                'icd_10' => 'Z99.9',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['diagnosis']);
    });

    it('returns is_custom flag in search results', function () {
        $user = User::factory()->create();

        // Create a custom diagnosis
        Diagnosis::create([
            'diagnosis' => 'Unique Custom Test Diagnosis',
            'code' => 'CUSTOM-TESTCODE',
            'icd_10' => 'R99',
            'is_custom' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/consultation/diagnoses/search?q=Unique Custom Test');

        $response->assertSuccessful();
        $results = $response->json();

        expect($results)->toHaveCount(1);
        expect($results[0]['is_custom'])->toBeTrue();
        expect($results[0]['name'])->toBe('Unique Custom Test Diagnosis');
        expect($results[0]['icd_code'])->toBe('R99');
    });
});
