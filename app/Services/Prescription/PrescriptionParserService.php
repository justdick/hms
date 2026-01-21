<?php

namespace App\Services\Prescription;

use App\Models\Drug;

/**
 * Service for parsing prescription input strings into structured data.
 *
 * Supports formats:
 * - Standard: "2 BD x 5 days", "1 TDS x 7/7", "5ml OD x 30 days"
 * - Split dose: "1-0-1 x 30 days", "2-1-1 x 7 days"
 * - Custom intervals: "4 tabs 0h,8h,24h,36h,48h,60h"
 * - Taper: "4-3-2-1 taper"
 * - Special: "STAT", "2 PRN"
 */
class PrescriptionParserService
{
    /**
     * Frequency abbreviation mappings.
     *
     * @var array<string, array{description: string, times_per_day: int}>
     */
    private const FREQUENCY_MAP = [
        'OD' => ['description' => 'Once daily (OD)', 'times_per_day' => 1],
        'BD' => ['description' => 'Twice daily (BD)', 'times_per_day' => 2],
        'BID' => ['description' => 'Twice daily (BD)', 'times_per_day' => 2],
        'TDS' => ['description' => 'Three times daily (TDS)', 'times_per_day' => 3],
        'TID' => ['description' => 'Three times daily (TDS)', 'times_per_day' => 3],
        'QDS' => ['description' => 'Four times daily (QDS)', 'times_per_day' => 4],
        'QID' => ['description' => 'Four times daily (QDS)', 'times_per_day' => 4],
        'Q6H' => ['description' => 'Every 6 hours (Q6H)', 'times_per_day' => 4],
        'Q8H' => ['description' => 'Every 8 hours (Q8H)', 'times_per_day' => 3],
        'Q12H' => ['description' => 'Every 12 hours (Q12H)', 'times_per_day' => 2],
        '0-12-24H' => ['description' => 'At 0, 12, 24 hours (0-12-24H)', 'times_per_day' => null, 'total_doses' => 3, 'is_schedule' => true],
    ];

    /**
     * Frequency codes that are only valid for injectable drugs.
     */
    private const INJECTABLE_ONLY_FREQUENCIES = [
        '0-12-24H',
    ];

    /**
     * Drug forms considered as injectables.
     */
    private const INJECTABLE_FORMS = [
        'injection',
        'iv_bag',
    ];

    /**
     * Parse a prescription input string.
     */
    public function parse(string $input, ?Drug $drug = null): ParsedPrescriptionResult
    {
        $input = trim($input);

        if (empty($input)) {
            return ParsedPrescriptionResult::invalid(['Please enter a prescription']);
        }

        // For topical drugs (cream, ointment, gel, lotion), try simple quantity parsing first
        if ($drug && in_array(strtolower($drug->form ?? ''), self::TOPICAL_FORMS)) {
            if ($result = $this->parseTopical($input)) {
                return $result;
            }
        }

        // Try STAT first
        if ($result = $this->parseStat($input)) {
            if ($drug) {
                $result = $this->applyDrugQuantity($result, $drug);
            }

            return $result;
        }

        // Try patch/interval-based pattern
        if ($result = $this->parsePatch($input)) {
            if ($drug) {
                $result = $this->applyDrugQuantity($result, $drug);
            }

            return $result;
        }

        // Try PRN
        if ($result = $this->parsePrn($input)) {
            if ($drug) {
                $result = $this->applyDrugQuantity($result, $drug);
            }

            return $result;
        }

        // Try taper pattern
        if ($result = $this->parseTaper($input)) {
            if ($drug) {
                $result = $this->applyDrugQuantity($result, $drug);
            }

            return $result;
        }

        // Try injectable-only schedule (0-12-24H) - must check before custom intervals
        if ($result = $this->parseInjectableSchedule($input, $drug)) {
            if ($drug) {
                $result = $this->applyDrugQuantity($result, $drug);
            }

            return $result;
        }

        // Try custom intervals
        if ($result = $this->parseCustomIntervals($input)) {
            if ($drug) {
                $result = $this->applyDrugQuantity($result, $drug);
            }

            return $result;
        }

        // Try split dose pattern
        if ($result = $this->parseSplitDose($input)) {
            if ($drug) {
                $result = $this->applyDrugQuantity($result, $drug);
            }

            return $result;
        }

        // Try standard frequency pattern
        if ($result = $this->parseStandard($input)) {
            if ($drug) {
                $result = $this->applyDrugQuantity($result, $drug);
            }

            return $result;
        }

        // Try to provide partial feedback
        return $this->parsePartial($input);
    }

