<?php

namespace App\Services\Prescription;

/**
 * Value object representing the result of parsing a prescription input string.
 */
class ParsedPrescriptionResult
{
    public function __construct(
        public bool $isValid,
        public ?string $doseQuantity = null,
        public ?string $frequency = null,
        public ?string $frequencyCode = null,
        public ?string $duration = null,
        public ?int $durationDays = null,
        public ?int $quantityToDispense = null,
        public string $scheduleType = 'standard',
        public ?array $schedulePattern = null,
        public ?string $displayText = null,
        public array $errors = [],
        public array $warnings = [],
    ) {}

    /**
     * Create a valid result with all parsed components.
     */
    public static function valid(
        string $doseQuantity,
        string $frequency,
        string $frequencyCode,
        string $duration,
        ?int $durationDays,
        int $quantityToDispense,
        string $scheduleType = 'standard',
        ?array $schedulePattern = null,
        ?string $displayText = null,
        array $warnings = [],
    ): self {
        return new self(
            isValid: true,
            doseQuantity: $doseQuantity,
            frequency: $frequency,
            frequencyCode: $frequencyCode,
            duration: $duration,
            durationDays: $durationDays,
            quantityToDispense: $quantityToDispense,
            scheduleType: $scheduleType,
            schedulePattern: $schedulePattern,
            displayText: $displayText,
            errors: [],
            warnings: $warnings,
        );
    }

    /**
     * Create an invalid result with error messages.
     */
    public static function invalid(array $errors, array $warnings = []): self
    {
        return new self(
            isValid: false,
            errors: $errors,
            warnings: $warnings,
        );
    }

    /**
     * Create a partial result where some components were recognized.
     */
    public static function partial(
        array $errors,
        array $warnings = [],
        ?string $doseQuantity = null,
        ?string $frequency = null,
        ?string $frequencyCode = null,
        ?string $duration = null,
        ?int $durationDays = null,
    ): self {
        return new self(
            isValid: false,
            doseQuantity: $doseQuantity,
            frequency: $frequency,
            frequencyCode: $frequencyCode,
            duration: $duration,
            durationDays: $durationDays,
            errors: $errors,
            warnings: $warnings,
        );
    }

    /**
     * Create a valid STAT result (single immediate dose).
     */
    public static function stat(string $doseQuantity, int $quantityToDispense): self
    {
        return new self(
            isValid: true,
            doseQuantity: $doseQuantity,
            frequency: 'Immediately (STAT)',
            frequencyCode: 'STAT',
            duration: 'Single dose',
            durationDays: 1,
            quantityToDispense: $quantityToDispense,
            scheduleType: 'stat',
            displayText: "{$doseQuantity} STAT",
        );
    }

    /**
     * Create a valid PRN result (as needed).
     */
    public static function prn(string $doseQuantity, int $quantityToDispense): self
    {
        return new self(
            isValid: true,
            doseQuantity: $doseQuantity,
            frequency: 'As needed (PRN)',
            frequencyCode: 'PRN',
            duration: 'As needed',
            durationDays: null,
            quantityToDispense: $quantityToDispense,
            scheduleType: 'prn',
            displayText: "{$doseQuantity} PRN",
        );
    }

    /**
     * Convert the result to an array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'isValid' => $this->isValid,
            'doseQuantity' => $this->doseQuantity,
            'frequency' => $this->frequency,
            'frequencyCode' => $this->frequencyCode,
            'duration' => $this->duration,
            'durationDays' => $this->durationDays,
            'quantityToDispense' => $this->quantityToDispense,
            'scheduleType' => $this->scheduleType,
            'schedulePattern' => $this->schedulePattern,
            'displayText' => $this->displayText,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Check if the result has any warnings.
     */
    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }

    /**
     * Check if the result has any errors.
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Get the times per day based on frequency code.
     */
    public function getTimesPerDay(): ?int
    {
        return match ($this->frequencyCode) {
            'OD' => 1,
            'BD' => 2,
            'TDS' => 3,
            'QDS' => 4,
            'Q6H' => 4,
            'Q8H' => 3,
            'Q12H' => 2,
            'STAT' => 1,
            'PRN' => null,
            default => null,
        };
    }
}
