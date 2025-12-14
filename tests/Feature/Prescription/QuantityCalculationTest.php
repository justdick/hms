<?php

/**
 * Property-Based Tests for Prescription Quantity Calculations
 *
 * Feature: prescription-quantity-testing
 *
 * These tests verify that prescription quantity calculations are correct
 * across all drug forms using property-based testing with random inputs.
 */

use App\Models\Drug;
use App\Services\Prescription\PrescriptionParserService;

beforeEach(function () {
    $this->parser = new PrescriptionParserService;
});

/**
 * Feature: prescription-quantity-testing, Property 1: Piece-based quantity calculation
 *
 * For any piece-based drug (tablet, capsule, suppository, sachet, vial, lozenge,
 * pessary, enema, IV bag) with dose N, frequency F, and duration D days,
 * the quantity should equal N × frequency_multiplier(F) × D.
 *
 * **Validates: Requirements 1.1, 1.9**
 */
it('calculates piece-based drug quantity as dose × frequency_multiplier × duration', function () {
    // Run 100+ iterations with random inputs
    for ($i = 0; $i < 100; $i++) {
        // Generate random piece-based drug form
        $pieceBasedForms = PIECE_BASED_FORMS;
        $form = $pieceBasedForms[array_rand($pieceBasedForms)];

        // Create drug with the selected form
        $drug = new Drug;
        $drug->form = $form;
        $drug->unit_type = 'piece';
        $drug->bottle_size = null;

        // Generate random dose (1-10)
        $dose = rand(1, 10);

        // Generate random frequency
        $frequencies = getValidFrequencyCodes();
        $frequencyCode = $frequencies[array_rand($frequencies)];
        $frequencyMultiplier = getFrequencyMultiplier($frequencyCode);

        // Generate random duration (1-30 days)
        $duration = rand(1, 30);

        // Build input string
        $input = "{$dose} {$frequencyCode} x {$duration} days";

        // Parse the prescription
        $result = $this->parser->parse($input, $drug);

        // Calculate expected quantity
        $expectedQuantity = (int) ceil($dose * $frequencyMultiplier * $duration);

        // Assert the result
        expect($result->isValid)->toBeTrue(
            "Failed to parse: '{$input}' for {$form} drug"
        );
        expect($result->quantityToDispense)->toBe(
            $expectedQuantity,
            "Quantity mismatch for '{$input}' ({$form}): expected {$expectedQuantity}, got {$result->quantityToDispense}"
        );
    }
});

/**
 * Feature: prescription-quantity-testing, Property 1: Piece-based quantity calculation
 *
 * Additional test: Verify each piece-based drug form individually
 * to ensure the form is correctly identified as piece-based.
 *
 * **Validates: Requirements 1.1, 1.9**
 */
it('correctly identifies all piece-based drug forms', function (string $form) {
    $drug = new Drug;
    $drug->form = $form;
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    // Use a simple prescription: 2 BD x 5 days
    // Expected: 2 × 2 × 5 = 20
    $input = '2 BD x 5 days';
    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe(20);
})->with(PIECE_BASED_FORMS);

/**
 * Feature: prescription-quantity-testing, Property 2: Frequency multiplier consistency
 *
 * For any frequency code in {OD, BD, TDS, QDS, Q6H, Q8H, Q12H},
 * the parser should return a consistent multiplier value that matches
 * the medical standard (1, 2, 3, 4, 4, 3, 2 respectively).
 *
 * **Validates: Requirements 1.2-1.8**
 */
it('returns consistent frequency multipliers for all frequency codes', function () {
    $expectedMultipliers = FREQUENCY_MULTIPLIERS;

    foreach ($expectedMultipliers as $code => $expectedMultiplier) {
        $result = $this->parser->parseFrequency($code);

        expect($result)->not->toBeNull(
            "Failed to parse frequency code: {$code}"
        );
        expect($result['times_per_day'])->toBe(
            $expectedMultiplier,
            "Multiplier mismatch for {$code}: expected {$expectedMultiplier}, got {$result['times_per_day']}"
        );
    }
});

/**
 * Feature: prescription-quantity-testing, Property 2: Frequency multiplier consistency
 *
 * Property test: For any valid frequency code, the quantity calculation
 * should use the correct multiplier regardless of case.
 *
 * **Validates: Requirements 1.2-1.8**
 */
it('applies frequency multipliers correctly in quantity calculations', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    // Test each frequency code with a fixed dose and duration
    $dose = 1;
    $duration = 10;

    foreach (FREQUENCY_MULTIPLIERS as $code => $multiplier) {
        $input = "{$dose} {$code} x {$duration} days";
        $result = $this->parser->parse($input, $drug);

        $expectedQuantity = $dose * $multiplier * $duration;

        expect($result->isValid)->toBeTrue()
            ->and($result->quantityToDispense)->toBe(
                $expectedQuantity,
                "Quantity mismatch for {$code}: expected {$expectedQuantity}, got {$result->quantityToDispense}"
            );
    }
});

/**
 * Feature: prescription-quantity-testing, Property 2: Frequency multiplier consistency
 *
 * Property test: Frequency codes should be case-insensitive.
 *
 * **Validates: Requirements 1.2-1.8**
 */
it('parses frequency codes case-insensitively for quantity calculation', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $codes = ['OD', 'BD', 'TDS', 'QDS', 'Q6H', 'Q8H', 'Q12H'];

    foreach ($codes as $code) {
        $upperInput = "2 {$code} x 5 days";
        $lowerInput = '2 '.strtolower($code).' x 5 days';
        $mixedInput = '2 '.ucfirst(strtolower($code)).' x 5 days';

        $upperResult = $this->parser->parse($upperInput, $drug);
        $lowerResult = $this->parser->parse($lowerInput, $drug);
        $mixedResult = $this->parser->parse($mixedInput, $drug);

        expect($upperResult->isValid)->toBeTrue()
            ->and($lowerResult->isValid)->toBeTrue()
            ->and($mixedResult->isValid)->toBeTrue()
            ->and($upperResult->quantityToDispense)->toBe($lowerResult->quantityToDispense)
            ->and($upperResult->quantityToDispense)->toBe($mixedResult->quantityToDispense);
    }
});

