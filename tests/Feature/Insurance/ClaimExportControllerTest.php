<?php

/**
 * Feature tests for ClaimExportController
 *
 * Tests the XML export functionality for NHIA claim batch submission.
 * XML format matches the exact NHIS portal submission format.
 *
 * _Requirements: 15.1, 15.2, 15.3_
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
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'insurance.view-batches']);
    Permission::firstOrCreate(['name' => 'insurance.manage-batches']);
    Permission::firstOrCreate(['name' => 'insurance.export-batches']);
});

describe('exportXml', function () {
    it('exports a batch to XML format', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create([
            'batch_number' => 'BATCH-202511-0001',
            'name' => 'November 2025 Claims',
            'submission_period' => '2025-11-01',
            'status' => 'finalized',
            'total_claims' => 1,
            'total_amount' => 500.00,
        ]);

        $claim = InsuranceClaim::factory()->create([
            'claim_check_code' => '12345',
            'status' => 'vetted',
            'total_claim_amount' => 500.00,
        ]);

        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
            'claim_amount' => 500.00,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertHeader('Content-Disposition', 'attachment; filename="nhis-batch-BATCH-202511-0001.xml"');

        // Verify XML content - NHIS format uses <claims> root
        $xml = $response->getContent();
        expect($xml)->toContain('<claims>');
        expect($xml)->toContain('<claim>');
        expect($xml)->toContain('<claimCheckCode>12345</claimCheckCode>');
    });

    it('records export timestamp when exporting', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create([
            'status' => 'finalized',
            'exported_at' => null,
        ]);

        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $batch->refresh();
        expect($batch->exported_at)->not->toBeNull();
    });

    it('includes patient information in NHIS format', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $patient = Patient::factory()->create();

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create([
            'patient_id' => $patient->id,
            'membership_id' => 'NHIS-12345678',
            'patient_surname' => 'SMITH',
            'patient_other_names' => 'JOHN',
            'patient_gender' => 'M',
            'folder_id' => '1234/2025',
            'status' => 'vetted',
        ]);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert - NHIS format uses flat structure with camelCase tags
        $xml = $response->getContent();
        expect($xml)->toContain('<memberNo>NHIS-12345678</memberNo>');
        expect($xml)->toContain('<surname>SMITH</surname>');
        expect($xml)->toContain('<otherNames>JOHN</otherNames>');
        expect($xml)->toContain('<gender>M</gender>');
        expect($xml)->toContain('<hospitalRecNo>1234/2025</hospitalRecNo>');
        expect($xml)->toContain('<isDependant>0</isDependant>');
    });

    it('includes service information in NHIS format', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create([
            'type_of_service' => 'OPD',
            'type_of_attendance' => 'EAE',
            'specialty_attended' => 'OPDC',
            'is_unbundled' => false,
            'is_pharmacy_included' => true,
            'date_of_attendance' => '2025-09-01',
            'date_of_discharge' => '2025-09-01',
            'status' => 'vetted',
        ]);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Add a drug item so includesPharmacy is 1
        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'item_type' => 'drug',
            'code' => 'DRUG001',
            'description' => 'Test Drug',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $xml = $response->getContent();
        expect($xml)->toContain('<typeOfService>OPD</typeOfService>');
        expect($xml)->toContain('<typeOfAttendance>EAE</typeOfAttendance>');
        expect($xml)->toContain('<specialtyAttended>OPDC</specialtyAttended>');
        expect($xml)->toContain('<isUnbundled>0</isUnbundled>');
        expect($xml)->toContain('<includesPharmacy>1</includesPharmacy>');
        expect($xml)->toContain('<serviceOutcome>DISC</serviceOutcome>');
        expect($xml)->toContain('<dateOfService>2025-09-01</dateOfService>');
    });

    it('includes G-DRG code in diagnosis element', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $gdrgTariff = GdrgTariff::factory()->create([
            'code' => 'OPDC06A',
            'name' => 'General OPD Consultation',
            'tariff_price' => 150.00,
        ]);

        $diagnosis = Diagnosis::factory()->create([
            'diagnosis' => 'MALARIA',
            'icd_10' => 'B50',
        ]);

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create([
            'gdrg_tariff_id' => $gdrgTariff->id,
            'gdrg_amount' => 150.00,
            'date_of_attendance' => '2025-09-01',
            'status' => 'vetted',
        ]);

        InsuranceClaimDiagnosis::factory()->create([
            'insurance_claim_id' => $claim->id,
            'diagnosis_id' => $diagnosis->id,
            'is_primary' => true,
        ]);

        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert - NHIS format includes gdrgCode in diagnosis element
        $xml = $response->getContent();
        expect($xml)->toContain('<diagnosis>');
        expect($xml)->toContain('<gdrgCode>OPDC06A</gdrgCode>');
        expect($xml)->toContain('<ICD10>B50</ICD10>');
        expect($xml)->toContain('<diagnosis>MALARIA</diagnosis>');
    });

    it('includes medicines with prescription details in NHIS format', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $nhisTariff = NhisTariff::factory()->create([
            'nhis_code' => 'ARTLUMTA4',
            'price' => 50.00,
        ]);

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create([
            'date_of_attendance' => '2025-09-01',
            'status' => 'vetted',
        ]);

        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'item_type' => 'drug',
            'item_date' => '2025-09-01',
            'code' => 'HOSP-001',
            'description' => 'Artemether/Lumefantrine',
            'quantity' => 1,
            'nhis_tariff_id' => $nhisTariff->id,
            'nhis_code' => 'ARTLUMTA4',
            'nhis_price' => 50.00,
        ]);

        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert - NHIS format uses <medicine> with nested <prescription>
        $xml = $response->getContent();
        expect($xml)->toContain('<medicine>');
        expect($xml)->toContain('<medicineCode>ARTLUMTA4</medicineCode>');
        expect($xml)->toContain('<dispensedQty>1</dispensedQty>');
        expect($xml)->toContain('<serviceDate>2025-09-01</serviceDate>');
        expect($xml)->toContain('<prescription>');
        expect($xml)->toContain('<dose>');
        expect($xml)->toContain('<frequency>');
        expect($xml)->toContain('<duration>');
        expect($xml)->toContain('<unparsed>');
    });

    it('includes referral info element', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert - NHIS format always includes referralInfo
        $xml = $response->getContent();
        expect($xml)->toContain('<referralInfo>');
        expect($xml)->toContain('<facilityID>');
        expect($xml)->toContain('<facilityName>');
    });

    it('generates valid XML structure with claims root', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert - Valid XML with NHIS format
        $xml = $response->getContent();
        $dom = new DOMDocument;
        $isValid = $dom->loadXML($xml);
        expect($isValid)->toBeTrue();

        // Check root element is <claims>
        expect($dom->documentElement->tagName)->toBe('claims');
    });

    it('denies export to unauthorized user', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $response->assertForbidden();
    });

    it('allows export of draft batch', function () {
        // Arrange - Export should work for any batch status
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'draft']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
    });

    it('handles special characters in data', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create([
            'name' => 'Test & Special <Characters> "Batch"',
            'status' => 'finalized',
        ]);

        $claim = InsuranceClaim::factory()->create([
            'patient_surname' => "O'Brien",
            'patient_other_names' => 'John & Jane',
            'status' => 'vetted',
        ]);

        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert - XML should be valid despite special characters
        $xml = $response->getContent();
        $dom = new DOMDocument;
        $isValid = $dom->loadXML($xml);
        expect($isValid)->toBeTrue();
    });

    it('sets includesPharmacy based on drug items', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create([
            'is_pharmacy_included' => false,
            'status' => 'vetted',
        ]);

        // Add a drug item
        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'item_type' => 'drug',
            'nhis_code' => 'PARACETA1',
            'quantity' => 30,
        ]);

        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert - includesPharmacy should be 1 when drug items exist
        $xml = $response->getContent();
        expect($xml)->toContain('<includesPharmacy>1</includesPharmacy>');
    });

    it('exports multiple claims in batch', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create([
            'status' => 'finalized',
            'total_claims' => 3,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $claim = InsuranceClaim::factory()->create([
                'claim_check_code' => "1000{$i}",
                'status' => 'vetted',
            ]);
            ClaimBatchItem::factory()->create([
                'claim_batch_id' => $batch->id,
                'insurance_claim_id' => $claim->id,
            ]);
        }

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $xml = $response->getContent();
        expect($xml)->toContain('<claimCheckCode>10001</claimCheckCode>');
        expect($xml)->toContain('<claimCheckCode>10002</claimCheckCode>');
        expect($xml)->toContain('<claimCheckCode>10003</claimCheckCode>');

        // Count claim elements
        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $claimElements = $dom->getElementsByTagName('claim');
        expect($claimElements->length)->toBe(3);
    });
});
