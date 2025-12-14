<?php

use App\Models\Drug;
use App\Services\Prescription\ParsedPrescriptionResult;
use App\Services\Prescription\PrescriptionParserService;

beforeEach(function () {
    $this->parser = new PrescriptionParserService;
});

/**
 * Feature: smart-prescription-input, Property 2: Frequency abbreviation mapping consistency
 * Validates: Requirements 3.5
 *
 * For any valid frequency abbreviation (OD, BD, TDS, QDS, Q6H, Q8H, Q12H),
 * parsing should produce a consistent frequency description and times-per-day value
 * that matches the medical standard.
 */
it('maps frequency abbreviations consistently', function (string $code, string $expectedDesc, int $expectedTimesPerDay) {
    $result = $this->parser->parseFrequency($code);

    expect($result)->not->toBeNull()
        ->and($result['description'])->toContain($expectedDesc)
        ->and($result['times_per_day'])->toBe($expectedTimesPerDay);
})->with([
    'OD - once daily' => ['OD', 'Once daily', 1],
    'BD - twice daily' => ['BD', 'Twice daily', 2],
    'BID - twice daily alias' => ['BID', 'Twice daily', 2],
    'TDS - three times daily' => ['TDS', 'Three times daily', 3],
    'TID - three times daily alias' => ['TID', 'Three times daily', 3],
    'QDS - four times daily' => ['QDS', 'Four times daily', 4],
    'QID - four times daily alias' => ['QID', 'Four times daily', 4],
    'Q6H - every 6 hours' => ['Q6H', 'Every 6 hours', 4],
    'Q8H - every 8 hours' => ['Q8H', 'Every 8 hours', 3],
    'Q12H - every 12 hours' => ['Q12H', 'Every 12 hours', 2],
]);

/**
 * Feature: smart-prescription-input, Property 2: Frequency abbreviation mapping consistency
 * Validates: Requirements 3.5
 *
 * Property test: For any valid frequency code, parsing is case-insensitive
 */
it('parses frequency abbreviations case-insensitively', function () {
    $codes = ['OD', 'BD', 'TDS', 'QDS', 'Q6H', 'Q8H', 'Q12H'];

    foreach ($codes as $code) {
        $upper = $this->parser->parseFrequency(strtoupper($code));
        $lower = $this->parser->parseFrequency(strtolower($code));
        $mixed = $this->parser->parseFrequency(ucfirst(strtolower($code)));

        expect($upper)->not->toBeNull()
            ->and($lower)->not->toBeNull()
            ->and($mixed)->not->toBeNull()
            ->and($upper['times_per_day'])->toBe($lower['times_per_day'])
            ->and($upper['times_per_day'])->toBe($mixed['times_per_day']);
    }
});

/**
 * Feature: smart-prescription-input, Property 3: Duration format parsing consistency
 * Validates: Requirements 3.6
 *
 * For any valid duration format ("x N days", "x N/7", "x N weeks"),
 * parsing should produce the correct number of days.
 */
it('parses duration formats correctly', function (string $input, int $expectedDays) {
    $result = $this->parser->parseDuration($input);

    expect($result)->not->toBeNull()
        ->and($result['days'])->toBe($expectedDays);
})->with([
    'x 5 days' => ['x 5 days', 5],
    'x 7 days' => ['x 7 days', 7],
    'x 30 days' => ['x 30 days', 30],
    '5 days without x' => ['5 days', 5],
    'x 7/7 notation' => ['x 7/7', 7],
    '14/7 notation' => ['14/7', 14],
    'x 1 week' => ['x 1 week', 7],
    'x 2 weeks' => ['x 2 weeks', 14],
    '4 weeks' => ['4 weeks', 28],
]);

/**
 * Feature: smart-prescription-input, Property 3: Duration format parsing consistency
 * Validates: Requirements 3.6
 *
 * Property test: For any positive integer N, "x N days" should parse to N days
 */
it('parses any positive day count correctly', function () {
    // Test with random day counts from 1 to 365
    for ($i = 0; $i < 100; $i++) {
        $days = rand(1, 365);
        $result = $this->parser->parseDuration("x {$days} days");

        expect($result)->not->toBeNull()
            ->and($result['days'])->toBe($days);
    }
});