/**
 * Feature: prescription-quantity-testing, Property 1: Piece-based quantity calculation
 *
 * Edge case: Verify quantity calculation with decimal doses (e.g., 0.5 tablets).
 *
 * **Validates: Requirements 1.1, 1.9**
 */
it('handles decimal doses correctly for piece-based drugs', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    // Test cases with decimal doses
    $testCases = [
        ['0.5 BD x 10 days', 10],  // 0.5 × 2 × 10 = 10
        ['1.5 TDS x 7 days', 32],  // 1.5 × 3 × 7 = 31.5 → ceil = 32
        ['2.5 OD x 14 days', 35],  // 2.5 × 1 × 14 = 35
    ];

    foreach ($testCases as [$input, $expectedQuantity]) {
        $result = $this->parser->parse($input, $drug);

        expect($result->isValid)->toBeTrue()
            ->and($result->quantityToDispense)->toBe($expectedQuantity);
    }
});

/**
 * Feature: prescription-quantity-testing, Property 1: Piece-based quantity calculation
 *
 * Property test: Quantity should always be a positive integer (ceiling applied).
 *
 * **Validates: Requirements 1.1, 1.9**
 */
it('always returns positive integer quantities for piece-based drugs', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    // Run 50 iterations with random inputs
    for ($i = 0; $i < 50; $i++) {
        $dose = rand(1, 10);
        $frequencies = getValidFrequencyCodes();
        $frequencyCode = $frequencies[array_rand($frequencies)];
        $duration = rand(1, 30);

        $input = "{$dose} {$frequencyCode} x {$duration} days";
        $result = $this->parser->parse($input, $drug);

        expect($result->isValid)->toBeTrue();
        expect($result->quantityToDispense)->toBeInt();
        expect($result->quantityToDispense)->toBeGreaterThan(0);
    }
});

/**
 * Feature: prescription-quantity-testing, Property 3: Volume-based bottle calculation
 *
 * For any volume-based drug (syrup, suspension) with dose M ml, frequency F,
 * duration D days, and bottle_size B ml, the quantity should equal
 * ceil((M × frequency_multiplier(F) × D) / B) bottles.
 *
 * **Validates: Requirements 2.1, 2.2, 2.5**
 */
it('calculates volume-based drug quantity as ceil(ml × frequency × days / bottle_size)', function () {
    // Run 100+ iterations with random inputs
    for ($i = 0; $i < 100; $i++) {
        // Generate random volume-based drug form
        $volumeBasedForms = VOLUME_BASED_FORMS;
        $form = $volumeBasedForms[array_rand($volumeBasedForms)];

        // Generate random bottle size (common sizes: 60, 100, 125, 200ml)
        $bottleSizes = [60, 100, 125, 200];
        $bottleSize = $bottleSizes[array_rand($bottleSizes)];

        // Create drug with the selected form and bottle size
        $drug = new Drug;
        $drug->form = $form;
        $drug->unit_type = 'bottle';
        $drug->bottle_size = $bottleSize;

        // Generate random dose in ml (common doses: 2.5, 5, 10, 15, 20ml)
        $doses = [2.5, 5, 10, 15, 20];
        $doseValue = $doses[array_rand($doses)];

        // Generate random frequency
        $frequencies = getValidFrequencyCodes();
        $frequencyCode = $frequencies[array_rand($frequencies)];
        $frequencyMultiplier = getFrequencyMultiplier($frequencyCode);

        // Generate random duration (1-30 days)
        $duration = rand(1, 30);

        // Build input string with ml unit
        $input = "{$doseValue}ml {$frequencyCode} x {$duration} days";

        // Parse the prescription
        $result = $this->parser->parse($input, $drug);

        // Calculate expected quantity (bottles)
        $totalMl = $doseValue * $frequencyMultiplier * $duration;
        $expectedBottles = (int) ceil($totalMl / $bottleSize);

        // Assert the result
        expect($result->isValid)->toBeTrue(
            "Failed to parse: '{$input}' for {$form} drug with bottle_size {$bottleSize}ml"
        );
        expect($result->quantityToDispense)->toBe(
            $expectedBottles,
            "Bottle count mismatch for '{$input}' ({$form}, {$bottleSize}ml bottle): expected {$expectedBottles}, got {$result->quantityToDispense}. Total ml needed: {$totalMl}"
        );
    }
});

/**
 * Feature: prescription-quantity-testing, Property 3: Volume-based bottle calculation
 *
 * Additional test: Verify each volume-based drug form individually
 * to ensure the form is correctly identified as volume-based.
 *
 * **Validates: Requirements 2.1, 2.2, 2.5**
 */
it('correctly identifies all volume-based drug forms', function (string $form) {
    $drug = new Drug;
    $drug->form = $form;
    $drug->unit_type = 'bottle';
    $drug->bottle_size = 100; // 100ml bottle

    // Use a simple prescription: 5ml TDS x 7 days
    // Total ml = 5 × 3 × 7 = 105ml
    // Expected bottles = ceil(105 / 100) = 2
    $input = '5ml TDS x 7 days';
    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe(2);
})->with(VOLUME_BASED_FORMS);

/**
 * Feature: prescription-quantity-testing, Property 3: Volume-based bottle calculation
 *
 * Test specific bottle size scenarios from requirements.
 *
 * **Validates: Requirements 2.3, 2.4**
 */