    /**
     * Parse frequency abbreviation from input.
     *
     * @return array{code: string, description: string, times_per_day: int}|null
     */
    public function parseFrequency(string $input): ?array
    {
        $input = strtoupper(trim($input));

        foreach (self::FREQUENCY_MAP as $code => $data) {
            if ($input === $code) {
                return [
                    'code' => $code === 'BID' ? 'BD' : ($code === 'TID' ? 'TDS' : ($code === 'QID' ? 'QDS' : $code)),
                    'description' => $data['description'],
                    'times_per_day' => $data['times_per_day'],
                ];
            }
        }

        return null;
    }

    /**
     * Parse duration from input.
     *
     * Supports flexible formats:
     * - Separators: x, *, /, for (e.g., "x 5 days", "* 5", "/ 5d", "for 5")
     * - Duration keywords: days, day, d, or just number (e.g., "5 days", "5d", "5")
     * - Week notation: N/7 (e.g., "7/7" = 7 days)
     * - Weeks: N weeks (e.g., "2 weeks" = 14 days)
     * - Trailing punctuation is ignored (e.g., "5 days." works)
     *
     * @return array{duration: string, days: int}|null
     */
    public function parseDuration(string $input): ?array
    {
        // Clean input: trim and remove trailing punctuation
        $input = trim($input);
        $input = preg_replace('/[.,;:!?]+$/', '', $input);
        $input = trim($input);

        // Flexible separator pattern: x, *, /, for (all optional)
        $separatorPattern = '(?:[x*\/]\s*|for\s+)?';

        // Pattern: "N/7" (N days in week notation) - check first to avoid conflict
        if (preg_match('/^' . $separatorPattern . '(\d+)\/7$/i', $input, $matches)) {
            $days = (int) $matches[1];

            return [
                'duration' => "{$days} days",
                'days' => $days,
            ];
        }

        // Pattern: "N weeks" or "N week"
        if (preg_match('/^' . $separatorPattern . '(\d+)\s*weeks?$/i', $input, $matches)) {
            $weeks = (int) $matches[1];
            $days = $weeks * 7;

            return [
                'duration' => "{$weeks} weeks",
                'days' => $days,
            ];
        }

        // Pattern: "N days", "N day", "N d", "Nd", or just "N"
        // Matches: "5 days", "5 day", "5d", "5 d", "5"
        if (preg_match('/^' . $separatorPattern . '(\d+)\s*(?:days?|d)?$/i', $input, $matches)) {
            $days = (int) $matches[1];

            return [
                'duration' => "{$days} days",
                'days' => $days,
            ];
        }

        return null;
    }

    /**
     * Parse standard frequency pattern (e.g., "2 BD x 5 days").
     *
     * Supports flexible separators: x, *, /, for
     * Examples: "2 BD x 5 days", "2 BD * 5", "2 BD / 5d", "2 BD for 5"
     */
    private function parseStandard(string $input): ?ParsedPrescriptionResult
    {
        // Pattern: dose frequency separator duration
        // Separators: x, *, /, for
        // Examples: "2 BD x 5 days", "1 TDS * 7", "5ml OD / 30", "2 BD for 5 days"
        $pattern = '/^(\d+(?:\.\d+)?)\s*(ml|mg|tabs?|capsules?|caps?)?\s*(OD|BD|BID|TDS|TID|QDS|QID|Q6H|Q8H|Q12H)\s*[x*\/]\s*(.+)$/i';
        $patternFor = '/^(\d+(?:\.\d+)?)\s*(ml|mg|tabs?|capsules?|caps?)?\s*(OD|BD|BID|TDS|TID|QDS|QID|Q6H|Q8H|Q12H)\s+for\s+(.+)$/i';

        $matches = null;
        if (!preg_match($pattern, $input, $matches) && !preg_match($patternFor, $input, $matches)) {
            return null;
        }

        $doseValue = $matches[1];
        $doseUnit = $matches[2] ?? '';
        $frequencyCode = strtoupper($matches[3]);
        $durationPart = $matches[4];

        $frequency = $this->parseFrequency($frequencyCode);
        if (!$frequency) {
            return null;
        }

        $duration = $this->parseDuration($durationPart);
        if (!$duration) {
            return ParsedPrescriptionResult::partial(
                errors: ["Could not parse duration: '{$durationPart}'. Try 'x 5 days', '5d', or just '5'"],
                doseQuantity: $doseValue . ($doseUnit ? " {$doseUnit}" : ''),
                frequency: $frequency['description'],
                frequencyCode: $frequency['code'],
            );
        }

        $doseQuantity = $doseValue . ($doseUnit ? " {$doseUnit}" : '');
        $totalQuantity = (int) ceil((float) $doseValue * $frequency['times_per_day'] * $duration['days']);

        $displayText = "{$doseQuantity} {$frequency['code']} x {$duration['duration']}";

        return ParsedPrescriptionResult::valid(
            doseQuantity: $doseQuantity,
            frequency: $frequency['description'],
            frequencyCode: $frequency['code'],
            duration: $duration['duration'],
            durationDays: $duration['days'],
            quantityToDispense: $totalQuantity,
            scheduleType: 'standard',
            schedulePattern: [
                'type' => 'standard',
                'frequency_code' => $frequency['code'],
                'times_per_day' => $frequency['times_per_day'],
            ],
            displayText: $displayText,
        );
    }

