<?php

use App\Services\CoveragePresetService;

it('returns all coverage presets', function () {
    $service = new CoveragePresetService;
    $presets = $service->getPresets();

    expect($presets)->toBeArray()
        ->and($presets)->toHaveCount(4);
});

it('includes NHIS Standard preset', function () {
    $service = new CoveragePresetService;
    $presets = $service->getPresets();

    $nhis = collect($presets)->firstWhere('id', 'nhis_standard');

    expect($nhis)->not->toBeNull()
        ->and($nhis['name'])->toBe('NHIS Standard')
        ->and($nhis['coverages'])->toBeArray()
        ->and($nhis['coverages']['consultation'])->toBe(70)
        ->and($nhis['coverages']['drug'])->toBe(80)
        ->and($nhis['coverages']['lab'])->toBe(90)
        ->and($nhis['coverages']['procedure'])->toBe(75)
        ->and($nhis['coverages']['ward'])->toBe(100)
        ->and($nhis['coverages']['nursing'])->toBe(80);
});

it('includes Corporate Premium preset', function () {
    $service = new CoveragePresetService;
    $presets = $service->getPresets();

    $corporate = collect($presets)->firstWhere('id', 'corporate_premium');

    expect($corporate)->not->toBeNull()
        ->and($corporate['name'])->toBe('Corporate Premium')
        ->and($corporate['coverages'])->toBeArray()
        ->and($corporate['coverages']['consultation'])->toBe(90)
        ->and($corporate['coverages']['drug'])->toBe(90)
        ->and($corporate['coverages']['lab'])->toBe(100)
        ->and($corporate['coverages']['procedure'])->toBe(90)
        ->and($corporate['coverages']['ward'])->toBe(100)
        ->and($corporate['coverages']['nursing'])->toBe(90);
});

it('includes Basic Coverage preset', function () {
    $service = new CoveragePresetService;
    $presets = $service->getPresets();

    $basic = collect($presets)->firstWhere('id', 'basic');

    expect($basic)->not->toBeNull()
        ->and($basic['name'])->toBe('Basic Coverage')
        ->and($basic['coverages'])->toBeArray()
        ->and($basic['coverages']['consultation'])->toBe(50)
        ->and($basic['coverages']['drug'])->toBe(60)
        ->and($basic['coverages']['lab'])->toBe(70)
        ->and($basic['coverages']['procedure'])->toBe(50)
        ->and($basic['coverages']['ward'])->toBe(80)
        ->and($basic['coverages']['nursing'])->toBe(60);
});

it('includes Custom preset with null coverages', function () {
    $service = new CoveragePresetService;
    $presets = $service->getPresets();

    $custom = collect($presets)->firstWhere('id', 'custom');

    expect($custom)->not->toBeNull()
        ->and($custom['name'])->toBe('Custom')
        ->and($custom['coverages'])->toBeNull();
});

it('ensures all presets have required fields', function () {
    $service = new CoveragePresetService;
    $presets = $service->getPresets();

    foreach ($presets as $preset) {
        expect($preset)->toHaveKeys(['id', 'name', 'description', 'coverages']);
    }
});