/**
 * Feature: smart-prescription-input, Property 3: Duration format parsing consistency
 * Validates: Requirements 3.6
 *
 * Property test: For any positive integer N, "x N weeks" should parse to N*7 days
 */
it('parses weeks as 7 days each', function () {
    for ($i = 0; $i < 50; $i++) {
        $weeks = rand(1, 52);
        $result = $this->parser->parseDuration("x {$weeks} weeks");

        expect($result)->not->toBeNull()
            ->and($result['days'])->toBe($weeks * 7);
    }
});

/**
 * Feature: smart-prescription-input, Property 4: Split dose quantity calculation
 * Validates: Requirements 4.3
 *
 * For any split dose pattern (e.g., "a-b-c x N days"),
 * the total quantity should equal (a + b + c) × N.
 */
it('calculates split dose quantity correctly', function () {
    // Test with various split dose patterns
    for ($i = 0; $i < 100; $i++) {
        $morning = rand(0, 4);
        $noon = rand(0, 4);
        $evening = rand(0, 4);
        $days = rand(1, 30);

        // Skip if all zeros
        if ($morning + $noon + $evening === 0) {
            continue;
        }

        $input = "{$morning}-{$noon}-{$evening} x {$days} days";
        $result = $this->parser->parseSplitDose($input);

        $expectedQuantity = (int) ceil(($morning + $noon + $evening) * $days);

        expect($result)->not->toBeNull()
            ->and($result->isValid)->toBeTrue()
            ->and($result->quantityToDispense)->toBe($expectedQuantity)
            ->and($result->scheduleType)->toBe('split_dose');
    }
});

