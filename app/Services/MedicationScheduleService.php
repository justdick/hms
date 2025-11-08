<?php

namespace App\Services;

use App\Models\MedicationAdministration;
use App\Models\MedicationScheduleAdjustment;
use App\Models\Prescription;
use App\Models\User;
use Carbon\Carbon;

class MedicationScheduleService
{
    /**
     * Generate smart default time patterns based on frequency and current time.
     */
    public function generateSmartDefaults(string $frequency, Carbon $currentTime, int $duration): array
    {
        $upperFreq = strtoupper(trim($frequency));

        // Extract frequency code from descriptive text if needed
        if (preg_match('/\((BID|BD|TID|TDS|QID|QDS|Q12H|Q8H|Q6H|Q4H|Q2H|OD|PRN)\)/i', $frequency, $matches)) {
            $upperFreq = strtoupper($matches[1]);
        } elseif (preg_match('/\b(BID|BD|TID|TDS|QID|QDS|Q12H|Q8H|Q6H|Q4H|Q2H|OD|PRN)\b/i', $frequency, $matches)) {
            $upperFreq = strtoupper($matches[1]);
        }

        // PRN medications don't need schedules
        if ($upperFreq === 'PRN') {
            return [
                'day_1' => [],
                'subsequent' => [],
            ];
        }

        // BID (Twice daily): Day 1 = [current time, next standard time], Subsequent = [06:00, 18:00]
        if (in_array($upperFreq, ['BID', 'BD', 'Q12H'])) {
            $currentHour = $currentTime->format('H:i');
            $standardTimes = ['06:00', '18:00'];

            // Find next standard time after current time
            $nextStandardTime = null;
            foreach ($standardTimes as $time) {
                if ($time > $currentHour) {
                    $nextStandardTime = $time;
                    break;
                }
            }

            // If no next standard time today, use first time tomorrow (but we'll use 18:00 as fallback)
            if ($nextStandardTime === null) {
                $nextStandardTime = '18:00';
            }

            return [
                'day_1' => [$currentHour, $nextStandardTime],
                'subsequent' => ['06:00', '18:00'],
            ];
        }

        // TID (Three times daily): Day 1 = [next available from 06:00/14:00/22:00], Subsequent = [06:00, 14:00, 22:00]
        if (in_array($upperFreq, ['TID', 'TDS', 'Q8H'])) {
            $currentHour = $currentTime->format('H:i');
            $standardTimes = ['06:00', '14:00', '22:00'];

            // Find next available times for Day 1
            $day1Times = [];
            foreach ($standardTimes as $time) {
                if ($time >= $currentHour) {
                    $day1Times[] = $time;
                }
            }

            // If no times left today, start with all times tomorrow (but provide at least one time)
            if (empty($day1Times)) {
                $day1Times = ['14:00', '22:00'];
            }

            return [
                'day_1' => $day1Times,
                'subsequent' => ['06:00', '14:00', '22:00'],
            ];
        }

        // QID (Four times daily): Subsequent = [06:00, 12:00, 18:00, 00:00]
        if (in_array($upperFreq, ['QID', 'QDS', 'Q6H'])) {
            $currentHour = $currentTime->format('H:i');
            $standardTimes = ['06:00', '12:00', '18:00', '00:00'];

            // Find next available times for Day 1
            $day1Times = [];
            foreach ($standardTimes as $time) {
                if ($time >= $currentHour) {
                    $day1Times[] = $time;
                }
            }

            // If no times left today, provide some times
            if (empty($day1Times)) {
                $day1Times = ['12:00', '18:00', '00:00'];
            }

            return [
                'day_1' => $day1Times,
                'subsequent' => ['06:00', '12:00', '18:00', '00:00'],
            ];
        }

        // Q4H (Every 4 hours): Calculate from current time rounded to nearest hour
        if ($upperFreq === 'Q4H') {
            $roundedTime = $currentTime->copy()->minute === 0 && $currentTime->second === 0
                ? $currentTime->copy()
                : $currentTime->copy()->addHour()->startOfHour();

            $times = [];
            $time = $roundedTime->copy();

            // Generate times for 24 hours (6 doses)
            for ($i = 0; $i < 6; $i++) {
                $times[] = $time->format('H:i');
                $time->addHours(4);
            }

            return [
                'day_1' => [$times[0]],
                'subsequent' => array_slice($times, 0, 6),
            ];
        }

        // Q2H (Every 2 hours): Calculate from current time rounded to nearest hour
        if ($upperFreq === 'Q2H') {
            $roundedTime = $currentTime->copy()->minute === 0 && $currentTime->second === 0
                ? $currentTime->copy()
                : $currentTime->copy()->addHour()->startOfHour();

            $times = [];
            $time = $roundedTime->copy();

            // Generate times for 24 hours (12 doses)
            for ($i = 0; $i < 12; $i++) {
                $times[] = $time->format('H:i');
                $time->addHours(2);
            }

            return [
                'day_1' => [$times[0]],
                'subsequent' => array_slice($times, 0, 12),
            ];
        }

        // Q6H: Already handled in QID section above, but add explicit case
        if ($upperFreq === 'Q6H') {
            return [
                'day_1' => ['06:00', '12:00', '18:00', '00:00'],
                'subsequent' => ['06:00', '12:00', '18:00', '00:00'],
            ];
        }

        // OD (Once daily): Default to 06:00
        if ($upperFreq === 'OD') {
            $currentHour = $currentTime->format('H:i');
            $day1Time = $currentHour >= '06:00' ? $currentHour : '06:00';

            return [
                'day_1' => [$day1Time],
                'subsequent' => ['06:00'],
            ];
        }

        // Default: Once daily at 06:00
        return [
            'day_1' => ['06:00'],
            'subsequent' => ['06:00'],
        ];
    }