    /**
     * Parse split dose pattern (e.g., "1-0-1 x 30 days").
     *
     * Supports flexible separators: x, *, /, for
     * Examples: "1-0-1 x 30 days", "2-1-1 * 7", "1-0-1 / 30d", "1-0-1 for 30"
     */
    public function parseSplitDose(string $input): ?ParsedPrescriptionResult
    {
        // Pattern: morning-noon-evening separator duration
        // Separators: x, *, /, for
        $pattern = '/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)\s*[x*\/]\s*(.+)$/i';
        $patternFor = '/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)\s+for\s+(.+)$/i';

        $matches = null;
        if (!preg_match($pattern, $input, $matches) && !preg_match($patternFor, $input, $matches)) {
            return null;
        }

        $morning = (float) $matches[1];
        $noon = (float) $matches[2];
        $evening = (float) $matches[3];
        $durationPart = $matches[4];

        $duration = $this->parseDuration($durationPart);
        if (!$duration) {
            return ParsedPrescriptionResult::partial(
                errors: ["Could not parse duration: '{$durationPart}'. Try 'x 5 days', '5d', or just '5'"],
                doseQuantity: "{$morning}-{$noon}-{$evening}",
            );
        }

        $dailyTotal = $morning + $noon + $evening;
        $totalQuantity = (int) ceil($dailyTotal * $duration['days']);

        // Build frequency description
        $parts = [];
        if ($morning > 0) {
            $parts[] = "{$morning} morning";
        }
        if ($noon > 0) {
            $parts[] = "{$noon} noon";
        }
        if ($evening > 0) {
            $parts[] = "{$evening} evening";
        }
        $frequencyDesc = implode(', ', $parts) . " ({$dailyTotal}/day)";

        $displayText = "{$morning}-{$noon}-{$evening} x {$duration['duration']}";

        return ParsedPrescriptionResult::valid(
            doseQuantity: "{$morning}-{$noon}-{$evening}",
            frequency: $frequencyDesc,
            frequencyCode: 'SPLIT',
            duration: $duration['duration'],
            durationDays: $duration['days'],
            quantityToDispense: $totalQuantity,
            scheduleType: 'split_dose',
            schedulePattern: [
                'type' => 'split_dose',
                'pattern' => [
                    'morning' => $morning,
                    'noon' => $noon,
                    'evening' => $evening,
                ],
                'daily_total' => $dailyTotal,
            ],
            displayText: $displayText,
        );
    }

