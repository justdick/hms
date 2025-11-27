<?php

/**
 * Property-Based Test for XML Export Round Trip
 *
 * **Feature: nhis-claims-integration, Property 25: XML Export Round Trip**
 * **Validates: Requirements 15.1, 15.2, 15.3**
 *
 * Property: For any claim batch exported to XML, parsing the XML should produce data
 * that matches the original claim records including patient NHIS ID, G-DRG code,
 * diagnoses, and item NHIS codes.
 */

use App\Models\ClaimBatch;
use App\Models\ClaimBatchItem;
use App\Models\Diagnosis;
use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimDiagnosis;
use App\Models\InsuranceClaimItem;
use App\Models\NhisTariff;
use App\Models\Patient;
use App\Models\User;
use App\Services\NhisXmlExportService;

beforeEach(function () {
    // Clean up
    ClaimBatchItem::query()->delete();
    ClaimBatch::query()->delete();
    InsuranceClaimDiagnosis::query()->delete();
    InsuranceClaimItem::query()->delete();
    InsuranceClaim::query()->delete();
});

dataset('claim_counts', [
    [1],
    [2],
    [3],
]);

dataset('item_counts', [
    [1],
    [2],
    [3],
]);

it('preserves batch details through XML export and parse round trip', function (int $claimCount) {
    // Arrange
    $service = new NhisXmlExportService;
    $user = User::factory()->create();

    $batch = ClaimBatch::factory()->create([
        'batch_number' => 'BATCH-202511-0001',
        'name' => 'November 2025 Claims',
        'submission_period' => '2025-11-01',
        'total_claims' => $claimCount,
        'total_amount' => $claimCount * 500.00,
        'created_by' => $user->id,
    ]);

    // Create claims and add to batch
    for ($i = 0; $i < $claimCount; $i++) {
        $claim = InsuranceClaim::factory()->create([
            'status' => 'vetted',
            'total_claim_amount' => 500.00,
        ]);

        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
            'claim_amount' => 500.00,
        ]);
    }

    // Act
    $xml = $service->generateXml($batch);
    $parsed = $service->parseXml($xml);

    // Assert - Batch details preserved
    expect($parsed['batch']['batch_number'])->toBe('BATCH-202511-0001');
    expect($parsed['batch']['batch_name'])->toBe('November 2025 Claims');
    expect($parsed['batch']['submission_period'])->toBe('2025-11');
    expect($parsed['batch']['total_claims'])->toBe($claimCount);
    expect($parsed['batch']['total_amount'])->toBe($claimCount * 500.00);
})->with('claim_counts');

it('preserves patient NHIS member ID through XML export and parse round trip', function () {
    // Arrange
    $service = new NhisXmlExportService;
    $user = User::factory()->create();

    $patient = Patient::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => 'male',
        'date_of_birth' => '1990-05-15',
    ]);

    $batch = ClaimBatch::factory()->create([
        'total_claims' => 1,
        'total_amount' => 500.00,
        'created_by' => $user->id,
    ]);

    // NHIS member ID comes from claim's membership_id field
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_surname' => 'Doe',
        'patient_other_names' => 'John',
        'patient_gender' => 'male',
        'patient_dob' => '1990-05-15',
        'membership_id' => 'NHIS-12345678',
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);

    ClaimBatchItem::factory()->create([
        'claim_batch_id' => $batch->id,
        'insurance_claim_id' => $claim->id,
        'claim_amount' => 500.00,
    ]);

    // Act
    $xml = $service->generateXml($batch);
    $parsed = $service->parseXml($xml);

    // Assert - Patient NHIS ID preserved (from claim's membership_id)
    expect($parsed['claims'])->toHaveCount(1);
    expect($parsed['claims'][0]['patient']['nhis_member_id'])->toBe('NHIS-12345678');
    expect($parsed['claims'][0]['patient']['surname'])->toBe('Doe');
    expect($parsed['claims'][0]['patient']['other_names'])->toBe('John');
    expect($parsed['claims'][0]['patient']['gender'])->toBe('male');
    expect($parsed['claims'][0]['patient']['date_of_birth'])->toBe('1990-05-15');
});