it('calculates correct bottle count for specific scenarios', function (int $bottleSize, int $totalMl, int $expectedBottles) {
    $drug = new Drug;
    $drug->form = 'syrup';
    $drug->unit_type = 'bottle';
    $drug->bottle_size = $bottleSize;

    // Calculate dose and duration to achieve the target total ml
    // Using OD (1x daily) for simplicity
    $doseValue = $totalMl; // Use total ml as single dose for 1 day
    $input = "{$doseValue}ml OD x 1 days";

    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe($expectedBottles);
})->with([
    'bottle_size 100ml, total 105ml → 2 bottles' => [100, 105, 2],
    'bottle_size 125ml, total 75ml → 1 bottle' => [125, 75, 1],
    'bottle_size 100ml, total 100ml → 1 bottle (exact)' => [100, 100, 1],
    'bottle_size 100ml, total 200ml → 2 bottles (exact)' => [100, 200, 2],
    'bottle_size 60ml, total 180ml → 3 bottles (exact)' => [60, 180, 3],
    'bottle_size 60ml, total 181ml → 4 bottles (ceiling)' => [60, 181, 4],
]);

/**
 * Feature: prescription-quantity-testing, Property 3: Volume-based bottle calculation
 *
 * Property test: Bottle quantity should always be a positive integer (ceiling applied).
 *
 * **Validates: Requirements 2.1, 2.2, 2.5**
 */
it('always returns positive integer bottle quantities for volume-based drugs', function () {
    $drug = new Drug;
    $drug->form = 'syrup';
    $drug->unit_type = 'bottle';
    $drug->bottle_size = 100;

    // Run 50 iterations with random inputs
    for ($i = 0; $i < 50; $i++) {
        $doses = [2.5, 5, 10, 15, 20];
        $doseValue = $doses[array_rand($doses)];
        $frequencies = getValidFrequencyCodes();
        $frequencyCode = $frequencies[array_rand($frequencies)];
        $duration = rand(1, 30);

        $input = "{$doseValue}ml {$frequencyCode} x {$duration} days";
        $result = $this->parser->parse($input, $drug);

        expect($result->isValid)->toBeTrue();
        expect($result->quantityToDispense)->toBeInt();
        expect($result->quantityToDispense)->toBeGreaterThan(0);
    }
});

/**
 * Feature: prescription-quantity-testing, Property 3: Volume-based bottle calculation
 *
 * Property test: Larger doses or longer durations should result in more bottles.
 *
 * **Validates: Requirements 2.1, 2.2, 2.5**
 */
it('increases bottle count with larger doses or longer durations', function () {
    $drug = new Drug;
    $drug->form = 'syrup';
    $drug->unit_type = 'bottle';
    $drug->bottle_size = 100;

    // Test that doubling the dose doubles (or increases) the bottle count
    $result1 = $this->parser->parse('5ml OD x 10 days', $drug);
    $result2 = $this->parser->parse('10ml OD x 10 days', $drug);

    expect($result2->quantityToDispense)->toBeGreaterThanOrEqual($result1->quantityToDispense);

    // Test that doubling the duration doubles (or increases) the bottle count
    $result3 = $this->parser->parse('5ml OD x 10 days', $drug);
    $result4 = $this->parser->parse('5ml OD x 20 days', $drug);

    expect($result4->quantityToDispense)->toBeGreaterThanOrEqual($result3->quantityToDispense);

    // Test that higher frequency increases bottle count
    $result5 = $this->parser->parse('5ml OD x 10 days', $drug);  // 50ml total
    $result6 = $this->parser->parse('5ml QDS x 10 days', $drug); // 200ml total

    expect($result6->quantityToDispense)->toBeGreaterThan($result5->quantityToDispense);
});

/**
 * Feature: prescription-quantity-testing, Property 4: Interval-based patch calculation
 *
 * For any patch drug with change interval I days and duration D days,
 * the quantity should equal ceil(D / I) patches.
 *
 * **Validates: Requirements 3.1, 3.4**
 */
it('calculates patch quantity as ceil(duration / change_interval)', function () {
    // Run 100+ iterations with random inputs
    for ($i = 0; $i < 100; $i++) {
        // Create a patch drug
        $drug = new Drug;
        $drug->form = 'patch';
        $drug->unit_type = 'piece';
        $drug->bottle_size = null;

        // Generate random change interval (1-7 days)
        $changeInterval = rand(1, 7);

        // Generate random duration (1-30 days)
        $duration = rand(1, 30);

        // Build input string for patch prescription
        // Format: "change every N days x D days" or "every N days x D days"
        $input = "change every {$changeInterval} days x {$duration} days";

        // Parse the prescription
        $result = $this->parser->parse($input, $drug);

        // Calculate expected quantity: ceil(duration / interval)
        $expectedQuantity = (int) ceil($duration / $changeInterval);

        // Assert the result
        expect($result->isValid)->toBeTrue(
            "Failed to parse: '{$input}' for patch drug"
        );
        expect($result->quantityToDispense)->toBe(
            $expectedQuantity,
            "Quantity mismatch for '{$input}': expected {$expectedQuantity} patches (ceil({$duration}/{$changeInterval})), got {$result->quantityToDispense}"
        );
    }
});

/**
 * Feature: prescription-quantity-testing, Property 4: Interval-based patch calculation
 *
 * Test specific patch scenarios from requirements.
 *
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
 */
it('calculates correct patch count for specific scenarios', function (int $changeInterval, int $duration, int $expectedPatches) {
    $drug = new Drug;
    $drug->form = 'patch';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $input = "change every {$changeInterval} days x {$duration} days";
    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe($expectedPatches);
})->with([
    'change every 3 days for 30 days → 10 patches' => [3, 30, 10],
    'change weekly (7 days) for 28 days → 4 patches' => [7, 28, 4],
    'change daily for 7 days → 7 patches' => [1, 7, 7],
    'change every 2 days for 7 days → 4 patches (ceiling)' => [2, 7, 4],
    'change every 3 days for 10 days → 4 patches (ceiling)' => [3, 10, 4],
]);

/**
 * Feature: prescription-quantity-testing, Property 4: Interval-based patch calculation
 *
 * Property test: Patch quantity should always be a positive integer (ceiling applied).
 *
 * **Validates: Requirements 3.1, 3.4**
 */