    /**
     * Parse custom interval schedule (e.g., "4 tabs 0h,8h,24h,36h,48h,60h").
     *
     * Supports formats:
     * - "4 tabs 0h,8h,24h,36h,48h,60h" - with 'h' suffix
     * - "4 tabs at 0,8,24,36,48,60" - with 'at' keyword
     * - "4mg 0,8,12,24" - dose with mg/ml followed by comma-separated hours
     * - "4mg 0,8,12,24 HRS" or "4mg 0,8,12,24HRS" - with optional HRS suffix
     */
    public function parseCustomIntervals(string $input): ?ParsedPrescriptionResult
    {
        // Pattern 1: "4 tabs 0h,8h,24h,36h,48h,60h" - hours with 'h' suffix
        $pattern1 = '/^(\d+(?:\.\d+)?)\s*(tabs?|capsules?|caps?|ml|mg)?\s*((?:\d+h?,?\s*)+)$/i';

        // Pattern 2: "4 tabs at 0,8,24,36,48,60" - with 'at' keyword
        $pattern2 = '/^(\d+(?:\.\d+)?)\s*(tabs?|capsules?|caps?|ml|mg)?\s*at\s*((?:\d+,?\s*)+)$/i';

        // Pattern 3: "4mg 0,8,12,24" or "4mg 0,8,12,24 HRS" - dose unit followed by comma-separated numbers
        // This pattern requires mg/ml unit to distinguish from other patterns
        $pattern3 = '/^(\d+(?:\.\d+)?)\s*(mg|ml)\s+((?:\d+,)+\d+)\s*(?:hrs?)?$/i';

        $doseValue = null;
        $doseUnit = '';
        $intervalsStr = '';

        if (preg_match($pattern1, $input, $matches)) {
            $doseValue = $matches[1];
            $doseUnit = $matches[2] ?? '';
            $intervalsStr = $matches[3];
        } elseif (preg_match($pattern2, $input, $matches)) {
            $doseValue = $matches[1];
            $doseUnit = $matches[2] ?? '';
            $intervalsStr = $matches[3];
        } elseif (preg_match($pattern3, $input, $matches)) {
            $doseValue = $matches[1];
            $doseUnit = $matches[2] ?? '';
            $intervalsStr = $matches[3];
        } else {
            return null;
        }

        // Parse intervals - remove 'h' suffix and split by comma/space
        $intervalsStr = preg_replace('/h/i', '', $intervalsStr);
        $intervals = array_map('intval', preg_split('/[,\s]+/', trim($intervalsStr)));
        $intervals = array_filter($intervals, fn($v) => $v >= 0 || $v === 0);
        $intervals = array_values($intervals);

        // Must have at least 2 intervals
        if (count($intervals) < 2) {
            return null;
        }

        // Include 0 if not present
        if ($intervals[0] !== 0) {
            array_unshift($intervals, 0);
        }

        $totalDoses = count($intervals);
        $totalQuantity = (int) ceil((float) $doseValue * $totalDoses);

        $doseQuantity = $doseValue . ($doseUnit ? " {$doseUnit}" : '');
        $intervalsDisplay = implode('h, ', $intervals) . 'h';
        $displayText = "{$doseQuantity} at {$intervalsDisplay}";

        return ParsedPrescriptionResult::valid(
            doseQuantity: $doseQuantity,
            frequency: "Custom intervals ({$totalDoses} doses)",
            frequencyCode: 'CUSTOM',
            duration: 'Custom schedule',
            durationDays: (int) ceil(max($intervals) / 24) + 1,
            quantityToDispense: $totalQuantity,
            scheduleType: 'custom_interval',
            schedulePattern: [
                'type' => 'custom_interval',
                'intervals_hours' => $intervals,
                'dose_per_interval' => (float) $doseValue,
                'total_doses' => $totalDoses,
            ],
            displayText: $displayText,
        );
    }

