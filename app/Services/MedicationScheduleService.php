<?php

namespace App\Services;

use App\Models\MedicationAdministration;
use App\Models\Prescription;
use Carbon\Carbon;

class MedicationScheduleService
{
    /**
     * Generate medication administration schedule for a prescription.
     */
    public function generateSchedule(Prescription $prescription): void
    {
        // Only for admitted patients
        if (! $prescription->consultation->patientAdmission) {
            return;
        }

        $schedules = $this->parseFrequency($prescription->frequency);
        $duration = $this->parseDuration($prescription->duration);

        $startDate = now()->startOfDay();
        $endDate = $startDate->copy()->addDays($duration);

        foreach ($this->generateDates($startDate, $endDate) as $date) {
            foreach ($schedules as $time) {
                MedicationAdministration::create([
                    'prescription_id' => $prescription->id,
                    'patient_admission_id' => $prescription->consultation->patientAdmission->id,
                    'scheduled_time' => Carbon::parse($date->format('Y-m-d').' '.$time),
                    'status' => 'scheduled',
                    'dosage_given' => null,
                    'route' => $prescription->route ?? 'oral',
                ]);
            }
        }
    }

    /**
     * Parse prescription frequency into scheduled times.
     */
    private function parseFrequency(string $frequency): array
    {
        // Standard medication frequencies
        $schedules = [
            'OD' => ['08:00'], // Once daily
            'BD' => ['08:00', '20:00'], // Twice daily
            'TDS' => ['08:00', '14:00', '20:00'], // Three times daily
            'QDS' => ['08:00', '12:00', '16:00', '20:00'], // Four times daily
            'QID' => ['08:00', '12:00', '16:00', '20:00'], // Four times daily (alternate)
            'Q6H' => ['06:00', '12:00', '18:00', '00:00'], // Every 6 hours
            'Q8H' => ['08:00', '16:00', '00:00'], // Every 8 hours
            'Q12H' => ['08:00', '20:00'], // Every 12 hours
            'PRN' => ['08:00'], // As needed (default schedule)
            'STAT' => ['now'], // Immediately (special handling)
        ];

        $upperFreq = strtoupper(trim($frequency));

        // Handle STAT (immediately)
        if ($upperFreq === 'STAT') {
            return [now()->format('H:i')];
        }

        return $schedules[$upperFreq] ?? ['08:00']; // Default to once daily
    }

    /**
     * Parse prescription duration into number of days.
     */
    private function parseDuration(string $duration): int
    {
        // Parse durations like "5 days", "2 weeks", "1 month"
        if (preg_match('/(\d+)\s*(day|week|month)/i', $duration, $matches)) {
            $value = (int) $matches[1];
            $unit = strtolower($matches[2]);

            return match ($unit) {
                'week' => $value * 7,
                'month' => $value * 30,
                default => $value,
            };
        }

        // Try to extract just a number
        if (preg_match('/(\d+)/', $duration, $matches)) {
            return (int) $matches[1];
        }

        return 5; // default 5 days
    }

    /**
     * Generate array of dates between start and end.
     */
    private function generateDates(Carbon $start, Carbon $end): array
    {
        $dates = [];
        $current = $start->copy();

        while ($current <= $end) {
            $dates[] = $current->copy();
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Regenerate schedules for a prescription (e.g., after dosage change).
     */
    public function regenerateSchedule(Prescription $prescription): void
    {
        // Delete future scheduled medications
        MedicationAdministration::where('prescription_id', $prescription->id)
            ->where('status', 'scheduled')
            ->where('scheduled_time', '>', now())
            ->delete();

        // Generate new schedule
        $this->generateSchedule($prescription);
    }
}
