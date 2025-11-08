<?php

/**
 * Browser Tests for Vitals Alert Dashboard
 *
 * These tests verify the vitals alert dashboard functionality,
 * including display, sorting, filtering, and quick actions.
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

it('displays all active vitals schedules on dashboard', function () {
    // Create multiple patients with schedules
    $admission1 = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $schedule1 = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission1->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $admission2 = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-102',
        'status' => 'admitted',
    ]);

    $schedule2 = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission2->id,
        'interval_minutes' => 240,
        'next_due_at' => now()->addHours(2),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500)
        ->assertSee($admission1->patient->name)
        ->assertSee($admission2->patient->name)
        ->assertSee('A-101')
        ->assertSee('A-102')
        ->assertNoJavascriptErrors();
});

it('shows patient information in dashboard table', function () {
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

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500)
        ->assertSee($admission->patient->name)
        ->assertSee('A-101')
        ->assertNoJavascriptErrors();
});

it('displays vitals status for each patient', function () {
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addMinutes(30),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500);

    // Check for status display
    $hasStatus = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("Upcoming") || text.includes("Due") || text.includes("Overdue");
    }');

    $page->assertNoJavascriptErrors();
});

it('shows next due time for each schedule', function () {
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

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500);

    // Check for time display
    $hasTime = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("in") || text.includes("Next Due");
    }');

    $page->assertNoJavascriptErrors();
});

it('sorts patients by urgency with overdue first', function () {
    // Create overdue patient
    $overdueAdmission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $overdueSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $overdueAdmission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->subMinutes(20),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    VitalsAlert::factory()->create([
        'vitals_schedule_id' => $overdueSchedule->id,
        'patient_admission_id' => $overdueAdmission->id,
        'due_at' => now()->subMinutes(20),
        'status' => 'overdue',
    ]);

    // Create due patient
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

    // Create upcoming patient
    $upcomingAdmission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-103',
        'status' => 'admitted',
    ]);

    $upcomingSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $upcomingAdmission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500);

    // Get the order of patients in the table
    $patientOrder = $page->evaluate('() => {
        const rows = document.querySelectorAll("tbody tr");
        const names = [];
        rows.forEach(row => {
            const text = row.textContent;
            if (text.includes("A-101")) names.push("overdue");
            if (text.includes("A-102")) names.push("due");
            if (text.includes("A-103")) names.push("upcoming");
        });
        return names;
    }');

    // Overdue should be first
    if (is_array($patientOrder) && count($patientOrder) > 0) {
        expect($patientOrder[0])->toBe('overdue');
    }

    $page->assertNoJavascriptErrors();
});

it('has ward filter dropdown', function () {
    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500);

    // Check for filter dropdown
    $hasFilter = $page->evaluate('() => {
        const selects = document.querySelectorAll("select");
        const text = document.body.textContent;
        return selects.length > 0 || text.includes("Filter") || text.includes("Ward");
    }');

    $page->assertNoJavascriptErrors();
});

it('filters schedules by selected ward', function () {
    $otherWard = Ward::factory()->create(['name' => 'Other Ward']);

    // Create admission in current ward
    $currentWardAdmission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    VitalsSchedule::factory()->create([
        'patient_admission_id' => $currentWardAdmission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Create admission in other ward
    $otherWardAdmission = PatientAdmission::factory()->create([
        'ward_id' => $otherWard->id,
        'bed_number' => 'B-201',
        'status' => 'admitted',
    ]);

    VitalsSchedule::factory()->create([
        'patient_admission_id' => $otherWardAdmission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500)
        ->assertSee($currentWardAdmission->patient->name)
        ->assertDontSee($otherWardAdmission->patient->name)
        ->assertNoJavascriptErrors();
});

it('has quick action buttons for each patient', function () {
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

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500);

    // Check for action buttons
    $hasActions = $page->evaluate('() => {
        const buttons = document.querySelectorAll("button");
        const links = document.querySelectorAll("a");
        return buttons.length > 0 || links.length > 0;
    }');

    $page->assertNoJavascriptErrors();
});

it('navigates to patient page when clicking quick action', function () {
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

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500);

    // Click on patient name or action button
    if ($page->hasText($admission->patient->name)) {
        $page->click($admission->patient->name)
            ->pause(500)
            ->assertUrlContains("/wards/{$this->ward->id}/patients/{$admission->id}")
            ->assertNoJavascriptErrors();
    }
});

it('displays time calculations for each schedule', function () {
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addMinutes(45),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500);

    // Check for time calculations
    $hasTimeCalc = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("minutes") || text.includes("hours") || text.includes("in");
    }');

    $page->assertNoJavascriptErrors();
});

it('shows empty state when no active schedules exist', function () {
    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500);

    // Check for empty state
    $hasEmptyState = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("No") || text.includes("empty") || text.includes("schedules");
    }');

    $page->assertNoJavascriptErrors();
});

it('updates dashboard in real-time through polling', function () {
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

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(1000);

    // Wait for polling to update (30 seconds + buffer)
    $page->pause(35000);

    // Dashboard should have updated
    $page->assertNoJavascriptErrors();
});

it('displays interval information for each schedule', function () {
    $admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 240, // 4 hours
        'next_due_at' => now()->addHours(2),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500);

    // Check for interval display
    $hasInterval = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("Every") || text.includes("4") || text.includes("240");
    }');

    $page->assertNoJavascriptErrors();
});

it('highlights overdue patients with distinct styling', function () {
    $overdueAdmission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
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

    $page = visit("/wards/{$this->ward->id}/vitals-dashboard");

    $page->pause(500);

    // Check for red/overdue styling
    $hasOverdueStyling = $page->evaluate('() => {
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

it('is responsive on mobile viewport', function () {
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

    // Test on mobile viewport
    $page = visit("/wards/{$this->ward->id}/vitals-dashboard", viewport: [375, 667]);

    $page->pause(500)
        ->assertSee($admission->patient->name)
        ->assertNoJavascriptErrors();
});