    /**
     * Parse taper pattern (e.g., "4-3-2-1 taper").
     */
    public function parseTaper(string $input): ?ParsedPrescriptionResult
    {
        // Pattern: "4-3-2-1 taper" or "4-3-2-1"
        $pattern = '/^((?:\d+(?:\.\d+)?-)+\d+(?:\.\d+)?)\s*(?:taper)?$/i';

        if (!preg_match($pattern, $input, $matches)) {
            return null;
        }

        $dosesStr = $matches[1];

        // Check if it looks like a taper (decreasing or has "taper" keyword)
        $hasTaperKeyword = stripos($input, 'taper') !== false;

        $doses = array_map('floatval', explode('-', $dosesStr));

        // Must have at least 2 doses
        if (count($doses) < 2) {
            return null;
        }

        // If no taper keyword, check if it's decreasing (to distinguish from split dose)
        if (!$hasTaperKeyword) {
            $isDecreasing = true;
            for ($i = 1; $i < count($doses); $i++) {
                if ($doses[$i] > $doses[$i - 1]) {
                    $isDecreasing = false;
                    break;
                }
            }
            // If not decreasing and exactly 3 doses, it's likely a split dose
            if (!$isDecreasing && count($doses) === 3) {
                return null;
            }
            // If not decreasing and no taper keyword, not a taper
            if (!$isDecreasing) {
                return null;
            }
        }

        $totalQuantity = (int) ceil(array_sum($doses));
        $durationDays = count($doses);

        // Build schedule description
        $scheduleDesc = [];
        foreach ($doses as $day => $dose) {
            $dayNum = $day + 1;
            $scheduleDesc[] = "Day {$dayNum}: {$dose}";
        }

        $displayText = implode('-', array_map(fn($d) => (string) $d, $doses)) . ' taper';

        return ParsedPrescriptionResult::valid(
            doseQuantity: implode('-', array_map(fn($d) => (string) $d, $doses)),
            frequency: 'Taper schedule',
            frequencyCode: 'TAPER',
            duration: "{$durationDays} days",
            durationDays: $durationDays,
            quantityToDispense: $totalQuantity,
            scheduleType: 'taper',
            schedulePattern: [
                'type' => 'taper',
                'doses' => $doses,
                'duration_days' => $durationDays,
            ],
            displayText: $displayText,
        );
    }

    /**
     * Parse injectable-only schedule (e.g., "2 0-12-24H" or "60mg 0-12-24 HRS").
     *
     * This schedule is specifically for injectable drugs (e.g., IV Artesunate for severe malaria).
     * It represents 3 doses at hours 0, 12, and 24.
     *
     * @param  string  $input  The prescription input string
     * @param  Drug|null  $drug  The drug being prescribed (required for validation)
     */
    private function parseInjectableSchedule(string $input, ?Drug $drug = null): ?ParsedPrescriptionResult
    {
        // Pattern: "2 0-12-24H", "60mg 0-12-24H", "2 0-12-24 HRS", "60mg 0-12-24 hrs"
        $pattern = '/^(\d+(?:\.\d+)?)\s*(mg|ml|units?)?\s*0-12-24\s*(?:H|HRS?)$/i';

        if (!preg_match($pattern, $input, $matches)) {
            return null;
        }

        $doseValue = $matches[1];
        $doseUnit = $matches[2] ?? '';

        // Validate: this frequency is only for injectable drugs
        if ($drug) {
            $drugForm = strtolower($drug->form ?? '');
            if (!in_array($drugForm, self::INJECTABLE_FORMS)) {
                return ParsedPrescriptionResult::invalid([
                    '0-12-24H schedule is only valid for injectable drugs.',
                    "This drug is a '{$drug->form}', not an injection.",
                ]);
            }
        }

        $doseQuantity = $doseValue . ($doseUnit ? " {$doseUnit}" : '');
        $totalDoses = 3; // Doses at 0h, 12h, 24h
        $totalQuantity = (int) ceil((float) $doseValue * $totalDoses);

        $displayText = "{$doseQuantity} at 0, 12, 24 hours";

        return ParsedPrescriptionResult::valid(
            doseQuantity: $doseQuantity,
            frequency: 'At 0, 12, 24 hours (0-12-24H)',
            frequencyCode: '0-12-24H',
            duration: '24 hours (3 doses)',
            durationDays: 1,
            quantityToDispense: $totalQuantity,
            scheduleType: 'injectable_interval',
            schedulePattern: [
                'type' => 'injectable_interval',
                'intervals_hours' => [0, 12, 24],
                'dose_per_interval' => (float) $doseValue,
                'total_doses' => $totalDoses,
            ],
            displayText: $displayText,
        );
    }

    /**
     * Parse STAT (single immediate dose).
     */
    private function parseStat(string $input): ?ParsedPrescriptionResult
    {
        // Pattern: "STAT" or "2 STAT" or "2 tabs STAT"
        $pattern = '/^(\d+(?:\.\d+)?)?\s*(tabs?|capsules?|caps?|ml)?\s*STAT$/i';

        if (!preg_match($pattern, $input, $matches)) {
            return null;
        }

        $doseValue = $matches[1] ?? '1';
        $doseUnit = $matches[2] ?? '';

        $doseQuantity = $doseValue . ($doseUnit ? " {$doseUnit}" : '');
        $totalQuantity = (int) ceil((float) $doseValue);

        return ParsedPrescriptionResult::stat($doseQuantity, $totalQuantity);
    }

