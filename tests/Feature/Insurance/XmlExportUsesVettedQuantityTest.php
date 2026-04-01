<?php

use App\Models\Charge;
use App\Models\ClaimBatch;
use App\Models\ClaimBatchItem;
use App\Models\Drug;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\Prescription;
use App\Models\User;
use App\Services\NhisXmlExportService;
use Spatie\Permission\Models\Permission;

/**
 * Verifies that XML export uses the vetted claim item quantity,
 * not the raw prescription quantity.
 */
beforeEach(function () {
    Permission::firstOrCreate(['name' => 'insurance.export-batches']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('insurance.export-batches');
});

it('uses claim item quantity in XML export when charge has a prescription with different qty', function () {
    // Arrange: prescription qty=6000, but vetting officer set claim item qty=30
    $drug = Drug::factory()->create([
        'drug_code' => 'IBUPROTA1',
        'form' => 'tablet',
        'nhis_claim_qty_as_one' => false,
    ]);

    // Create prescription without firing events (avoids auto-charge creation)
    $prescription = Prescription::withoutEvents(fn () => Prescription::factory()->create([
        'drug_id' => $drug->id,
        'quantity' => 6000,
    ]));

    $charge = Charge::factory()->create([
        'prescription_id' => $prescription->id,
        'service_type' => 'pharmacy',
        'charge_type' => 'medication',
    ]);

    $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
    $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);

    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'item_type' => 'drug',
        'code' => 'IBUPROTA1',
        'description' => 'Ibuprofen Tablet, 200 mg Tablet (6000 tablet)',
        'quantity' => 30, // Vetted quantity
        'charge_id' => $charge->id,
        'nhis_code' => 'IBUPROTA1',
        'nhis_price' => 0.22,
    ]);

    ClaimBatchItem::factory()->create([
        'claim_batch_id' => $batch->id,
        'insurance_claim_id' => $claim->id,
    ]);

    // Act
    $response = $this->actingAs($this->user)
        ->get("/admin/insurance/claims/export-batch/{$batch->id}");

    // Assert: XML should contain vetted qty (30), NOT prescription qty (6000)
    $xml = $response->getContent();
    expect($xml)->toContain('<dispensedQty>30</dispensedQty>');
    expect($xml)->not->toContain('<dispensedQty>6000</dispensedQty>');
});

it('uses claim item quantity in DOM-based XML generation', function () {
    $drug = Drug::factory()->create([
        'drug_code' => 'TESTDRUG1',
        'form' => 'tablet',
        'nhis_claim_qty_as_one' => false,
    ]);

    $prescription = Prescription::withoutEvents(fn () => Prescription::factory()->create([
        'drug_id' => $drug->id,
        'quantity' => 500,
    ]));

    $charge = Charge::factory()->create([
        'prescription_id' => $prescription->id,
        'service_type' => 'pharmacy',
        'charge_type' => 'medication',
    ]);

    $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
    $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);

    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'item_type' => 'drug',
        'code' => 'TESTDRUG1',
        'quantity' => 10, // Vetted quantity
        'charge_id' => $charge->id,
    ]);

    ClaimBatchItem::factory()->create([
        'claim_batch_id' => $batch->id,
        'insurance_claim_id' => $claim->id,
    ]);

    // Act
    $service = new NhisXmlExportService;
    $xml = $service->generateXml($batch);

    // Assert
    expect($xml)->toContain('<dispensedQty>10</dispensedQty>');
    expect($xml)->not->toContain('<dispensedQty>500</dispensedQty>');
});
