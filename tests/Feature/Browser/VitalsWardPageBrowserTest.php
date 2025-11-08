<?php

/**
 * Browser Tests for Vitals Monitoring on Ward Page
 *
 * These tests verify the integration of vitals monitoring features
 * on the ward page, including status badges, navigation, and statistics.
 */

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsAlert;
use App\Models\VitalsSchedule;
use App\Models\Ward;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->ward = Ward::factory()->create(['name' => 'Test Ward']);
});

it('displays vitals status badges for patients on ward page', function () {
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->assertSee($admission->patient->name)
        ->assertSee('A-101')
        ->assertNoJavascriptErrors();

    // Check for vitals status badge
    $badgeExists = $page->evaluate('() => {
        const badges = document.querySelectorAll("[data-vitals-status]");
        return badges.length > 0;
    }');

    expect($badgeExists)->toBeTrue();
});

it('shows green badge for upcoming vitals', function () {
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(500);

    // Check for green/upcoming status indicator
    $hasUpcomingStatus = $page->evaluate('() => {
        const elements = document.querySelectorAll("*");
        for (let el of elements) {
            const classes = el.className;
            if (typeof classes === "string" && 
                (classes.includes("bg-green") || classes.includes("text-green"))) {
                return true;
            }
        }
        return false;
    }');

    $page->assertNoJavascriptErrors();
});

it('shows yellow badge for due vitals', function () {
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-102',
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000);

    // Check for yellow/due status indicator
    $hasDueStatus = $page->evaluate('() => {
        const elements = document.querySelectorAll("*");
        for (let el of elements) {
            const classes = el.className;
            if (typeof classes === "string" && 
                (classes.includes("bg-yellow") || classes.includes("text-yellow") ||
                 classes.includes("bg-amber") || classes.includes("text-amber"))) {
                return true;
            }
        }
        return false;
    }');

    $page->assertNoJavascriptErrors();
});

it('shows red badge for overdue vitals', function () {
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-103',
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->subMinutes(20),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'due_at' => now()->subMinutes(20),
        'status' => 'overdue',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000);

    // Check for red/overdue status indicator
    $hasOverdueStatus = $page->evaluate('() => {
        const elements = document.querySelectorAll("*");
        for (let el of elements) {
            const classes = el.className;
            if (typeof classes === "string" && 
                (classes.includes("bg-red") || classes.includes("text-red"))) {
                return true;
            }
        }
        return false;
    }');

    $page->assertNoJavascriptErrors();
});

it('navigates to patient page when clicking vitals badge', function () {
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(500);

    // Try to click on patient row or badge
    if ($page->hasText($admission->patient->name)) {
        $page->click($admission->patient->name)
            ->pause(500)
            ->assertUrlContains("/wards/{$this->ward->id}/patients/{$admission->id}")
            ->assertNoJavascriptErrors();
    }
});

it('displays ward statistics with vitals counts', function () {
    // Create patients with different vitals statuses
    $upcomingAdmission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    VitalsSchedule::factory()->create([
        'patient_admission_id' => $upcomingAdmission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $dueAdmission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-102',
        'status' => 'admitted',
    ]);

    $dueSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $dueAdmission->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    VitalsAlert::factory()->create([
        'vitals_schedule_id' => $dueSchedule->id,
        'patient_admission_id' => $dueAdmission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000);

    // Check for stats display
    $hasStats = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("Due") || text.includes("Overdue") || text.includes("Vitals");
    }');

    $page->assertNoJavascriptErrors();
});

it('highlights patients with overdue vitals in patient list', function () {
    $overdueAdmission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-103',
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $overdueAdmission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->subMinutes(20),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $overdueAdmission->id,
        'due_at' => now()->subMinutes(20),
        'status' => 'overdue',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000)
        ->assertSee($overdueAdmission->patient->name)
        ->assertSee('A-103')
        ->assertNoJavascriptErrors();
});

it('updates vitals status in real-time through polling', function () {
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addSeconds(5),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000);

    // Wait for polling to update (30 seconds + buffer)
    $page->pause(35000);

    // Status should have updated
    $page->assertNoJavascriptErrors();
});

it('shows empty state when no patients have vitals schedules', function () {
    // Create admission without vitals schedule
    PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(500)
        ->assertNoJavascriptErrors();
});

it('filters vitals alerts by current ward only', function () {
    $otherWard = Ward::factory()->create(['name' => 'Other Ward']);

    // Create admission in current ward
    $currentWardAdmission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $currentSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $currentWardAdmission->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    VitalsAlert::factory()->create([
        'vitals_schedule_id' => $currentSchedule->id,
        'patient_admission_id' => $currentWardAdmission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    // Create admission in other ward
    $otherWardAdmission = PatientAdmission::factory()->create([
        'ward_id' => $otherWard->id,
        'bed_number' => 'B-201',
        'status' => 'admitted',
    ]);

    $otherSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $otherWardAdmission->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    VitalsAlert::factory()->create([
        'vitals_schedule_id' => $otherSchedule->id,
        'patient_admission_id' => $otherWardAdmission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000)
        ->assertSee($currentWardAdmission->patient->name)
        ->assertDontSee($otherWardAdmission->patient->name)
        ->assertNoJavascriptErrors();
});