it('preserves G-DRG code and amount through XML export and parse round trip', function () {
    // Arrange
    $service = new NhisXmlExportService;
    $user = User::factory()->create();

    $gdrgTariff = GdrgTariff::factory()->create([
        'code' => 'GDRG-OPD001',
        'name' => 'General OPD Consultation',
        'tariff_price' => 150.00,
    ]);

    $batch = ClaimBatch::factory()->create([
        'total_claims' => 1,
        'total_amount' => 650.00,
        'created_by' => $user->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'gdrg_tariff_id' => $gdrgTariff->id,
        'gdrg_amount' => 150.00,
        'status' => 'vetted',
        'total_claim_amount' => 650.00,
    ]);

    ClaimBatchItem::factory()->create([
        'claim_batch_id' => $batch->id,
        'insurance_claim_id' => $claim->id,
        'claim_amount' => 650.00,
    ]);

    // Act
    $xml = $service->generateXml($batch);
    $parsed = $service->parseXml($xml);

    // Assert - G-DRG preserved
    expect($parsed['claims'])->toHaveCount(1);
    expect($parsed['claims'][0]['gdrg']['code'])->toBe('GDRG-OPD001');
    expect($parsed['claims'][0]['gdrg']['name'])->toBe('General OPD Consultation');
    expect($parsed['claims'][0]['gdrg']['amount'])->toBe(150.00);
});

it('preserves diagnoses with ICD-10 codes through XML export and parse round trip', function () {
    // Arrange
    $service = new NhisXmlExportService;
    $user = User::factory()->create();

    $primaryDiagnosis = Diagnosis::factory()->create([
        'diagnosis' => 'Malaria',
        'icd_10' => 'B50.9',
    ]);

    $secondaryDiagnosis = Diagnosis::factory()->create([
        'diagnosis' => 'Anemia',
        'icd_10' => 'D64.9',
    ]);

    $batch = ClaimBatch::factory()->create([
        'total_claims' => 1,
        'total_amount' => 500.00,
        'created_by' => $user->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);

    // Add diagnoses to claim
    InsuranceClaimDiagnosis::factory()->create([
        'insurance_claim_id' => $claim->id,
        'diagnosis_id' => $primaryDiagnosis->id,
        'is_primary' => true,
    ]);

    InsuranceClaimDiagnosis::factory()->create([
        'insurance_claim_id' => $claim->id,
        'diagnosis_id' => $secondaryDiagnosis->id,
        'is_primary' => false,
    ]);

    ClaimBatchItem::factory()->create([
        'claim_batch_id' => $batch->id,
        'insurance_claim_id' => $claim->id,
        'claim_amount' => 500.00,
    ]);

    // Act
    $xml = $service->generateXml($batch);
    $parsed = $service->parseXml($xml);

    // Assert - Diagnoses preserved
    expect($parsed['claims'])->toHaveCount(1);
    expect($parsed['claims'][0]['diagnoses'])->toHaveCount(2);

    // Find primary diagnosis
    $primaryParsed = collect($parsed['claims'][0]['diagnoses'])->firstWhere('is_primary', true);
    expect($primaryParsed)->not->toBeNull();
    expect($primaryParsed['icd10_code'])->toBe('B50.9');
    expect($primaryParsed['description'])->toBe('Malaria');

    // Find secondary diagnosis
    $secondaryParsed = collect($parsed['claims'][0]['diagnoses'])->firstWhere('is_primary', false);
    expect($secondaryParsed)->not->toBeNull();
    expect($secondaryParsed['icd10_code'])->toBe('D64.9');
    expect($secondaryParsed['description'])->toBe('Anemia');
});

it('preserves item NHIS codes and prices through XML export and parse round trip', function (int $itemCount) {
    // Arrange
    $service = new NhisXmlExportService;
    $user = User::factory()->create();

    $batch = ClaimBatch::factory()->create([
        'total_claims' => 1,
        'total_amount' => $itemCount * 100.00,
        'created_by' => $user->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => $itemCount * 100.00,
    ]);

    // Create items with NHIS codes
    $expectedItems = [];
    for ($i = 0; $i < $itemCount; $i++) {
        $nhisTariff = NhisTariff::factory()->create([
            'nhis_code' => 'NHIS-MED-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT),
            'price' => 100.00,
        ]);

        $item = InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'item_type' => 'drug',
            'code' => 'HOSP-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT),
            'description' => 'Test Drug '.($i + 1),
            'quantity' => 1,
            'nhis_tariff_id' => $nhisTariff->id,
            'nhis_code' => $nhisTariff->nhis_code,
            'nhis_price' => $nhisTariff->price,
            'unit_tariff' => 100.00,
            'subtotal' => 100.00,
            'is_covered' => true,
            'insurance_pays' => 100.00,
            'patient_pays' => 0.00,
        ]);

        $expectedItems[] = [
            'nhis_code' => $nhisTariff->nhis_code,
            'hospital_code' => $item->code,
            'description' => $item->description,
            'unit_price' => 100.00,
        ];
    }

    ClaimBatchItem::factory()->create([
        'claim_batch_id' => $batch->id,
        'insurance_claim_id' => $claim->id,
        'claim_amount' => $itemCount * 100.00,
    ]);

    // Act
    $xml = $service->generateXml($batch);
    $parsed = $service->parseXml($xml);

    // Assert - Items preserved
    expect($parsed['claims'])->toHaveCount(1);
    expect($parsed['claims'][0]['items'])->toHaveCount($itemCount);

    foreach ($expectedItems as $index => $expected) {
        $parsedItem = $parsed['claims'][0]['items'][$index];
        expect($parsedItem['nhis_code'])->toBe($expected['nhis_code']);
        expect($parsedItem['hospital_code'])->toBe($expected['hospital_code']);
        expect($parsedItem['description'])->toBe($expected['description']);
        expect($parsedItem['unit_price'])->toBe($expected['unit_price']);
    }
})->with('item_counts');

