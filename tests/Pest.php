<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Test Constants for Prescription Quantity Testing
|--------------------------------------------------------------------------
|
| These constants define the frequency multipliers used in prescription
| quantity calculations. They match the medical standards for dosing
| frequencies and are used in property-based tests.
|
| Requirements: 1.2-1.8 from prescription-quantity-testing spec
|
*/

/**
 * Frequency multipliers for prescription quantity calculations.
 *
 * Maps frequency codes to their daily multiplier values:
 * - OD (Once Daily) = 1 dose per day
 * - BD (Twice Daily) = 2 doses per day
 * - TDS (Three Times Daily) = 3 doses per day
 * - QDS (Four Times Daily) = 4 doses per day
 * - Q6H (Every 6 Hours) = 4 doses per day
 * - Q8H (Every 8 Hours) = 3 doses per day
 * - Q12H (Every 12 Hours) = 2 doses per day
 */
const FREQUENCY_MULTIPLIERS = [
    'OD' => 1,
    'BD' => 2,
    'TDS' => 3,
    'QDS' => 4,
    'Q6H' => 4,
    'Q8H' => 3,
    'Q12H' => 2,
];

/**
 * Get the frequency multiplier for a given frequency code.
 *
 * @param  string  $frequencyCode  The frequency code (OD, BD, TDS, QDS, Q6H, Q8H, Q12H)
 * @return int The number of doses per day
 */
function getFrequencyMultiplier(string $frequencyCode): int
{
    return FREQUENCY_MULTIPLIERS[strtoupper($frequencyCode)] ?? 1;
}

/**
 * Get all valid frequency codes.
 *
 * @return array<string> List of valid frequency codes
 */
function getValidFrequencyCodes(): array
{
    return array_keys(FREQUENCY_MULTIPLIERS);
}

/**
 * Drug form categories for quantity calculation testing.
 */
const PIECE_BASED_FORMS = [
    'tablet',
    'capsule',
    'suppository',
    'sachet',
    'lozenge',
    'pessary',
    'enema',
    'injection',
    'iv_bag',
    'nebulizer',
];

const VOLUME_BASED_FORMS = [
    'syrup',
    'suspension',
];

const INTERVAL_BASED_FORMS = [
    'patch',
];

const FIXED_UNIT_FORMS = [
    'cream',
    'drops',
    'inhaler',
    'combination_pack',
];