    /**
     * Parse topical prescription (cream, ointment, gel, lotion).
     *
     * For topicals, we accept simple quantity inputs:
     * - "1" or "2" - number of tubes
     * - "1 tube" or "2 tubes" - explicit tube count
     *
     * The frequency/duration are set to "As directed" since topicals
     * are typically applied based on instructions rather than fixed schedules.
     */
    private function parseTopical(string $input): ?ParsedPrescriptionResult
    {
        // Pattern: just a number, or number with "tube(s)"
        // Examples: "1", "2", "1 tube", "2 tubes"
        $pattern = '/^(\d+)\s*(tubes?)?$/i';

        if (!preg_match($pattern, $input, $matches)) {
            return null;
        }

        $quantity = (int) $matches[1];
        if ($quantity < 1) {
            return null;
        }

        $unitLabel = $quantity === 1 ? 'tube' : 'tubes';
        $displayText = "{$quantity} {$unitLabel}";

        return ParsedPrescriptionResult::valid(
            doseQuantity: (string) $quantity,
            frequency: 'As directed',
            frequencyCode: 'TOPICAL',
            duration: 'As directed',
            durationDays: null,
            quantityToDispense: $quantity,
            scheduleType: 'topical',
            schedulePattern: [
                'type' => 'topical',
                'quantity' => $quantity,
            ],
            displayText: $displayText,
        );
    }

    /**
     * Parse patch/interval-based prescription (e.g., "change every 3 days x 30 days").
     *
     * For patches, quantity is calculated as ceil(duration / change_interval).
     */
    public function parsePatch(string $input): ?ParsedPrescriptionResult
    {
        // Pattern: "change every N days x D days" or "every N days x D days"
        // Examples: "change every 3 days x 30 days", "every 7 days x 28 days"
        $pattern = '/^(?:change\s+)?every\s+(\d+)\s*days?\s*x\s*(\d+)\s*days?$/i';

        if (!preg_match($pattern, $input, $matches)) {
            return null;
        }

        $changeInterval = (int) $matches[1];
        $durationDays = (int) $matches[2];

        // Validate change interval
        if ($changeInterval < 1) {
            return null;
        }

        // Calculate quantity: ceil(duration / interval)
        $totalQuantity = (int) ceil($durationDays / $changeInterval);

        $displayText = "Change every {$changeInterval} days x {$durationDays} days";

        return ParsedPrescriptionResult::valid(
            doseQuantity: '1',
            frequency: "Every {$changeInterval} days",
            frequencyCode: 'INTERVAL',
            duration: "{$durationDays} days",
            durationDays: $durationDays,
            quantityToDispense: $totalQuantity,
            scheduleType: 'interval',
            schedulePattern: [
                'type' => 'interval',
                'change_interval_days' => $changeInterval,
                'duration_days' => $durationDays,
            ],
            displayText: $displayText,
        );
    }

