<?php

use App\Models\Consultation;
use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create the permission
    Permission::firstOrCreate(['name' => 'consultations.filter-by-date', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'checkins.view-all', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'consultations.view-all', 'guard_name' => 'web']);

    // Create admin role with all permissions
    $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    $adminRole->syncPermissions(Permission::all());

    $this->department = Department::factory()->create();
    $this->patient = Patient::factory()->create();
});

describe('Consultation Date Filter Permission', function () {
    it('shows canFilterByDate as false for users without permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('checkins.view-all');
        $user->givePermissionTo('consultations.view-all');
        $this->department->users()->attach($user->id);

        $response = $this->actingAs($user)->get('/consultation');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Consultation/Index')
                ->where('canFilterByDate', false)
            );
    });

    it('shows canFilterByDate as true for users with permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('checkins.view-all');
        $user->givePermissionTo('consultations.view-all');
        $user->givePermissionTo('consultations.filter-by-date');
        $this->department->users()->attach($user->id);

        $response = $this->actingAs($user)->get('/consultation');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Consultation/Index')
                ->where('canFilterByDate', true)
            );
    });

    it('shows completed consultations from last 24 hours for users without permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('checkins.view-all');
        $user->givePermissionTo('consultations.view-all');
        $this->department->users()->attach($user->id);

        // Create a consultation completed 12 hours ago (should be visible)
        $recentCheckin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'status' => 'completed',
            'service_date' => now()->subHours(12),
        ]);
        $recentConsultation = Consultation::factory()->create([
            'patient_checkin_id' => $recentCheckin->id,
            'doctor_id' => $user->id,
            'status' => 'completed',
            'completed_at' => now()->subHours(12),
        ]);

        // Create a consultation completed 48 hours ago (should NOT be visible)
        $oldCheckin = PatientCheckin::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'department_id' => $this->department->id,
            'status' => 'completed',
            'service_date' => now()->subHours(48),
        ]);
        $oldConsultation = Consultation::factory()->create([
            'patient_checkin_id' => $oldCheckin->id,
            'doctor_id' => $user->id,
            'status' => 'completed',
            'completed_at' => now()->subHours(48),
        ]);

        $response = $this->actingAs($user)->get('/consultation');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('completedConsultations', 1)
                ->where('completedConsultations.0.id', $recentConsultation->id)
            );
    });

    it('allows users with permission to filter by date range', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('checkins.view-all');
        $user->givePermissionTo('consultations.view-all');
        $user->givePermissionTo('consultations.filter-by-date');
        $this->department->users()->attach($user->id);

        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();

        // Create a consultation from yesterday
        $yesterdayCheckin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'status' => 'completed',
            'service_date' => $yesterday,
        ]);
        $yesterdayConsultation = Consultation::factory()->create([
            'patient_checkin_id' => $yesterdayCheckin->id,
            'doctor_id' => $user->id,
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);

        // Create a consultation from today
        $todayCheckin = PatientCheckin::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'department_id' => $this->department->id,
            'status' => 'completed',
            'service_date' => $today,
        ]);
        $todayConsultation = Consultation::factory()->create([
            'patient_checkin_id' => $todayCheckin->id,
            'doctor_id' => $user->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Filter by yesterday only
        $response = $this->actingAs($user)->get("/consultation?date_from={$yesterday}&date_to={$yesterday}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.date_from', $yesterday)
                ->where('filters.date_to', $yesterday)
            );

        // Verify today's consultation is NOT in the results when filtering by yesterday
        $completedIds = collect($response->original->getData()['page']['props']['completedConsultations'])
            ->pluck('id')
            ->toArray();
        expect($completedIds)->toContain($yesterdayConsultation->id);
        expect($completedIds)->not->toContain($todayConsultation->id);
    });

    it('ignores date filter parameters for users without permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('checkins.view-all');
        $user->givePermissionTo('consultations.view-all');
        // NOT giving consultations.filter-by-date permission
        $this->department->users()->attach($user->id);

        $lastWeek = now()->subWeek()->toDateString();

        // Create a consultation from last week (outside 24 hours)
        $oldCheckin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'status' => 'completed',
            'service_date' => $lastWeek,
        ]);
        $oldConsultation = Consultation::factory()->create([
            'patient_checkin_id' => $oldCheckin->id,
            'doctor_id' => $user->id,
            'status' => 'completed',
            'completed_at' => now()->subWeek(),
        ]);

        // Try to filter by last week - should be ignored
        $response = $this->actingAs($user)->get("/consultation?date_from={$lastWeek}&date_to={$lastWeek}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canFilterByDate', false)
            );

        // The old consultation should NOT be in the results because user doesn't have permission
        // and the default behavior is last 24 hours
        $completedIds = collect($response->original->getData()['page']['props']['completedConsultations'])
            ->pluck('id')
            ->toArray();
        expect($completedIds)->not->toContain($oldConsultation->id);
    });
});