it('always returns positive integer quantities for patch drugs', function () {
    $drug = new Drug;
    $drug->form = 'patch';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    // Run 50 iterations with random inputs
    for ($i = 0; $i < 50; $i++) {
        $changeInterval = rand(1, 7);
        $duration = rand(1, 30);

        $input = "change every {$changeInterval} days x {$duration} days";
        $result = $this->parser->parse($input, $drug);

        expect($result->isValid)->toBeTrue();
        expect($result->quantityToDispense)->toBeInt();
        expect($result->quantityToDispense)->toBeGreaterThan(0);
    }
});

/**
 * Feature: prescription-quantity-testing, Property 4: Interval-based patch calculation
 *
 * Property test: Longer change intervals should result in fewer patches needed.
 *
 * **Validates: Requirements 3.1, 3.4**
 */
it('decreases patch count with longer change intervals', function () {
    $drug = new Drug;
    $drug->form = 'patch';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    // Fixed duration of 30 days
    $duration = 30;

    // Test that longer intervals result in fewer patches
    $result1 = $this->parser->parse("change every 1 days x {$duration} days", $drug);  // 30 patches
    $result2 = $this->parser->parse("change every 3 days x {$duration} days", $drug);  // 10 patches
    $result3 = $this->parser->parse("change every 7 days x {$duration} days", $drug);  // 5 patches (ceil(30/7))

    expect($result1->quantityToDispense)->toBeGreaterThan($result2->quantityToDispense);
    expect($result2->quantityToDispense)->toBeGreaterThan($result3->quantityToDispense);
});

/**
 * Feature: prescription-quantity-testing, Property 5: Fixed-unit drug defaults
 *
 * For any fixed-unit drug (cream, ointment, gel, drops, inhaler, combination pack),
 * the default quantity should be 1 unit regardless of frequency and duration.
 *
 * **Validates: Requirements 4.1-4.5**
 */
it('defaults fixed-unit drugs to quantity of 1 regardless of frequency and duration', function () {
    // Run 100+ iterations with random inputs
    for ($i = 0; $i < 100; $i++) {
        // Generate random fixed-unit drug form
        $fixedUnitForms = FIXED_UNIT_FORMS;
        $form = $fixedUnitForms[array_rand($fixedUnitForms)];

        // Create drug with the selected form
        $drug = new Drug;
        $drug->form = $form;
        $drug->unit_type = $form === 'cream' ? 'tube' : ($form === 'inhaler' ? 'device' : ($form === 'combination_pack' ? 'pack' : 'bottle'));
        $drug->bottle_size = null;

        // Generate random dose (1-10)
        $dose = rand(1, 10);

        // Generate random frequency
        $frequencies = getValidFrequencyCodes();
        $frequencyCode = $frequencies[array_rand($frequencies)];

        // Generate random duration (1-30 days)
        $duration = rand(1, 30);

        // Build input string
        $input = "{$dose} {$frequencyCode} x {$duration} days";

        // Parse the prescription
        $result = $this->parser->parse($input, $drug);

        // Fixed-unit drugs should always default to 1
        expect($result->isValid)->toBeTrue(
            "Failed to parse: '{$input}' for {$form} drug"
        );
        expect($result->quantityToDispense)->toBe(
            1,
            "Fixed-unit drug ({$form}) should default to 1, but got {$result->quantityToDispense} for '{$input}'"
        );
    }
});

/**
 * Feature: prescription-quantity-testing, Property 5: Fixed-unit drug defaults
 *
 * Additional test: Verify each fixed-unit drug form individually
 * to ensure the form is correctly identified as fixed-unit.
 *
 * **Validates: Requirements 4.1-4.5**
 */
it('correctly identifies all fixed-unit drug forms and defaults to 1', function (string $form) {
    $drug = new Drug;
    $drug->form = $form;
    $drug->unit_type = $form === 'cream' ? 'tube' : ($form === 'inhaler' ? 'device' : ($form === 'combination_pack' ? 'pack' : 'bottle'));
    $drug->bottle_size = null;

    // Use a prescription that would normally calculate to many units: 2 QDS x 30 days
    // For piece-based: 2 × 4 × 30 = 240
    // For fixed-unit: should be 1
    $input = '2 QDS x 30 days';
    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe(1);
})->with(FIXED_UNIT_FORMS);

/**
 * Feature: prescription-quantity-testing, Property 6: Context-aware drops interpretation
 *
 * For any drops drug with input "N F x D days", the system should interpret N
 * as drops per application (not bottles) and dispense 1 bottle.
 *
 * **Validates: Requirements 4.8**
 */
it('interprets drops drug input as drops per application and dispenses 1 bottle', function () {
    // Run 100+ iterations with random inputs
    for ($i = 0; $i < 100; $i++) {
        // Create a drops drug
        $drug = new Drug;
        $drug->form = 'drops';
        $drug->unit_type = 'bottle';
        $drug->bottle_size = null;

        // Generate random number of drops per application (1-5)
        $dropsPerApplication = rand(1, 5);

        // Generate random frequency
        $frequencies = getValidFrequencyCodes();
        $frequencyCode = $frequencies[array_rand($frequencies)];

        // Generate random duration (1-30 days)
        $duration = rand(1, 30);

        // Build input string - the number represents drops per application, not bottles
        $input = "{$dropsPerApplication} {$frequencyCode} x {$duration} days";

        // Parse the prescription
        $result = $this->parser->parse($input, $drug);

        // Drops should always dispense 1 bottle (standard bottles contain sufficient drops)
        expect($result->isValid)->toBeTrue(
            "Failed to parse: '{$input}' for drops drug"
        );
        expect($result->quantityToDispense)->toBe(
            1,
            "Drops drug should dispense 1 bottle, but got {$result->quantityToDispense} for '{$input}'"
        );
    }
});

/**
 * Feature: prescription-quantity-testing, Property 6: Context-aware drops interpretation
 *
 * Specific test case from requirements: "2 QDS x 7 days" with drops drug returns 1 bottle.
 *
 * **Validates: Requirements 4.8**
 */