    /**
     * Parse PRN (as needed).
     *
     * Supports formats:
     * - Simple: "PRN", "2 PRN", "2 tabs PRN"
     * - With max daily and duration: "PRN max 8/24h x 7 days", "2 PRN max 6/24h x 5 days"
     */
    private function parsePrn(string $input): ?ParsedPrescriptionResult
    {
        // Pattern 1: PRN with max daily and duration
        // Examples: "PRN max 8/24h x 7 days", "2 PRN max 6/24h x 5 days", "2 tabs PRN max 4/24h x 10 days"
        $patternWithMax = '/^(\d+(?:\.\d+)?)?\s*(tabs?|capsules?|caps?|ml)?\s*PRN\s+max\s+(\d+)\/24h\s*x\s*(\d+)\s*days?$/i';

        if (preg_match($patternWithMax, $input, $matches)) {
            $doseValue = $matches[1] ?? '1';
            $doseUnit = $matches[2] ?? '';
            $maxDaily = (int) $matches[3];
            $durationDays = (int) $matches[4];

            $doseQuantity = $doseValue . ($doseUnit ? " {$doseUnit}" : '');
            $totalQuantity = $maxDaily * $durationDays;

            $displayText = "{$doseQuantity} PRN (max {$maxDaily}/24h) x {$durationDays} days";

            return ParsedPrescriptionResult::valid(
                doseQuantity: $doseQuantity,
                frequency: "As needed (max {$maxDaily}/24h)",
                frequencyCode: 'PRN',
                duration: "{$durationDays} days",
                durationDays: $durationDays,
                quantityToDispense: $totalQuantity,
                scheduleType: 'prn',
                schedulePattern: [
                    'type' => 'prn',
                    'max_daily' => $maxDaily,
                    'duration_days' => $durationDays,
                ],
                displayText: $displayText,
            );
        }

        // Pattern 2: Simple PRN - "PRN" or "2 PRN" or "2 tabs PRN"
        $pattern = '/^(\d+(?:\.\d+)?)?\s*(tabs?|capsules?|caps?|ml)?\s*PRN$/i';

        if (!preg_match($pattern, $input, $matches)) {
            return null;
        }

        $doseValue = $matches[1] ?? '1';
        $doseUnit = $matches[2] ?? '';

        $doseQuantity = $doseValue . ($doseUnit ? " {$doseUnit}" : '');
        $totalQuantity = (int) ceil((float) $doseValue);

        return ParsedPrescriptionResult::prn($doseQuantity, $totalQuantity);
    }

    /**
     * Try to parse partial input and provide helpful feedback.
     */
    private function parsePartial(string $input): ParsedPrescriptionResult
    {
        $errors = [];
        $warnings = [];
        $doseQuantity = null;
        $frequency = null;
        $frequencyCode = null;
        $duration = null;
        $durationDays = null;

        // Try to extract dose
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(ml|mg|tabs?|capsules?|caps?)?/i', $input, $matches)) {
            $doseQuantity = $matches[1] . (isset($matches[2]) ? " {$matches[2]}" : '');
        }

        // Try to extract frequency
        foreach (array_keys(self::FREQUENCY_MAP) as $code) {
            if (preg_match('/\b' . preg_quote($code, '/') . '\b/i', $input)) {
                $freq = $this->parseFrequency($code);
                if ($freq) {
                    $frequency = $freq['description'];
                    $frequencyCode = $freq['code'];
                    break;
                }
            }
        }

        // Try to extract duration
        if (preg_match('/x\s*(.+)$/i', $input, $matches)) {
            $dur = $this->parseDuration($matches[1]);
            if ($dur) {
                $duration = $dur['duration'];
                $durationDays = $dur['days'];
            }
        }

        // Build error messages
        if (!$doseQuantity) {
            $errors[] = 'Could not find dose quantity. Start with a number (e.g., "2 BD x 5 days")';
        }
        if (!$frequency) {
            $errors[] = 'Could not find frequency. Use OD, BD, TDS, QDS, Q6H, Q8H, or Q12H';
        }
        if (!$duration && !$frequencyCode) {
            $errors[] = 'Could not find duration. Add "x N days" or "x N/7"';
        } elseif (!$duration && $frequencyCode !== 'STAT' && $frequencyCode !== 'PRN') {
            $errors[] = 'Could not find duration. Add "x N days" or "x N/7"';
        }

        if (empty($errors)) {
            $errors[] = "Could not parse prescription. Try formats like '2 BD x 5 days' or '1-0-1 x 7 days'";
        }

