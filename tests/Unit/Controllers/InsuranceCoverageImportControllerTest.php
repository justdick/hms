<?php

use App\Http\Controllers\Admin\InsuranceCoverageImportController;

uses()->group('unit', 'controllers');

it('processes percentage coverage type correctly', function () {
    $controller = new InsuranceCoverageImportController;
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('processCoverageType');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'percentage', 80.0);

    expect($result)->toBe([
        'coverage_value' => 80.0,
        'copay_percentage' => 20.0,
        'is_covered' => true,
    ]);
});

it('processes fixed_amount coverage type correctly', function () {
    $controller = new InsuranceCoverageImportController;
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('processCoverageType');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'fixed_amount', 30.0);

    expect($result)->toBe([
        'coverage_value' => 30.0,
        'copay_percentage' => 0,
        'is_covered' => true,
    ]);
});

it('processes full coverage type correctly', function () {
    $controller = new InsuranceCoverageImportController;
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('processCoverageType');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'full', 100.0);

    expect($result)->toBe([
        'coverage_value' => 100,
        'copay_percentage' => 0,
        'is_covered' => true,
    ]);
});

it('processes excluded coverage type correctly', function () {
    $controller = new InsuranceCoverageImportController;
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('processCoverageType');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'excluded', 0.0);

    expect($result)->toBe([
        'coverage_value' => 0,
        'copay_percentage' => 100,
        'is_covered' => false,
    ]);
});

it('calculates copay correctly for percentage type', function () {
    $controller = new InsuranceCoverageImportController;
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('processCoverageType');
    $method->setAccessible(true);

    // Test various percentages
    $result75 = $method->invoke($controller, 'percentage', 75.0);
    expect($result75['copay_percentage'])->toBe(25.0);

    $result50 = $method->invoke($controller, 'percentage', 50.0);
    expect($result50['copay_percentage'])->toBe(50.0);

    $result100 = $method->invoke($controller, 'percentage', 100.0);
    expect($result100['copay_percentage'])->toBe(0.0);
});