it('generates valid XML structure', function () {
    // Arrange
    $service = new NhisXmlExportService;
    $user = User::factory()->create();

    $batch = ClaimBatch::factory()->create([
        'total_claims' => 1,
        'total_amount' => 500.00,
        'created_by' => $user->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);

    ClaimBatchItem::factory()->create([
        'claim_batch_id' => $batch->id,
        'insurance_claim_id' => $claim->id,
        'claim_amount' => 500.00,
    ]);

    // Act
    $xml = $service->generateXml($batch);

    // Assert - Valid XML
    $dom = new DOMDocument;
    $isValid = $dom->loadXML($xml);
    expect($isValid)->toBeTrue();

    // Check root element
    expect($dom->documentElement->tagName)->toBe('NHIAClaimBatch');
    expect($dom->documentElement->getAttribute('xmlns'))->toBe('http://nhia.gov.gh/claims');
    expect($dom->documentElement->getAttribute('version'))->toBe('1.0');

    // Check required sections exist
    expect($dom->getElementsByTagName('Facility')->length)->toBe(1);
    expect($dom->getElementsByTagName('BatchDetails')->length)->toBe(1);
    expect($dom->getElementsByTagName('Claims')->length)->toBe(1);
});

it('handles special XML characters in data', function () {
    // Arrange
    $service = new NhisXmlExportService;
    $user = User::factory()->create();

    $batch = ClaimBatch::factory()->create([
        'name' => 'Test & Special <Characters> "Batch"',
        'total_claims' => 1,
        'total_amount' => 500.00,
        'created_by' => $user->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_surname' => "O'Brien",
        'patient_other_names' => 'John & Jane',
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);

    ClaimBatchItem::factory()->create([
        'claim_batch_id' => $batch->id,
        'insurance_claim_id' => $claim->id,
        'claim_amount' => 500.00,
    ]);

    // Act
    $xml = $service->generateXml($batch);

    // Assert - XML is valid despite special characters
    $dom = new DOMDocument;
    $isValid = $dom->loadXML($xml);
    expect($isValid)->toBeTrue();

    // Parse and verify data is preserved
    $parsed = $service->parseXml($xml);
    expect($parsed['batch']['batch_name'])->toBe('Test & Special <Characters> "Batch"');
    expect($parsed['claims'][0]['patient']['surname'])->toBe("O'Brien");
    expect($parsed['claims'][0]['patient']['other_names'])->toBe('John & Jane');
});

it('preserves claim totals through XML export and parse round trip', function () {
    // Arrange
    $service = new NhisXmlExportService;
    $user = User::factory()->create();

    $batch = ClaimBatch::factory()->create([
        'total_claims' => 1,
        'total_amount' => 1000.00,
        'created_by' => $user->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 1000.00,
        'insurance_covered_amount' => 800.00,
        'patient_copay_amount' => 200.00,
    ]);

    ClaimBatchItem::factory()->create([
        'claim_batch_id' => $batch->id,
        'insurance_claim_id' => $claim->id,
        'claim_amount' => 1000.00,
    ]);

    // Act
    $xml = $service->generateXml($batch);
    $parsed = $service->parseXml($xml);

    // Assert - Totals preserved
    expect($parsed['claims'])->toHaveCount(1);
    expect($parsed['claims'][0]['totals']['total_claim_amount'])->toBe(1000.00);
    expect($parsed['claims'][0]['totals']['insurance_covered_amount'])->toBe(800.00);
    expect($parsed['claims'][0]['totals']['patient_copay_amount'])->toBe(200.00);
});