    /**
     * Generate medication administration schedule from pattern.
     */
    public function generateScheduleFromPattern(Prescription $prescription): void
    {
        // Get patient admission
        $admission = $this->getPatientAdmission($prescription);

        if (! $admission) {
            return; // Only for admitted patients
        }

        // Skip PRN medications - they are administered on-demand
        if (strtoupper(trim($prescription->frequency ?? '')) === 'PRN') {
            return;
        }

        // Check if prescription has schedule pattern
        if (! $prescription->hasSchedule()) {
            return; // No schedule pattern configured
        }

        $schedulePattern = $prescription->schedule_pattern;
        $duration = $this->parseDuration($prescription->duration);
        $startDate = now()->startOfDay();

        // Generate schedule for each day
        for ($dayNumber = 1; $dayNumber <= $duration; $dayNumber++) {
            $currentDate = $startDate->copy()->addDays($dayNumber - 1);

            // Determine which times to use for this day
            $times = $this->getTimesForDay($schedulePattern, $dayNumber);

            // Create MedicationAdministration records for each time
            foreach ($times as $time) {
                $scheduledDateTime = $currentDate->copy()->setTimeFromTimeString($time);

                MedicationAdministration::create([
                    'prescription_id' => $prescription->id,
                    'patient_admission_id' => $admission->id,
                    'scheduled_time' => $scheduledDateTime,
                    'status' => 'scheduled',
                    'dosage_given' => $prescription->dose_quantity,
                    'route' => null,
                    'is_adjusted' => false,
                ]);
            }
        }
    }

    /**
     * Get times for a specific day from schedule pattern.
     */
    private function getTimesForDay(array $schedulePattern, int $dayNumber): array
    {
        // Check for day_1 pattern
        if ($dayNumber === 1 && isset($schedulePattern['day_1'])) {
            return $schedulePattern['day_1'];
        }

        // Check for specific day pattern (day_2, day_3, etc.)
        $dayKey = "day_{$dayNumber}";
        if (isset($schedulePattern[$dayKey])) {
            return $schedulePattern[$dayKey];
        }

        // Use subsequent pattern as default
        return $schedulePattern['subsequent'] ?? [];
    }

