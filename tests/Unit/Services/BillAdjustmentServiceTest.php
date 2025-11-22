<?php

use App\Services\BillAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new BillAdjustmentService;
});

describe('BillAdjustmentService - calculateAdjustedAmount', function () {
    it('calculates percentage discount correctly', function () {
        $result = $this->service->calculateAdjustedAmount(100, 'discount_percentage', 20);

        expect($result)->toBe(80.0);
    });

    it('calculates fixed discount correctly', function () {
        $result = $this->service->calculateAdjustedAmount(100, 'discount_fixed', 25);

        expect($result)->toBe(75.0);
    });

    it('returns zero for waiver', function () {
        $result = $this->service->calculateAdjustedAmount(100, 'waiver', 0);

        expect($result)->toBe(0.0);
    });

    it('handles decimal amounts correctly', function () {
        $result = $this->service->calculateAdjustedAmount(123.45, 'discount_percentage', 15);

        expect($result)->toBe(104.93);
    });

    it('throws exception for negative original amount', function () {
        $this->service->calculateAdjustedAmount(-100, 'discount_percentage', 20);
    })->throws(\InvalidArgumentException::class, 'Original amount cannot be negative');

    it('throws exception for negative adjustment value', function () {
        $this->service->calculateAdjustedAmount(100, 'discount_percentage', -20);
    })->throws(\InvalidArgumentException::class, 'Adjustment value cannot be negative');

    it('throws exception for invalid adjustment type', function () {
        $this->service->calculateAdjustedAmount(100, 'invalid_type', 20);
    })->throws(\InvalidArgumentException::class);

    it('throws exception for percentage over 100', function () {
        $this->service->calculateAdjustedAmount(100, 'discount_percentage', 150);
    })->throws(\InvalidArgumentException::class, 'Percentage must be between 0 and 100');

    it('throws exception for fixed discount exceeding amount', function () {
        $this->service->calculateAdjustedAmount(100, 'discount_fixed', 150);
    })->throws(\InvalidArgumentException::class, 'Discount amount cannot exceed original amount');
});

describe('BillAdjustmentService - calculateAdjustmentAmount', function () {
    it('calculates adjustment amount for percentage discount', function () {
        $result = $this->service->calculateAdjustmentAmount(100, 'discount_percentage', 20);

        expect($result)->toBe(20.0);
    });

    it('calculates adjustment amount for fixed discount', function () {
        $result = $this->service->calculateAdjustmentAmount(100, 'discount_fixed', 25);

        expect($result)->toBe(25.0);
    });

    it('calculates full amount for waiver', function () {
        $result = $this->service->calculateAdjustmentAmount(100, 'waiver', 0);

        expect($result)->toBe(100.0);
    });
});
