<?php

use App\Models\MedicationAdministration;
use App\Models\Prescription;
use App\Models\User;
use App\Services\MedicationScheduleService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new MedicationScheduleService;
});

describe('calculateFirstDoseTime', function () {
    it('returns 6 AM today when current time is before 6 AM', function () {
        Carbon::setTestNow('2024-01-15 05:30:00');

        $firstDose = $this->service->calculateFirstDoseTime();

        expect($firstDose->format('Y-m-d H:i:s'))->toBe('2024-01-15 06:00:00');
    });

    it('returns 6 AM tomorrow when current time is after 6 AM', function () {
        Carbon::setTestNow('2024-01-15 08:30:00');

        $firstDose = $this->service->calculateFirstDoseTime();

        expect($firstDose->format('Y-m-d H:i:s'))->toBe('2024-01-16 06:00:00');
    });

    it('returns 6 AM tomorrow when current time is exactly 6 AM', function () {
        Carbon::setTestNow('2024-01-15 06:00:00');

        $firstDose = $this->service->calculateFirstDoseTime();

        expect($firstDose->format('Y-m-d H:i:s'))->toBe('2024-01-16 06:00:00');
    });
});

describe('calculateIntervalHours', function () {
    it('returns 12 hours for BID', function () {
        expect($this->service->calculateIntervalHours('BID'))->toBe(12);
    });

    it('returns 12 hours for BD', function () {
        expect($this->service->calculateIntervalHours('BD'))->toBe(12);
    });

    it('returns 12 hours for Q12H', function () {
        expect($this->service->calculateIntervalHours('Q12H'))->toBe(12);
    });

    it('returns 8 hours for TID', function () {
        expect($this->service->calculateIntervalHours('TID'))->toBe(8);
    });

    it('returns 8 hours for TDS', function () {
        expect($this->service->calculateIntervalHours('TDS'))->toBe(8);
    });

    it('returns 8 hours for Q8H', function () {
        expect($this->service->calculateIntervalHours('Q8H'))->toBe(8);
    });

    it('returns 6 hours for QID', function () {
        expect($this->service->calculateIntervalHours('QID'))->toBe(6);
    });

    it('returns 6 hours for QDS', function () {
        expect($this->service->calculateIntervalHours('QDS'))->toBe(6);
    });

    it('returns 6 hours for Q6H', function () {
        expect($this->service->calculateIntervalHours('Q6H'))->toBe(6);
    });

    it('returns 4 hours for Q4H', function () {
        expect($this->service->calculateIntervalHours('Q4H'))->toBe(4);
    });

    it('returns 2 hours for Q2H', function () {
        expect($this->service->calculateIntervalHours('Q2H'))->toBe(2);
    });

    it('returns 24 hours for OD', function () {
        expect($this->service->calculateIntervalHours('OD'))->toBe(24);
    });

    it('extracts frequency from parentheses', function () {
        expect($this->service->calculateIntervalHours('Twice daily (BID)'))->toBe(12);
    });

    it('is case insensitive', function () {
        expect($this->service->calculateIntervalHours('bid'))->toBe(12)
            ->and($this->service->calculateIntervalHours('Bid'))->toBe(12)
            ->and($this->service->calculateIntervalHours('BID'))->toBe(12);
    });
});

describe('parseFrequencyInterval', function () {
    it('parses "every X hours" pattern', function () {
        expect($this->service->parseFrequencyInterval('every 4 hours'))->toBe(4)
            ->and($this->service->parseFrequencyInterval('every 6 hours'))->toBe(6)
            ->and($this->service->parseFrequencyInterval('every 12 hours'))->toBe(12);
    });

    it('parses "X hourly" pattern', function () {
        expect($this->service->parseFrequencyInterval('4 hourly'))->toBe(4)
            ->and($this->service->parseFrequencyInterval('6 hourly'))->toBe(6);
    });

    it('parses "every X hrs" pattern', function () {
        expect($this->service->parseFrequencyInterval('every 4 hrs'))->toBe(4)
            ->and($this->service->parseFrequencyInterval('every 6 hrs'))->toBe(6);
    });

    it('is case insensitive', function () {
        expect($this->service->parseFrequencyInterval('Every 4 Hours'))->toBe(4)
            ->and($this->service->parseFrequencyInterval('EVERY 4 HOURS'))->toBe(4);
    });

    it('returns null for non-matching patterns', function () {
        expect($this->service->parseFrequencyInterval('twice daily'))->toBeNull()
            ->and($this->service->parseFrequencyInterval('as needed'))->toBeNull();
    });
});