    /**
     * Generate medication administration schedule for a prescription.
     */
    public function generateSchedule(Prescription $prescription): void
    {
        // Get patient admission - prescribable can be either WardRound or Consultation
        $admission = $this->getPatientAdmission($prescription);

        if (! $admission) {
            return; // Only for admitted patients
        }

        // Skip PRN medications - they are administered on-demand
        if (strtoupper(trim($prescription->frequency ?? '')) === 'PRN') {
            return;
        }

        // Calculate interval hours for the frequency
        $intervalHours = $this->calculateIntervalHours($prescription->frequency);

        if ($intervalHours === null) {
            return; // Invalid frequency
        }

        // Calculate first dose time (next 6 AM)
        $firstDoseTime = $this->calculateFirstDoseTime();

        // Parse duration
        $duration = $this->parseDuration($prescription->duration);

        // Generate all scheduled times
        $scheduledTimes = $this->generateScheduleTimes($firstDoseTime, $intervalHours, $duration);

        // Create MedicationAdministration records
        foreach ($scheduledTimes as $scheduledTime) {
            MedicationAdministration::create([
                'prescription_id' => $prescription->id,
                'patient_admission_id' => $admission->id,
                'scheduled_time' => $scheduledTime,
                'status' => 'scheduled',
                'dosage_given' => $prescription->dose_quantity,
                'route' => null, // Will be set during administration
                'is_adjusted' => false,
            ]);
        }
    }

    /**
     * Calculate the first dose time (start immediately, rounded to next hour).
     */
    public function calculateFirstDoseTime(): Carbon
    {
        $now = now();

        // Round up to the next hour
        // If we're at 14:30, this will give us 15:00
        // If we're at 14:00 exactly, this will give us 14:00
        $nextHour = $now->copy()->addHour()->startOfHour();

        // If current time is already on the hour (no minutes/seconds), use current hour
        if ($now->minute === 0 && $now->second === 0) {
            return $now->copy();
        }

        return $nextHour;
    }

    /**
     * Calculate interval hours based on frequency code.
     */
    public function calculateIntervalHours(string $frequency): ?int
    {
        $upperFreq = strtoupper(trim($frequency));

        // Standard frequency mappings
        $intervalMap = [
            'BID' => 12,
            'BD' => 12,
            'Q12H' => 12,
            'TID' => 8,
            'TDS' => 8,
            'Q8H' => 8,
            'QID' => 6,
            'QDS' => 6,
            'Q6H' => 6,
            'Q4H' => 4,
            'Q2H' => 2,
            'OD' => 24,
        ];

        // Try to extract abbreviation from descriptive text
        if (preg_match('/\((BID|BD|TID|TDS|QID|QDS|Q12H|Q8H|Q6H|Q4H|Q2H|OD)\)/i', $frequency, $matches)) {
            $upperFreq = strtoupper($matches[1]);
        } elseif (preg_match('/\b(BID|BD|TID|TDS|QID|QDS|Q12H|Q8H|Q6H|Q4H|Q2H|OD)\b/i', $frequency, $matches)) {
            $upperFreq = strtoupper($matches[1]);
        }

        // Check if we have a direct mapping
        if (isset($intervalMap[$upperFreq])) {
            return $intervalMap[$upperFreq];
        }

        // Try to parse dynamic interval patterns
        $parsedInterval = $this->parseFrequencyInterval($frequency);
        if ($parsedInterval !== null) {
            return $parsedInterval;
        }

        return null;
    }