it('parses specific split dose patterns correctly', function (string $input, int $expectedQuantity, int $expectedDays) {
    $result = $this->parser->parseSplitDose($input);

    expect($result)->not->toBeNull()
        ->and($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe($expectedQuantity)
        ->and($result->durationDays)->toBe($expectedDays)
        ->and($result->scheduleType)->toBe('split_dose');
})->with([
    '1-0-1 x 30 days' => ['1-0-1 x 30 days', 60, 30],
    '2-1-1 x 7 days' => ['2-1-1 x 7 days', 28, 7],
    '1-1-1 x 5 days' => ['1-1-1 x 5 days', 15, 5],
    '2-0-2 x 14 days' => ['2-0-2 x 14 days', 56, 14],
]);

/**
 * Feature: smart-prescription-input, Property 5: Custom interval quantity calculation
 * Validates: Requirements 5.1, 5.3
 *
 * For any custom interval schedule with D dose per interval and N intervals,
 * the total quantity should equal D × N.
 */
it('calculates custom interval quantity correctly', function () {
    // Test antimalarial-style patterns
    $testCases = [
        ['4 tabs 0h,8h,24h,36h,48h,60h', 4, 6, 24],
        ['2 tabs 0h,12h,24h,36h', 2, 4, 8],
        ['3 tabs 0h,8h,24h', 3, 3, 9],
    ];

    foreach ($testCases as [$input, $dosePerInterval, $numIntervals, $expectedQuantity]) {
        $result = $this->parser->parseCustomIntervals($input);

        expect($result)->not->toBeNull()
            ->and($result->isValid)->toBeTrue()
            ->and($result->quantityToDispense)->toBe($expectedQuantity)
            ->and($result->scheduleType)->toBe('custom_interval')
            ->and($result->schedulePattern['total_doses'])->toBe($numIntervals);
    }
});

/**
 * Feature: smart-prescription-input, Property 6: Taper quantity calculation
 * Validates: Requirements 7.2
 *
 * For any taper pattern with doses [d1, d2, ..., dn],
 * the total quantity should equal sum(d1 + d2 + ... + dn).
 */
it('calculates taper quantity as sum of doses', function () {
    // Test with various taper patterns
    for ($i = 0; $i < 50; $i++) {
        $numDays = rand(2, 7);
        $startDose = rand(2, 8);
        $doses = [];

        // Generate decreasing doses
        for ($d = 0; $d < $numDays; $d++) {
            $doses[] = max(1, $startDose - $d);
        }

        $input = implode('-', $doses).' taper';
        $result = $this->parser->parseTaper($input);

        $expectedQuantity = (int) ceil(array_sum($doses));

        expect($result)->not->toBeNull()
            ->and($result->isValid)->toBeTrue()
            ->and($result->quantityToDispense)->toBe($expectedQuantity)
            ->and($result->scheduleType)->toBe('taper');
    }
});

it('parses specific taper patterns correctly', function (string $input, int $expectedQuantity, int $expectedDays) {
    $result = $this->parser->parseTaper($input);

    expect($result)->not->toBeNull()
        ->and($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe($expectedQuantity)
        ->and($result->durationDays)->toBe($expectedDays);
})->with([
    '4-3-2-1 taper' => ['4-3-2-1 taper', 10, 4],
    '6-5-4-3-2-1 taper' => ['6-5-4-3-2-1 taper', 21, 6],
    '3-2-1 taper' => ['3-2-1 taper', 6, 3],
]);

/**
 * Feature: smart-prescription-input, Property 7: Quantity calculation correctness by drug type
 * Validates: Requirements 8.1, 8.2, 8.3
 *
 * For piece-based drugs, quantity = dose × frequency_per_day × duration_days
 * For liquid drugs, bottles = ceil(ml × frequency_per_day × duration_days / bottle_size)
 */
it('calculates quantity correctly for tablet drugs', function () {
    // Create a mock Drug object for tablet
    $drug = new Drug;
    $drug->form = 'tablet';
    $drug->unit_type = 'tablet';
    $drug->bottle_size = null;

    // Test various combinations
    for ($i = 0; $i < 50; $i++) {
        $dose = rand(1, 4);
        $frequency = ['OD', 'BD', 'TDS', 'QDS'][rand(0, 3)];
        $days = rand(1, 30);

        $input = "{$dose} {$frequency} x {$days} days";
        $result = $this->parser->parse($input, $drug);

        $timesPerDay = match ($frequency) {
            'OD' => 1,
            'BD' => 2,
            'TDS' => 3,
            'QDS' => 4,
        };

        $expectedQuantity = (int) ceil($dose * $timesPerDay * $days);

        expect($result->isValid)->toBeTrue()
            ->and($result->quantityToDispense)->toBe($expectedQuantity);
    }
});

it('calculates bottles correctly for liquid drugs', function () {
    // Create a mock Drug object for syrup
    $drug = new Drug;
    $drug->form = 'syrup';
    $drug->unit_type = 'bottle';
    $drug->bottle_size = 100; // 100ml bottle

    // 5ml TDS x 7 days = 5 * 3 * 7 = 105ml = 2 bottles
    $result = $this->parser->parse('5ml TDS x 7 days', $drug);

    expect($result->isValid)->toBeTrue()
        ->and($result->quantityToDispense)->toBe(2); // ceil(105/100) = 2 bottles
});

/**
 * Feature: smart-prescription-input, Property 11: STAT and PRN don't require duration
 * Validates: Requirements 6.4
 *
 * For any input containing STAT or PRN, the parser should not require
 * a duration component and should produce a valid result.
 */
it('parses STAT without duration', function (string $input) {
    $result = $this->parser->parse($input);

    expect($result->isValid)->toBeTrue()
        ->and($result->scheduleType)->toBe('stat')
        ->and($result->frequencyCode)->toBe('STAT');
})->with([
    'STAT alone' => ['STAT'],
    '2 STAT' => ['2 STAT'],
    '2 tabs STAT' => ['2 tabs STAT'],
    '1 cap STAT' => ['1 cap STAT'],
]);

it('parses PRN without duration', function (string $input) {
    $result = $this->parser->parse($input);

    expect($result->isValid)->toBeTrue()
        ->and($result->scheduleType)->toBe('prn')
        ->and($result->frequencyCode)->toBe('PRN');
})->with([
    'PRN alone' => ['PRN'],
    '2 PRN' => ['2 PRN'],
    '2 tabs PRN' => ['2 tabs PRN'],
    '1 cap PRN' => ['1 cap PRN'],
]);

/**
 * Feature: smart-prescription-input, Property 10: Invalid input produces helpful feedback
 * Validates: Requirements 12.1, 12.2, 12.4
 *
 * For any input that cannot be fully parsed, the parser should return
 * isValid=false with at least one error message describing the issue.
 */
it('provides helpful feedback for invalid input', function (string $input) {
    $result = $this->parser->parse($input);

    expect($result->isValid)->toBeFalse()
        ->and($result->errors)->not->toBeEmpty();
})->with([
    'empty string' => [''],
    'random text' => ['take some medicine'],
    'missing frequency' => ['2 x 5 days'],
    'missing duration' => ['2 BD'],
    'invalid frequency' => ['2 XYZ x 5 days'],
]);

it('provides partial feedback when some components are recognized', function () {
    // Has dose and frequency but missing duration
    $result = $this->parser->parse('2 BD');

    expect($result->isValid)->toBeFalse()
        ->and($result->doseQuantity)->toBe('2')
        ->and($result->frequencyCode)->toBe('BD')
        ->and($result->errors)->not->toBeEmpty();
});

/**
 * Feature: smart-prescription-input, Property 12: Schedule pattern storage for MAR
 * Validates: Requirements 4.4, 5.3, 11.1
 *
 * For any prescription with a non-standard schedule (split dose, custom interval, or taper),
 * the schedule_pattern field should contain the pattern data for MAR reference.
 */
it('stores schedule pattern for split dose', function () {
    $result = $this->parser->parseSplitDose('1-0-1 x 7 days');

    expect($result)->not->toBeNull()
        ->and($result->schedulePattern)->not->toBeNull()
        ->and($result->schedulePattern['type'])->toBe('split_dose')
        ->and($result->schedulePattern['pattern'])->toBe([
            'morning' => 1.0,
            'noon' => 0.0,
            'evening' => 1.0,
        ])
        ->and($result->schedulePattern['daily_total'])->toBe(2.0);
});

it('stores schedule pattern for custom intervals', function () {
    $result = $this->parser->parseCustomIntervals('4 tabs 0h,8h,24h,36h,48h,60h');

    expect($result)->not->toBeNull()
        ->and($result->schedulePattern)->not->toBeNull()
        ->and($result->schedulePattern['type'])->toBe('custom_interval')
        ->and($result->schedulePattern['intervals_hours'])->toBe([0, 8, 24, 36, 48, 60])
        ->and($result->schedulePattern['dose_per_interval'])->toBe(4.0)
        ->and($result->schedulePattern['total_doses'])->toBe(6);
});

it('stores schedule pattern for taper', function () {
    $result = $this->parser->parseTaper('4-3-2-1 taper');

    expect($result)->not->toBeNull()
        ->and($result->schedulePattern)->not->toBeNull()
        ->and($result->schedulePattern['type'])->toBe('taper')
        ->and($result->schedulePattern['doses'])->toBe([4.0, 3.0, 2.0, 1.0])
        ->and($result->schedulePattern['duration_days'])->toBe(4);
});

it('stores schedule pattern for standard frequency', function () {
    $result = $this->parser->parse('2 BD x 5 days');

    expect($result->isValid)->toBeTrue()
        ->and($result->schedulePattern)->not->toBeNull()
        ->and($result->schedulePattern['type'])->toBe('standard')
        ->and($result->schedulePattern['frequency_code'])->toBe('BD')
        ->and($result->schedulePattern['times_per_day'])->toBe(2);
});

/**
 * Feature: smart-prescription-input, Property 9: Parsing round-trip consistency
 * Validates: Requirements 2.2, 9.1
 *
 * For any valid ParsedPrescriptionResult, formatting it back to a display string
 * and re-parsing should produce an equivalent result.
 */
it('maintains round-trip consistency for standard prescriptions', function () {
    $testCases = [
        '2 BD x 5 days',
        '1 TDS x 7 days',
        '3 OD x 30 days',
        '1 QDS x 14 days',
    ];

    foreach ($testCases as $input) {
        $result1 = $this->parser->parse($input);
        $formatted = $this->parser->format($result1);
        $result2 = $this->parser->parse($formatted);

        expect($result1->isValid)->toBeTrue()
            ->and($result2->isValid)->toBeTrue()
            ->and($result2->doseQuantity)->toBe($result1->doseQuantity)
            ->and($result2->frequencyCode)->toBe($result1->frequencyCode)
            ->and($result2->durationDays)->toBe($result1->durationDays)
            ->and($result2->quantityToDispense)->toBe($result1->quantityToDispense);
    }
});

// Standard parsing tests
it('parses standard prescription formats', function (string $input, string $expectedDose, string $expectedFreqCode, int $expectedDays, int $expectedQty) {
    $result = $this->parser->parse($input);

    expect($result->isValid)->toBeTrue()
        ->and($result->doseQuantity)->toBe($expectedDose)
        ->and($result->frequencyCode)->toBe($expectedFreqCode)
        ->and($result->durationDays)->toBe($expectedDays)
        ->and($result->quantityToDispense)->toBe($expectedQty);
})->with([
    '2 BD x 5 days' => ['2 BD x 5 days', '2', 'BD', 5, 20],
    '1 TDS x 7/7' => ['1 TDS x 7/7', '1', 'TDS', 7, 21],
    '1 OD x 30 days' => ['1 OD x 30 days', '1', 'OD', 30, 30],
    '5ml TDS x 5 days' => ['5ml TDS x 5 days', '5 ml', 'TDS', 5, 75],
    '2 tabs QDS x 7 days' => ['2 tabs QDS x 7 days', '2 tabs', 'QDS', 7, 56],
]);

// toSchedulePattern tests
it('converts result to schedule pattern', function () {
    $result = $this->parser->parse('2 BD x 5 days');
    $pattern = $this->parser->toSchedulePattern($result);

    expect($pattern)->not->toBeNull()
        ->and($pattern['type'])->toBe('standard')
        ->and($pattern['frequency_code'])->toBe('BD');
});

it('returns null schedule pattern for invalid result', function () {
    $result = $this->parser->parse('invalid input');
    $pattern = $this->parser->toSchedulePattern($result);

    expect($pattern)->toBeNull();
});

// format method tests
it('formats valid results correctly', function () {
    $result = $this->parser->parse('2 BD x 5 days');
    $formatted = $this->parser->format($result);

    expect($formatted)->toBe('2 BD x 5 days');
});

it('returns empty string for invalid results', function () {
    $result = $this->parser->parse('invalid');
    $formatted = $this->parser->format($result);

    expect($formatted)->toBe('');
});

// ParsedPrescriptionResult tests
it('creates valid result with factory method', function () {
    $result = ParsedPrescriptionResult::valid(
        doseQuantity: '2',
        frequency: 'Twice daily (BD)',
        frequencyCode: 'BD',
        duration: '5 days',
        durationDays: 5,
        quantityToDispense: 20,
    );

    expect($result->isValid)->toBeTrue()
        ->and($result->doseQuantity)->toBe('2')
        ->and($result->frequencyCode)->toBe('BD')
        ->and($result->hasErrors())->toBeFalse();
});

it('creates invalid result with factory method', function () {
    $result = ParsedPrescriptionResult::invalid(['Error 1', 'Error 2']);

    expect($result->isValid)->toBeFalse()
        ->and($result->errors)->toHaveCount(2)
        ->and($result->hasErrors())->toBeTrue();
});

it('converts result to array', function () {
    $result = ParsedPrescriptionResult::valid(
        doseQuantity: '2',
        frequency: 'Twice daily (BD)',
        frequencyCode: 'BD',
        duration: '5 days',
        durationDays: 5,
        quantityToDispense: 20,
    );

    $array = $result->toArray();

    expect($array)->toBeArray()
        ->and($array['isValid'])->toBeTrue()
        ->and($array['doseQuantity'])->toBe('2')
        ->and($array['frequencyCode'])->toBe('BD');
});

it('gets times per day from frequency code', function () {
    $result = ParsedPrescriptionResult::valid(
        doseQuantity: '2',
        frequency: 'Twice daily (BD)',
        frequencyCode: 'BD',
        duration: '5 days',
        durationDays: 5,
        quantityToDispense: 20,
    );

    expect($result->getTimesPerDay())->toBe(2);
});