it('returns 1 bottle for drops drug with "2 QDS x 7 days" input', function () {
    $drug = new Drug;
    $drug->form = 'drops';
    $drug->unit_type = 'bottle';
    $drug->bottle_size = null;

    // This is the specific example from requirements
    $input = '2 QDS x 7 days';
    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe(1);
});

/**
 * Feature: prescription-quantity-testing, Property 5: Fixed-unit drug defaults
 *
 * Property test: Fixed-unit quantity should always be exactly 1 (not more, not less).
 *
 * **Validates: Requirements 4.1-4.5**
 */
it('always returns exactly 1 for fixed-unit drugs regardless of input variations', function () {
    // Test various input patterns that might affect quantity
    $testCases = [
        '1 OD x 1 days',      // Minimal
        '10 QDS x 30 days',   // Maximum typical
        '0.5 BD x 14 days',   // Decimal dose
        '3 TDS x 7 days',     // Common pattern
    ];

    foreach (FIXED_UNIT_FORMS as $form) {
        $drug = new Drug;
        $drug->form = $form;
        $drug->unit_type = $form === 'cream' ? 'tube' : ($form === 'inhaler' ? 'device' : ($form === 'combination_pack' ? 'pack' : 'bottle'));
        $drug->bottle_size = null;

        foreach ($testCases as $input) {
            $result = $this->parser->parse($input, $drug);

            expect($result->isValid)->toBeTrue()
                ->and($result->quantityToDispense)->toBe(
                    1,
                    "Fixed-unit drug ({$form}) should be 1 for '{$input}', got {$result->quantityToDispense}"
                );
        }
    }
});

/**
 * Feature: prescription-quantity-testing, Property 7: Split dose quantity calculation
 *
 * For any split dose pattern A-B-C with duration D days,
 * the quantity should equal (A + B + C) × D.
 *
 * **Validates: Requirements 5.1**
 */
it('calculates split dose quantity as (A + B + C) × duration', function () {
    // Run 100+ iterations with random inputs
    for ($i = 0; $i < 100; $i++) {
        // Create a piece-based drug (tablets)
        $drug = new Drug;
        $drug->form = 'tablet';
        $drug->unit_type = 'piece';
        $drug->bottle_size = null;

        // Generate random split dose pattern A-B-C (0-5 for each)
        $morning = rand(0, 5);
        $noon = rand(0, 5);
        $evening = rand(0, 5);

        // Ensure at least one dose is non-zero
        if ($morning === 0 && $noon === 0 && $evening === 0) {
            $morning = 1;
        }

        // Generate random duration (1-30 days)
        $duration = rand(1, 30);

        // Build input string
        $input = "{$morning}-{$noon}-{$evening} x {$duration} days";

        // Parse the prescription
        $result = $this->parser->parse($input, $drug);

        // Calculate expected quantity: (A + B + C) × duration
        $dailyTotal = $morning + $noon + $evening;
        $expectedQuantity = (int) ceil($dailyTotal * $duration);

        // Assert the result
        expect($result->isValid)->toBeTrue(
            "Failed to parse: '{$input}'"
        );
        expect($result->quantityToDispense)->toBe(
            $expectedQuantity,
            "Quantity mismatch for '{$input}': expected {$expectedQuantity} (({$morning}+{$noon}+{$evening}) × {$duration}), got {$result->quantityToDispense}"
        );
    }
});

/**
 * Feature: prescription-quantity-testing, Property 7: Split dose quantity calculation
 *
 * Test specific split dose scenarios from requirements.
 *
 * **Validates: Requirements 5.2, 5.3, 5.4**
 */
it('calculates correct quantity for specific split dose scenarios', function (string $pattern, int $duration, int $expectedQuantity) {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $input = "{$pattern} x {$duration} days";
    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe($expectedQuantity);
})->with([
    '1-0-1 for 30 days → 60' => ['1-0-1', 30, 60],
    '2-1-1 for 7 days → 28' => ['2-1-1', 7, 28],
    '1-1-1 for 30 days → 90' => ['1-1-1', 30, 90],
    '2-0-2 for 14 days → 56' => ['2-0-2', 14, 56],
    '0-1-1 for 10 days → 20' => ['0-1-1', 10, 20],
]);

/**
 * Feature: prescription-quantity-testing, Property 7: Split dose quantity calculation
 *
 * Property test: Split dose with decimal values should round up.
 *
 * **Validates: Requirements 5.1**
 */
it('handles decimal values in split dose patterns', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    // Test cases with decimal doses
    $testCases = [
        ['0.5-0-0.5 x 10 days', 10],  // (0.5 + 0 + 0.5) × 10 = 10
        ['1.5-0-1.5 x 7 days', 21],   // (1.5 + 0 + 1.5) × 7 = 21
        ['0.5-0.5-0.5 x 14 days', 21], // (0.5 + 0.5 + 0.5) × 14 = 21
    ];

    foreach ($testCases as [$input, $expectedQuantity]) {
        $result = $this->parser->parse($input, $drug);

        expect($result->isValid)->toBeTrue()
            ->and($result->quantityToDispense)->toBe($expectedQuantity);
    }
});

/**
 * Feature: prescription-quantity-testing, Property 8: STAT dose quantity
 *
 * For any STAT prescription with dose N, the quantity should equal N
 * (no duration multiplication).
 *
 * **Validates: Requirements 6.1, 6.2**
 */