        return ParsedPrescriptionResult::partial(
            errors: $errors,
            warnings: $warnings,
            doseQuantity: $doseQuantity,
            frequency: $frequency,
            frequencyCode: $frequencyCode,
            duration: $duration,
            durationDays: $durationDays,
        );
    }

    /**
     * Fixed-unit drug forms that default to 1 unit regardless of frequency/duration.
     *
     * These are medications typically dispensed as single units:
     * - Drops (eye/ear): 1 bottle (standard bottles contain sufficient drops)
     * - Inhalers: 1 device
     * - Combination packs: 1 pack
     *
     * Note: Creams, ointments, gels are handled separately as topicals
     */
    private const FIXED_UNIT_FORMS = [
        'drops',
        'inhaler',
        'combination_pack',
    ];

    /**
     * Topical drug forms - quantity is specified directly (e.g., "2 tubes")
     */
    private const TOPICAL_FORMS = [
        'cream',
        'ointment',
        'gel',
        'lotion',
    ];

    /**
     * Calculate quantity to dispense based on drug type.
     */
    public function calculateQuantity(ParsedPrescriptionResult $result, Drug $drug): int
    {
        if (!$result->isValid) {
            return 0;
        }

        // For STAT and PRN, use the parsed quantity
        if (in_array($result->scheduleType, ['stat', 'prn'])) {
            return $result->quantityToDispense ?? 1;
        }

        $drugForm = strtolower($drug->form ?? '');

        // Check if it's a fixed-unit drug (drops, inhaler, combination pack)
        // These default to 1 unit regardless of frequency and duration
        if (in_array($drugForm, self::FIXED_UNIT_FORMS)) {
            return 1;
        }

        // Check if it's a topical (cream, ointment, gel, lotion)
        // For topicals, use the dose quantity directly as the number of tubes/units
        // e.g., "2" means 2 tubes, "1" means 1 tube
        // If parsed via standard parser (e.g., "2 BD x 5 days"), just use the dose number
        if (in_array($drugForm, self::TOPICAL_FORMS)) {
            // For topical schedule type, use the parsed quantity
            if ($result->scheduleType === 'topical') {
                return $result->quantityToDispense ?? 1;
            }
            // For other schedule types (standard, etc.), extract dose and use as quantity
            $doseValue = (float) preg_replace('/[^0-9.]/', '', $result->doseQuantity ?? '1');

            return max(1, (int) ceil($doseValue));
        }

        // Extract numeric dose value
        $doseValue = (float) preg_replace('/[^0-9.]/', '', $result->doseQuantity ?? '1');

        // Check if it's a liquid drug (syrup, suspension) - calculate bottles needed
        // For liquids, the dose is assumed to be in ml (e.g., "2" means 2ml, "5ml" means 5ml)
        $isLiquid = in_array($drugForm, ['syrup', 'suspension', 'solution', 'liquid']);

        if ($isLiquid) {
            // If bottle_size is not configured, return 0 to indicate manual entry is needed
            // The frontend will show a manual input field when quantity is 0 for liquids
            if (!$drug->bottle_size) {
                return 0;
            }

            // Calculate bottles needed using the configured bottle size
            $timesPerDay = $result->getTimesPerDay() ?? 1;
            $totalMl = $doseValue * $timesPerDay * ($result->durationDays ?? 1);

            return (int) ceil($totalMl / $drug->bottle_size);
        }

        // For piece-based drugs (tablets, capsules)
        return $result->quantityToDispense ?? 0;
    }

    /**
     * Convert parsed result to schedule pattern for MAR.
     *
     * @return array<string, mixed>|null
     */
    public function toSchedulePattern(ParsedPrescriptionResult $result): ?array
    {
        if (!$result->isValid) {
            return null;
        }

        return $result->schedulePattern;
    }

    /**
     * Format parsed result as human-readable display text.
     */
    public function format(ParsedPrescriptionResult $result): string
    {
        if (!$result->isValid) {
            return '';
        }

        if ($result->displayText) {
            return $result->displayText;
        }

        $parts = [];

        if ($result->doseQuantity) {
            $parts[] = $result->doseQuantity;
        }

        if ($result->frequencyCode && !in_array($result->frequencyCode, ['SPLIT', 'CUSTOM', 'TAPER'])) {
            $parts[] = $result->frequencyCode;
        }

        if ($result->duration && !in_array($result->scheduleType, ['stat', 'prn'])) {
            $parts[] = "x {$result->duration}";
        }

        return implode(' ', $parts);
    }

    /**
     * Apply drug-specific quantity calculation to result.
     */
    private function applyDrugQuantity(ParsedPrescriptionResult $result, Drug $drug): ParsedPrescriptionResult
    {
        $calculatedQuantity = $this->calculateQuantity($result, $drug);

        return new ParsedPrescriptionResult(
            isValid: $result->isValid,
            doseQuantity: $result->doseQuantity,
            frequency: $result->frequency,
            frequencyCode: $result->frequencyCode,
            duration: $result->duration,
            durationDays: $result->durationDays,
            quantityToDispense: $calculatedQuantity,
            scheduleType: $result->scheduleType,
            schedulePattern: $result->schedulePattern,
            displayText: $result->displayText,
            errors: $result->errors,
            warnings: $result->warnings,
        );
    }
}
