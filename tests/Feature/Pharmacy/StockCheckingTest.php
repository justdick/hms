<?php

use App\Models\Drug;
use App\Models\DrugBatch;
use App\Services\PharmacyStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stockService = app(PharmacyStockService::class);
});

test('check availability returns correct status when stock is sufficient', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(100)->create();

    $result = $this->stockService->checkAvailability($drug, 50);

    expect($result['available'])->toBeTrue()
        ->and($result['in_stock'])->toBe(100)
        ->and($result['shortage'])->toBe(0);
});

test('check availability returns false when stock is insufficient', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(30)->create();

    $result = $this->stockService->checkAvailability($drug, 50);

    expect($result['available'])->toBeFalse()
        ->and($result['in_stock'])->toBe(30)
        ->and($result['shortage'])->toBe(20);
});

test('check availability excludes expired batches', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(50)->expired()->create();
    DrugBatch::factory()->for($drug)->withQuantity(30)->create();

    $result = $this->stockService->checkAvailability($drug, 40);

    expect($result['available'])->toBeFalse()
        ->and($result['in_stock'])->toBe(30);
});

test('get available batches returns batches sorted by expiry date', function () {
    $drug = Drug::factory()->create();
    $batch1 = DrugBatch::factory()->for($drug)->withQuantity(50)->create(['expiry_date' => now()->addMonths(12)]);
    $batch2 = DrugBatch::factory()->for($drug)->withQuantity(30)->create(['expiry_date' => now()->addMonths(6)]);
    $batch3 = DrugBatch::factory()->for($drug)->withQuantity(20)->create(['expiry_date' => now()->addMonths(18)]);

    $batches = $this->stockService->getAvailableBatches($drug, 100);

    expect($batches)->toHaveCount(3)
        ->and($batches->first()->id)->toBe($batch2->id)
        ->and($batches->last()->id)->toBe($batch3->id);
});

test('deduct stock reduces quantity from batches using FIFO', function () {
    $drug = Drug::factory()->create();
    $batch1 = DrugBatch::factory()->for($drug)->withQuantity(50)->create(['expiry_date' => now()->addMonths(6)]);
    $batch2 = DrugBatch::factory()->for($drug)->withQuantity(100)->create(['expiry_date' => now()->addMonths(12)]);

    $result = $this->stockService->deductStock($drug, 80);

    expect($result['success'])->toBeTrue()
        ->and($result['remaining_needed'])->toBe(0)
        ->and($result['deducted'])->toHaveCount(2);

    $batch1->refresh();
    $batch2->refresh();

    expect($batch1->quantity_remaining)->toBe(0)
        ->and($batch2->quantity_remaining)->toBe(70);
});

test('deduct stock returns failure when insufficient stock', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(30)->create();

    $result = $this->stockService->deductStock($drug, 50);

    expect($result['success'])->toBeFalse()
        ->and($result['remaining_needed'])->toBe(20);
});

test('get stock status returns correct indicator', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(100)->create();

    expect($this->stockService->getStockStatus($drug, 50))->toBe('in_stock')
        ->and($this->stockService->getStockStatus($drug, 150))->toBe('partial');
});

test('get stock status returns out of stock when no quantity available', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(0)->create();

    expect($this->stockService->getStockStatus($drug, 10))->toBe('out_of_stock');
});

test('has expiring batches detects batches expiring within 30 days', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(50)->expiringSoon(15)->create();

    expect($this->stockService->hasExpiringBatches($drug))->toBeTrue();
});

test('has expiring batches returns false when no batches expiring soon', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(50)->create(['expiry_date' => now()->addMonths(6)]);

    expect($this->stockService->hasExpiringBatches($drug))->toBeFalse();
});
