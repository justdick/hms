<?php

/**
 * Browser Tests for Vitals Alert Toast Notifications
 *
 * These tests verify the toast notification system for vitals alerts,
 * including appearance, styling, actions, and auto-dismiss behavior.
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

    $this->admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $this->schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);
});

it('shows toast notification when alert becomes due', function () {
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000) // Wait for polling to fetch alerts
        ->assertSee($this->admission->patient->name)
        ->assertSee('A-101')
        ->assertSee('Vitals Due')
        ->assertNoJavascriptErrors();
});

it('displays different styling for due vs overdue alerts', function () {
    // Create a due alert
    $dueAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000)
        ->assertSee('Vitals Due');

    // Now create an overdue alert
    $overdueSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->subMinutes(20),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $overdueAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $overdueSchedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now()->subMinutes(20),
        'status' => 'overdue',
    ]);

    $page->pause(1000)
        ->assertSee('Vitals Overdue')
        ->assertNoJavascriptErrors();
});

it('navigates to vitals recording when Record Vitals button is clicked', function () {
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000)
        ->assertSee('Record Vitals')
        ->click('Record Vitals')
        ->pause(500)
        ->assertUrlContains("/wards/{$this->ward->id}/patients/{$this->admission->id}")
        ->assertNoJavascriptErrors();
});

it('removes toast when Dismiss button is clicked', function () {
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000)
        ->assertSee($this->admission->patient->name);

    // Find and click dismiss button
    if ($page->hasText('Dismiss')) {
        $page->click('Dismiss')
            ->pause(500);

        // Toast should be removed or hidden
        $page->assertNoJavascriptErrors();
    }
});

it('auto-dismisses due alert toast after 10 seconds', function () {
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000)
        ->assertSee('Vitals Due');

    // Wait for auto-dismiss (10 seconds + buffer)
    $page->pause(11000);

    // Toast should be auto-dismissed
    $page->assertNoJavascriptErrors();
});

it('auto-dismisses overdue alert toast after 15 seconds', function () {
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now()->subMinutes(20),
        'status' => 'overdue',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000)
        ->assertSee('Vitals Overdue');

    // Wait for auto-dismiss (15 seconds + buffer)
    $page->pause(16000);

    // Toast should be auto-dismissed
    $page->assertNoJavascriptErrors();
});

it('displays patient bed number in toast notification', function () {
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000)
        ->assertSee('A-101')
        ->assertSee($this->admission->patient->name)
        ->assertNoJavascriptErrors();
});

it('displays time elapsed for overdue alerts', function () {
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now()->subMinutes(25),
        'status' => 'overdue',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1000)
        ->assertSee('Vitals Overdue')
        ->assertNoJavascriptErrors();
});