    /**
     * Parse frequency interval from text patterns.
     */
    public function parseFrequencyInterval(string $frequency): ?int
    {
        // Pattern: "every X hours", "X hourly", "every X hrs"
        if (preg_match('/every\s+(\d+)\s+hours?/i', $frequency, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d+)\s+hourly/i', $frequency, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/every\s+(\d+)\s+hrs?/i', $frequency, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Generate scheduled times based on first dose, interval, and duration.
     */
    private function generateScheduleTimes(Carbon $firstDose, int $intervalHours, int $durationDays): array
    {
        $times = [];
        $currentTime = $firstDose->copy();
        $endTime = $firstDose->copy()->addDays($durationDays);

        while ($currentTime->lessThan($endTime)) {
            $times[] = $currentTime->copy();
            $currentTime->addHours($intervalHours);
        }

        return $times;
    }

    /**
     * Reconfigure schedule with new pattern.
     */
    public function reconfigureSchedule(Prescription $prescription, array $newPattern, User $user): void
    {
        // Cancel all future scheduled administrations
        MedicationAdministration::where('prescription_id', $prescription->id)
            ->where('status', 'scheduled')
            ->where('scheduled_time', '>', now())
            ->update(['status' => 'cancelled']);

        // Update prescription schedule_pattern
        $prescription->update([
            'schedule_pattern' => $newPattern,
        ]);

        // Generate new schedule from pattern
        $this->generateScheduleFromPattern($prescription);

        // Create audit record (using MedicationScheduleAdjustment for tracking)
        // Note: This creates an audit entry for the reconfiguration action
        // We'll use the first future administration as the reference point
        $firstFutureAdmin = MedicationAdministration::where('prescription_id', $prescription->id)
            ->where('status', 'scheduled')
            ->where('scheduled_time', '>', now())
            ->orderBy('scheduled_time')
            ->first();

        if ($firstFutureAdmin) {
            MedicationScheduleAdjustment::create([
                'medication_administration_id' => $firstFutureAdmin->id,
                'adjusted_by_id' => $user->id,
                'original_time' => now(),
                'adjusted_time' => $firstFutureAdmin->scheduled_time,
                'reason' => 'Schedule reconfigured with new pattern',
            ]);
        }
    }

    /**
     * Adjust the scheduled time for a medication administration.
     */
    public function adjustScheduleTime(
        MedicationAdministration $administration,
        Carbon $newTime,
        User $user,
        ?string $reason = null
    ): void {
        // Validate administration is not already given
        if (! $administration->canBeAdjusted()) {
            throw new \InvalidArgumentException('Cannot adjust medication that has already been administered');
        }

        // Store original time for audit
        $originalTime = $administration->scheduled_time;

        // Update scheduled time and set adjusted flag
        $administration->update([
            'scheduled_time' => $newTime,
            'is_adjusted' => true,
        ]);

        // Create audit record
        MedicationScheduleAdjustment::create([
            'medication_administration_id' => $administration->id,
            'adjusted_by_id' => $user->id,
            'original_time' => $originalTime,
            'adjusted_time' => $newTime,
            'reason' => $reason,
        ]);
    }

    /**
     * Discontinue a prescription and cancel future administrations.
     */
    public function discontinuePrescription(
        Prescription $prescription,
        User $user,
        ?string $reason = null
    ): void {
        // Set discontinuation fields on prescription
        $prescription->discontinue($user, $reason);

        // Cancel all future scheduled administrations
        MedicationAdministration::where('prescription_id', $prescription->id)
            ->where('status', 'scheduled')
            ->where('scheduled_time', '>', now())
            ->update(['status' => 'cancelled']);
    }

    /**
     * Get patient admission from prescription.
     */
    private function getPatientAdmission(Prescription $prescription): ?\App\Models\PatientAdmission
    {
        // If prescribable is WardRound, get admission from ward round
        if ($prescription->prescribable_type === 'App\Models\WardRound') {
            return $prescription->prescribable->patientAdmission ?? null;
        }

        // If prescribable is Consultation, check if it has an admission
        if ($prescription->prescribable_type === 'App\Models\Consultation') {
            return $prescription->prescribable->patientAdmission ?? null;
        }

        // If prescription has direct consultation_id, check for admission
        if ($prescription->consultation_id) {
            return $prescription->consultation->patientAdmission ?? null;
        }

        return null;
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
            'BID' => ['08:00', '20:00'], // Twice daily (alternate)
            'TDS' => ['08:00', '14:00', '20:00'], // Three times daily
            'TID' => ['08:00', '14:00', '20:00'], // Three times daily (alternate)
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

        // Try to extract abbreviation from descriptive text
        // e.g., "Twice daily (BID)" -> "BID", "Every 6 hours" -> "Q6H"
        if (preg_match('/\((OD|BD|BID|TDS|TID|QDS|QID|Q6H|Q8H|Q12H|PRN|STAT)\)/i', $frequency, $matches)) {
            $upperFreq = strtoupper($matches[1]);
        } elseif (preg_match('/\b(OD|BD|BID|TDS|TID|QDS|QID|Q6H|Q8H|Q12H|PRN|STAT)\b/i', $frequency, $matches)) {
            $upperFreq = strtoupper($matches[1]);
        } elseif (stripos($frequency, 'every 6 hours') !== false || stripos($frequency, '6 hourly') !== false) {
            $upperFreq = 'Q6H';
        } elseif (stripos($frequency, 'every 8 hours') !== false || stripos($frequency, '8 hourly') !== false) {
            $upperFreq = 'Q8H';
        } elseif (stripos($frequency, 'every 12 hours') !== false || stripos($frequency, '12 hourly') !== false) {
            $upperFreq = 'Q12H';
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