it('calculates STAT dose quantity as dose only (no duration multiplication)', function () {
    // Run 100+ iterations with random inputs
    for ($i = 0; $i < 100; $i++) {
        // Create a piece-based drug
        $drug = new Drug;
        $drug->form = 'tablet';
        $drug->unit_type = 'piece';
        $drug->bottle_size = null;

        // Generate random dose (1-10)
        $dose = rand(1, 10);

        // Build input string - STAT means single immediate dose
        $input = "{$dose} STAT";

        // Parse the prescription
        $result = $this->parser->parse($input, $drug);

        // STAT quantity should equal dose (no multiplication)
        $expectedQuantity = $dose;

        // Assert the result
        expect($result->isValid)->toBeTrue(
            "Failed to parse: '{$input}'"
        );
        expect($result->quantityToDispense)->toBe(
            $expectedQuantity,
            "STAT quantity mismatch for '{$input}': expected {$expectedQuantity}, got {$result->quantityToDispense}"
        );
        expect($result->scheduleType)->toBe('stat');
        expect($result->frequencyCode)->toBe('STAT');
    }
});

/**
 * Feature: prescription-quantity-testing, Property 8: STAT dose quantity
 *
 * Test specific STAT dose scenarios.
 *
 * **Validates: Requirements 6.1, 6.2, 6.3**
 */
it('calculates correct quantity for specific STAT scenarios', function (string $input, int $expectedQuantity) {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe($expectedQuantity)
        ->and($result->scheduleType)->toBe('stat');
})->with([
    '1 STAT → 1' => ['1 STAT', 1],
    '2 STAT → 2' => ['2 STAT', 2],
    '2 tabs STAT → 2' => ['2 tabs STAT', 2],
    'STAT (no dose) → 1' => ['STAT', 1],
    '5 STAT → 5' => ['5 STAT', 5],
]);

/**
 * Feature: prescription-quantity-testing, Property 8: STAT dose quantity
 *
 * Property test: STAT does not require duration.
 *
 * **Validates: Requirements 6.2**
 */
it('does not require duration for STAT prescriptions', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    // STAT should be valid without any duration specification
    $result = $this->parser->parse('2 STAT', $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->duration)->toBe('Single dose')
        ->and($result->durationDays)->toBe(1);
});

/**
 * Feature: prescription-quantity-testing, Property 8: STAT dose quantity
 *
 * Property test: STAT is case-insensitive.
 *
 * **Validates: Requirements 6.1**
 */
it('parses STAT case-insensitively', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $variations = ['STAT', 'stat', 'Stat', 'StAt'];

    foreach ($variations as $stat) {
        $result = $this->parser->parse("2 {$stat}", $drug);

        expect($result->isValid)->toBeTrue()
            ->and($result->quantityToDispense)->toBe(2)
            ->and($result->scheduleType)->toBe('stat');
    }
});

/**
 * Feature: prescription-quantity-testing, Property 9: PRN with max daily calculation
 *
 * For any PRN prescription with max daily M and duration D days,
 * the quantity should equal M × D.
 *
 * **Validates: Requirements 7.1**
 */
it('calculates PRN with max daily quantity as max_daily × duration', function () {
    // Run 100+ iterations with random inputs
    for ($i = 0; $i < 100; $i++) {
        // Create a piece-based drug
        $drug = new Drug;
        $drug->form = 'tablet';
        $drug->unit_type = 'piece';
        $drug->bottle_size = null;

        // Generate random max daily (1-12)
        $maxDaily = rand(1, 12);

        // Generate random duration (1-30 days)
        $duration = rand(1, 30);

        // Build input string - PRN with max daily and duration
        $input = "PRN max {$maxDaily}/24h x {$duration} days";

        // Parse the prescription
        $result = $this->parser->parse($input, $drug);

        // PRN with max daily: quantity = max_daily × duration
        $expectedQuantity = $maxDaily * $duration;

        // Assert the result
        expect($result->isValid)->toBeTrue(
            "Failed to parse: '{$input}'"
        );
        expect($result->quantityToDispense)->toBe(
            $expectedQuantity,
            "PRN quantity mismatch for '{$input}': expected {$expectedQuantity} ({$maxDaily} × {$duration}), got {$result->quantityToDispense}"
        );
        expect($result->scheduleType)->toBe('prn');
        expect($result->frequencyCode)->toBe('PRN');
    }
});

/**
 * Feature: prescription-quantity-testing, Property 9: PRN with max daily calculation
 *
 * Test specific PRN with max daily scenarios from requirements.
 *
 * **Validates: Requirements 7.1, 7.3**
 */
it('calculates correct quantity for specific PRN with max daily scenarios', function (string $input, int $expectedQuantity) {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe($expectedQuantity)
        ->and($result->scheduleType)->toBe('prn');
})->with([
    'PRN max 8/24h x 7 days → 56' => ['PRN max 8/24h x 7 days', 56],
    'PRN max 4/24h x 10 days → 40' => ['PRN max 4/24h x 10 days', 40],
    '2 PRN max 6/24h x 5 days → 30' => ['2 PRN max 6/24h x 5 days', 30],
    '1 tab PRN max 3/24h x 14 days → 42' => ['1 tab PRN max 3/24h x 14 days', 42],
]);

/**
 * Feature: prescription-quantity-testing, Property 9: PRN with max daily calculation
 *
 * Property test: Simple PRN without max daily returns dose quantity.
 *
 * **Validates: Requirements 7.2**
 */
it('returns dose quantity for simple PRN without max daily', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    // Simple PRN should return the dose as quantity
    $testCases = [
        ['PRN', 1],
        ['2 PRN', 2],
        ['3 tabs PRN', 3],
    ];

    foreach ($testCases as [$input, $expectedQuantity]) {
        $result = $this->parser->parse($input, $drug);

        expect($result->isValid)->toBeTrue()
            ->and($result->quantityToDispense)->toBe($expectedQuantity)
            ->and($result->scheduleType)->toBe('prn');
    }
});

/**
 * Feature: prescription-quantity-testing, Property 9: PRN with max daily calculation
 *
 * Property test: PRN with max daily stores schedule pattern.
 *
 * **Validates: Requirements 7.1**
 */
it('stores schedule pattern for PRN with max daily', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $result = $this->parser->parse('PRN max 8/24h x 7 days', $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->schedulePattern)->toBeArray()
        ->and($result->schedulePattern['type'])->toBe('prn')
        ->and($result->schedulePattern['max_daily'])->toBe(8)
        ->and($result->schedulePattern['duration_days'])->toBe(7);
});

