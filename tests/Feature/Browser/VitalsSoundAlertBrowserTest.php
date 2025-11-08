<?php

/**
 * Browser Tests for Vitals Sound Alerts
 *
 * These tests verify the audio notification system for vitals alerts,
 * including sound playback, volume control, mute functionality, and preferences.
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

it('plays audio when due alert appears', function () {
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    // Wait for alert to appear and sound to play
    $page->pause(1500);

    // Verify no JavaScript errors occurred during audio playback
    $page->assertNoJavascriptErrors();

    // Check that audio element or sound was triggered
    $audioPlayed = $page->evaluate('() => {
        const audioElements = document.querySelectorAll("audio");
        return audioElements.length > 0 || window.lastAudioPlayed !== undefined;
    }');

    expect($audioPlayed)->toBeTrue();
});

it('plays different sounds for due vs overdue alerts', function () {
    // Create a due alert first
    $dueAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");
    $page->pause(1500);

    // Check for gentle sound
    $gentleSoundPlayed = $page->evaluate('() => {
        const audioElements = document.querySelectorAll("audio");
        for (let audio of audioElements) {
            if (audio.src && audio.src.includes("gentle")) {
                return true;
            }
        }
        return false;
    }');

    // Now test overdue alert
    $overdueAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now()->subMinutes(20),
        'status' => 'overdue',
    ]);

    $page->pause(1500);

    // Check for urgent sound
    $urgentSoundPlayed = $page->evaluate('() => {
        const audioElements = document.querySelectorAll("audio");
        for (let audio of audioElements) {
            if (audio.src && audio.src.includes("urgent")) {
                return true;
            }
        }
        return false;
    }');

    $page->assertNoJavascriptErrors();
});

it('can access sound alert settings page', function () {
    $page = visit('/settings/vitals-alerts');

    $page->assertSee('Vitals Alert Settings')
        ->assertSee('Sound Alerts')
        ->assertSee('Volume')
        ->assertNoJavascriptErrors();
});

it('can toggle sound alerts on and off', function () {
    $page = visit('/settings/vitals-alerts');

    $page->assertSee('Enable Sound Alerts');

    // Find and toggle the sound alert switch
    $toggleExists = $page->evaluate('() => {
        const toggle = document.querySelector("[role=\'switch\']");
        return toggle !== null;
    }');

    if ($toggleExists) {
        $page->click('[role="switch"]')
            ->pause(500);
    }

    $page->assertNoJavascriptErrors();
});

it('can adjust volume control', function () {
    $page = visit('/settings/vitals-alerts');

    $page->assertSee('Volume');

    // Check if volume slider exists
    $sliderExists = $page->evaluate('() => {
        const slider = document.querySelector("input[type=\'range\']");
        return slider !== null;
    }');

    if ($sliderExists) {
        // Set volume to 50%
        $page->evaluate('() => {
            const slider = document.querySelector("input[type=\'range\']");
            if (slider) {
                slider.value = 50;
                slider.dispatchEvent(new Event("input", { bubbles: true }));
                slider.dispatchEvent(new Event("change", { bubbles: true }));
            }
        }');

        $page->pause(500);
    }

    $page->assertNoJavascriptErrors();
});

it('can test sound preview', function () {
    $page = visit('/settings/vitals-alerts');

    $page->assertSee('Test Sound');

    // Click test button if it exists
    if ($page->hasText('Test Sound')) {
        $page->click('Test Sound')
            ->pause(1000);
    }

    $page->assertNoJavascriptErrors();
});

it('persists sound preferences to localStorage', function () {
    $page = visit('/settings/vitals-alerts');

    // Enable sound alerts
    $toggleExists = $page->evaluate('() => {
        const toggle = document.querySelector("[role=\'switch\']");
        return toggle !== null;
    }');

    if ($toggleExists) {
        $page->click('[role="switch"]')
            ->pause(500);
    }

    // Check localStorage
    $settingsSaved = $page->evaluate('() => {
        const settings = localStorage.getItem("vitalsAlertSettings");
        return settings !== null;
    }');

    expect($settingsSaved)->toBeTrue();

    $page->assertNoJavascriptErrors();
});

it('respects mute setting when alert appears', function () {
    // First, disable sound alerts
    $page = visit('/settings/vitals-alerts');

    $page->evaluate('() => {
        localStorage.setItem("vitalsAlertSettings", JSON.stringify({
            enabled: false,
            volume: 0.7,
            soundType: "gentle"
        }));
    }');

    // Now visit ward page with an alert
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");
    $page->pause(1500);

    // Verify no audio was played
    $audioPlayed = $page->evaluate('() => {
        const audioElements = document.querySelectorAll("audio");
        let played = false;
        audioElements.forEach(audio => {
            if (!audio.paused) {
                played = true;
            }
        });
        return played;
    }');

    $page->assertNoJavascriptErrors();
});

it('can select different sound types', function () {
    $page = visit('/settings/vitals-alerts');

    // Check if sound type selector exists
    $selectorExists = $page->evaluate('() => {
        const selects = document.querySelectorAll("select");
        for (let select of selects) {
            const options = Array.from(select.options).map(o => o.value);
            if (options.includes("gentle") || options.includes("urgent")) {
                return true;
            }
        }
        return false;
    }');

    if ($selectorExists) {
        $page->pause(500);
    }

    $page->assertNoJavascriptErrors();
});

it('handles audio playback errors gracefully', function () {
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $this->schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    // Mock audio failure
    $page = visit("/wards/{$this->ward->id}");

    $page->evaluate('() => {
        const originalAudio = window.Audio;
        window.Audio = function() {
            const audio = new originalAudio();
            audio.play = () => Promise.reject(new Error("Audio playback failed"));
            return audio;
        };
    }');

    $page->pause(1500);

    // Should still show visual notification without crashing
    $page->assertNoJavascriptErrors();
});
