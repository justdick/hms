<?php

use App\Models\Diagnosis;
use App\Models\User;

describe('Custom Diagnosis', function () {
    it('can create a custom diagnosis', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/consultation/diagnoses/custom', [
                'diagnosis' => 'Rare Tropical Disease XYZ',
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
        expect($response->json('icd_code'))->toBeNull();

        $this->assertDatabaseHas('diagnoses', [
            'diagnosis' => 'Rare Tropical Disease XYZ',
            'is_custom' => true,
            'created_by' => $user->id,
        ]);
    });

    it('prevents duplicate custom diagnoses', function () {
        $user = User::factory()->create();

        Diagnosis::create([
            'diagnosis' => 'Existing Custom Diagnosis',
            'code' => 'CUSTOM-12345678',
            'is_custom' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/consultation/diagnoses/custom', [
                'diagnosis' => 'Existing Custom Diagnosis',
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
    });
});