/**
 * Feature: prescription-quantity-testing, Property 10: Taper pattern quantity calculation
 *
 * For any taper pattern [D1, D2, ..., Dn], the quantity should equal
 * sum(D1 + D2 + ... + Dn).
 *
 * **Validates: Requirements 8.1**
 */
it('calculates taper pattern quantity as sum of all doses', function () {
    // Run 100+ iterations with random inputs
    for ($i = 0; $i < 100; $i++) {
        // Create a piece-based drug
        $drug = new Drug;
        $drug->form = 'tablet';
        $drug->unit_type = 'piece';
        $drug->bottle_size = null;

        // Generate random taper pattern (2-7 days, decreasing doses)
        $numDays = rand(2, 7);
        $startDose = rand(3, 10);

        // Build decreasing doses
        $doses = [];
        $currentDose = $startDose;
        for ($day = 0; $day < $numDays; $day++) {
            $doses[] = max(1, $currentDose - $day);
        }

        // Build input string
        $input = implode('-', $doses).' taper';

        // Parse the prescription
        $result = $this->parser->parse($input, $drug);

        // Taper quantity = sum of all doses
        $expectedQuantity = (int) ceil(array_sum($doses));

        // Assert the result
        expect($result->isValid)->toBeTrue(
            "Failed to parse: '{$input}'"
        );
        expect($result->quantityToDispense)->toBe(
            $expectedQuantity,
            "Taper quantity mismatch for '{$input}': expected {$expectedQuantity} (sum of ".implode('+', $doses)."), got {$result->quantityToDispense}"
        );
        expect($result->scheduleType)->toBe('taper');
        expect($result->frequencyCode)->toBe('TAPER');
    }
});

/**
 * Feature: prescription-quantity-testing, Property 10: Taper pattern quantity calculation
 *
 * Test specific taper scenarios from requirements.
 *
 * **Validates: Requirements 8.1, 8.2, 8.3**
 */
it('calculates correct quantity for specific taper scenarios', function (string $input, int $expectedQuantity) {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe($expectedQuantity)
        ->and($result->scheduleType)->toBe('taper');
})->with([
    '4-3-2-1 taper → 10' => ['4-3-2-1 taper', 10],
    '5-4-3-2-1 taper → 15' => ['5-4-3-2-1 taper', 15],
    '6-5-4-3-2-1 taper → 21' => ['6-5-4-3-2-1 taper', 21],
    '3-2-1 taper → 6' => ['3-2-1 taper', 6],
    '8-6-4-2 taper → 20' => ['8-6-4-2 taper', 20],
]);

/**
 * Feature: prescription-quantity-testing, Property 10: Taper pattern quantity calculation
 *
 * Property test: Taper pattern stores schedule with doses array.
 *
 * **Validates: Requirements 8.1**
 */
it('stores schedule pattern for taper with doses array', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $result = $this->parser->parse('4-3-2-1 taper', $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->schedulePattern)->toBeArray()
        ->and($result->schedulePattern['type'])->toBe('taper')
        ->and($result->schedulePattern['doses'])->toBe([4.0, 3.0, 2.0, 1.0])
        ->and($result->schedulePattern['duration_days'])->toBe(4);
});

/**
 * Feature: prescription-quantity-testing, Property 10: Taper pattern quantity calculation
 *
 * Property test: Taper duration equals number of doses.
 *
 * **Validates: Requirements 8.1**
 */
it('sets taper duration to number of doses', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $testCases = [
        ['4-3-2-1 taper', 4],
        ['5-4-3-2-1 taper', 5],
        ['3-2-1 taper', 3],
        ['6-5-4-3-2-1 taper', 6],
    ];

    foreach ($testCases as [$input, $expectedDays]) {
        $result = $this->parser->parse($input, $drug);

        expect($result->isValid)->toBeTrue()
            ->and($result->durationDays)->toBe($expectedDays);
    }
});

/**
 * Feature: prescription-quantity-testing, Property 10: Taper pattern quantity calculation
 *
 * Property test: Taper with decimal doses rounds up total.
 *
 * **Validates: Requirements 8.1**
 */
it('handles decimal doses in taper patterns', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    // 2.5 + 2 + 1.5 + 1 = 7
    $result = $this->parser->parse('2.5-2-1.5-1 taper', $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe(7);
});

/**
 * Feature: prescription-quantity-testing, Property 11: Custom interval quantity calculation
 *
 * For any custom interval schedule with dose N per interval and M intervals,
 * the quantity should equal N × M.
 *
 * **Validates: Requirements 9.1**
 */
it('calculates custom interval quantity as dose × number of intervals', function () {
    // Run 100+ iterations with random inputs
    for ($i = 0; $i < 100; $i++) {
        // Create a piece-based drug
        $drug = new Drug;
        $drug->form = 'tablet';
        $drug->unit_type = 'piece';
        $drug->bottle_size = null;

        // Generate random dose per interval (1-6)
        $dosePerInterval = rand(1, 6);

        // Generate random number of intervals (2-8)
        $numIntervals = rand(2, 8);

        // Generate random interval hours (starting from 0)
        $intervals = [0];
        $currentHour = 0;
        for ($j = 1; $j < $numIntervals; $j++) {
            $currentHour += rand(4, 24); // Add 4-24 hours between intervals
            $intervals[] = $currentHour;
        }

        // Build input string
        $intervalsStr = implode('h,', $intervals).'h';
        $input = "{$dosePerInterval} tabs {$intervalsStr}";

        // Parse the prescription
        $result = $this->parser->parse($input, $drug);

        // Custom interval quantity = dose × number of intervals
        $expectedQuantity = (int) ceil($dosePerInterval * $numIntervals);

        // Assert the result
        expect($result->isValid)->toBeTrue(
            "Failed to parse: '{$input}'"
        );
        expect($result->quantityToDispense)->toBe(
            $expectedQuantity,
            "Custom interval quantity mismatch for '{$input}': expected {$expectedQuantity} ({$dosePerInterval} × {$numIntervals}), got {$result->quantityToDispense}"
        );
        expect($result->scheduleType)->toBe('custom_interval');
        expect($result->frequencyCode)->toBe('CUSTOM');
    }
});