describe('adjustScheduleTime', function () {
    it('adjusts scheduled time and creates audit record', function () {
        $user = User::factory()->create();
        $prescription = Prescription::factory()
            ->for(\App\Models\Consultation::factory())
            ->for(\App\Models\Drug::factory())
            ->createQuietly();

        $admission = \App\Models\PatientAdmission::factory()->create();

        $administration = MedicationAdministration::factory()
            ->for($prescription)
            ->for($admission, 'patientAdmission')
            ->create([
                'scheduled_time' => now()->addHours(2),
                'status' => 'scheduled',
                'is_adjusted' => false,
            ]);

        $newTime = now()->addHours(4);
        $reason = 'Patient requested later time';

        $this->service->adjustScheduleTime($administration, $newTime, $user, $reason);

        $administration->refresh();

        expect($administration->scheduled_time->format('Y-m-d H:i'))->toBe($newTime->format('Y-m-d H:i'))
            ->and($administration->is_adjusted)->toBeTrue()
            ->and($administration->scheduleAdjustments)->toHaveCount(1)
            ->and($administration->scheduleAdjustments->first()->adjusted_by_id)->toBe($user->id)
            ->and($administration->scheduleAdjustments->first()->reason)->toBe($reason);
    });

    it('throws exception when adjusting already given medication', function () {
        $user = User::factory()->create();
        $prescription = Prescription::factory()
            ->for(\App\Models\Consultation::factory())
            ->for(\App\Models\Drug::factory())
            ->createQuietly();

        $admission = \App\Models\PatientAdmission::factory()->create();

        $administration = MedicationAdministration::factory()
            ->for($prescription)
            ->for($admission, 'patientAdmission')
            ->given()
            ->create([
                'scheduled_time' => now()->subHours(2),
            ]);

        $newTime = now()->addHours(4);

        $this->service->adjustScheduleTime($administration, $newTime, $user);
    })->throws(\InvalidArgumentException::class, 'Cannot adjust medication that has already been administered');
});

describe('discontinuePrescription', function () {
    it('sets discontinuation fields and cancels future doses', function () {
        $user = User::factory()->create();
        $prescription = Prescription::factory()
            ->for(\App\Models\Consultation::factory())
            ->for(\App\Models\Drug::factory())
            ->createQuietly([
                'discontinued_at' => null,
            ]);

        $admission = \App\Models\PatientAdmission::factory()->create();

        // Create some administrations
        MedicationAdministration::factory()
            ->for($prescription)
            ->for($admission, 'patientAdmission')
            ->given()
            ->create([
                'scheduled_time' => now()->subHours(2),
            ]);

        $futureAdmin1 = MedicationAdministration::factory()
            ->for($prescription)
            ->for($admission, 'patientAdmission')
            ->create([
                'scheduled_time' => now()->addHours(2),
                'status' => 'scheduled',
            ]);

        $futureAdmin2 = MedicationAdministration::factory()
            ->for($prescription)
            ->for($admission, 'patientAdmission')
            ->create([
                'scheduled_time' => now()->addHours(8),
                'status' => 'scheduled',
            ]);

        $reason = 'Switching to different medication';

        $this->service->discontinuePrescription($prescription, $user, $reason);

        $prescription->refresh();

        expect($prescription->discontinued_at)->not->toBeNull()
            ->and($prescription->discontinued_by_id)->toBe($user->id)
            ->and($prescription->discontinuation_reason)->toBe($reason)
            ->and($futureAdmin1->fresh()->status)->toBe('cancelled')
            ->and($futureAdmin2->fresh()->status)->toBe('cancelled');
    });

    it('preserves already given administrations', function () {
        $user = User::factory()->create();
        $prescription = Prescription::factory()
            ->for(\App\Models\Consultation::factory())
            ->for(\App\Models\Drug::factory())
            ->createQuietly();

        $admission = \App\Models\PatientAdmission::factory()->create();

        $givenAdmin = MedicationAdministration::factory()
            ->for($prescription)
            ->for($admission, 'patientAdmission')
            ->given()
            ->create([
                'scheduled_time' => now()->subHours(2),
            ]);

        $this->service->discontinuePrescription($prescription, $user, 'Test reason');

        expect($givenAdmin->fresh()->status)->toBe('given');
    });
});