/**
 * Feature: prescription-quantity-testing, Property 11: Custom interval quantity calculation
 *
 * Test specific custom interval scenarios from requirements.
 *
 * **Validates: Requirements 9.1, 9.2**
 */
it('calculates correct quantity for specific custom interval scenarios', function (string $input, int $expectedQuantity) {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe($expectedQuantity)
        ->and($result->scheduleType)->toBe('custom_interval');
})->with([
    '4 tabs 0h,8h,24h,36h,48h,60h → 24' => ['4 tabs 0h,8h,24h,36h,48h,60h', 24],
    '2 tabs 0h,12h,24h → 6' => ['2 tabs 0h,12h,24h', 6],
    '3 caps 0h,8h,16h,24h → 12' => ['3 caps 0h,8h,16h,24h', 12],
    '1 tab 0h,6h,12h,18h,24h → 5' => ['1 tab 0h,6h,12h,18h,24h', 5],
]);

/**
 * Feature: prescription-quantity-testing, Property 11: Custom interval quantity calculation
 *
 * Property test: Custom intervals with decimal doses rounds up.
 *
 * **Validates: Requirements 9.1**
 */
it('handles decimal doses in custom interval patterns', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    // 1.5 × 4 intervals = 6
    $result = $this->parser->parse('1.5 tabs 0h,8h,16h,24h', $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe(6);
});

/**
 * Feature: prescription-quantity-testing, Property 12: Custom interval pattern storage
 *
 * For any prescription with custom intervals, the schedule_pattern field
 * should contain the interval hours for MAR reference.
 *
 * **Validates: Requirements 9.3**
 */
it('stores interval hours in schedule_pattern for custom intervals', function () {
    // Run 100+ iterations with random inputs
    for ($i = 0; $i < 100; $i++) {
        // Create a piece-based drug
        $drug = new Drug;
        $drug->form = 'tablet';
        $drug->unit_type = 'piece';
        $drug->bottle_size = null;

        // Generate random dose per interval (1-6)
        $dosePerInterval = rand(1, 6);

        // Generate random number of intervals (2-8)
        $numIntervals = rand(2, 8);

        // Generate random interval hours (starting from 0)
        $intervals = [0];
        $currentHour = 0;
        for ($j = 1; $j < $numIntervals; $j++) {
            $currentHour += rand(4, 24); // Add 4-24 hours between intervals
            $intervals[] = $currentHour;
        }

        // Build input string
        $intervalsStr = implode('h,', $intervals).'h';
        $input = "{$dosePerInterval} tabs {$intervalsStr}";

        // Parse the prescription
        $result = $this->parser->parse($input, $drug);

        // Assert the schedule pattern contains interval hours
        expect($result->isValid)->toBeTrue(
            "Failed to parse: '{$input}'"
        );
        expect($result->schedulePattern)->toBeArray();
        expect($result->schedulePattern['type'])->toBe('custom_interval');
        expect($result->schedulePattern['intervals_hours'])->toBeArray();
        expect($result->schedulePattern['intervals_hours'])->toBe($intervals);
        expect($result->schedulePattern['dose_per_interval'])->toBe((float) $dosePerInterval);
        expect($result->schedulePattern['total_doses'])->toBe($numIntervals);
    }
});

/**
 * Feature: prescription-quantity-testing, Property 12: Custom interval pattern storage
 *
 * Test specific custom interval storage scenarios.
 *
 * **Validates: Requirements 9.3**
 */
it('stores correct schedule pattern for specific custom interval scenarios', function (string $input, array $expectedIntervals, float $expectedDose, int $expectedTotalDoses) {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $result = $this->parser->parse($input, $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->schedulePattern['type'])->toBe('custom_interval')
        ->and($result->schedulePattern['intervals_hours'])->toBe($expectedIntervals)
        ->and($result->schedulePattern['dose_per_interval'])->toBe($expectedDose)
        ->and($result->schedulePattern['total_doses'])->toBe($expectedTotalDoses);
})->with([
    'antimalarial 0h,8h,24h,36h,48h,60h' => ['4 tabs 0h,8h,24h,36h,48h,60h', [0, 8, 24, 36, 48, 60], 4.0, 6],
    'simple 0h,12h,24h' => ['2 tabs 0h,12h,24h', [0, 12, 24], 2.0, 3],
    'four times 0h,8h,16h,24h' => ['3 caps 0h,8h,16h,24h', [0, 8, 16, 24], 3.0, 4],
]);

/**
 * Feature: prescription-quantity-testing, Property 12: Custom interval pattern storage
 *
 * Property test: Schedule pattern can be used for MAR generation.
 *
 * **Validates: Requirements 9.3**
 */
it('provides schedule pattern usable for MAR generation', function () {
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'piece';
    $drug->bottle_size = null;

    $result = $this->parser->parse('4 tabs 0h,8h,24h,36h,48h,60h', $drug);

    // The schedule pattern should have all info needed for MAR
    expect($result->schedulePattern)->toHaveKeys([
        'type',
        'intervals_hours',
        'dose_per_interval',
        'total_doses',
    ]);

    // Verify we can calculate administration times from intervals
    $intervals = $result->schedulePattern['intervals_hours'];
    expect($intervals)->toBeArray();
    expect(count($intervals))->toBe(6);

    // First interval should always be 0 (immediate)
    expect($intervals[0])->toBe(0);

    // All intervals should be non-negative integers
    foreach ($intervals as $interval) {
        expect($interval)->toBeInt();
        expect($interval)->toBeGreaterThanOrEqual(0);
    }
});
