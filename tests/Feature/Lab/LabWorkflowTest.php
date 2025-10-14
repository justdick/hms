<?php

use App\Models\Consultation;
use App\Models\LabOrder;
use App\Models\LabService;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('lab technician can view lab dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('lab.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Lab/Index')
            ->has('labOrders')
            ->has('stats')
            ->has('categories')
        );
});

test('lab dashboard shows correct stats', function () {
    $user = User::factory()->create();
    $labService = LabService::factory()->create();

    // Create lab orders with different statuses
    $consultation = Consultation::factory()->create();

    LabOrder::factory()->create([
        'consultation_id' => $consultation->id,
        'lab_service_id' => $labService->id,
        'status' => 'ordered',
        'ordered_by' => $user->id,
    ]);

    LabOrder::factory()->create([
        'consultation_id' => $consultation->id,
        'lab_service_id' => $labService->id,
        'status' => 'sample_collected',
        'ordered_by' => $user->id,
    ]);

    LabOrder::factory()->create([
        'consultation_id' => $consultation->id,
        'lab_service_id' => $labService->id,
        'status' => 'completed',
        'ordered_by' => $user->id,
        'result_entered_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('lab.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.ordered', 1)
            ->where('stats.sample_collected', 1)
            ->where('stats.completed_today', 1)
        );
});

test('can view individual lab order', function () {
    $user = User::factory()->create();
    $labOrder = LabOrder::factory()->create([
        'ordered_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('lab.orders.show', $labOrder));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Lab/Show')
            ->has('labOrder')
        );
});

test('can collect sample for ordered test', function () {
    $user = User::factory()->create();
    $labOrder = LabOrder::factory()->create([
        'status' => 'ordered',
        'ordered_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->patch(route('lab.orders.collect-sample', $labOrder));

    $response->assertRedirect();

    $labOrder->refresh();
    expect($labOrder->status)->toBe('sample_collected');
    expect($labOrder->sample_collected_at)->not->toBeNull();
});

test('can start processing test with collected sample', function () {
    $user = User::factory()->create();
    $labOrder = LabOrder::factory()->create([
        'status' => 'sample_collected',
        'ordered_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->patch(route('lab.orders.start-processing', $labOrder));

    $response->assertRedirect();

    $labOrder->refresh();
    expect($labOrder->status)->toBe('in_progress');
});

test('can complete test with results', function () {
    $user = User::factory()->create();
    $labOrder = LabOrder::factory()->create([
        'status' => 'in_progress',
        'ordered_by' => $user->id,
    ]);

    $resultData = [
        'hemoglobin' => '12.5 g/dL',
        'white_blood_cells' => '7500 /μL',
        'platelets' => '250000 /μL',
    ];

    $resultNotes = 'All values within normal range.';

    $response = $this->actingAs($user)
        ->patch(route('lab.orders.complete', $labOrder), [
            'result_values' => $resultData,
            'result_notes' => $resultNotes,
        ]);

    $response->assertRedirect(route('lab.index'));

    $labOrder->refresh();
    expect($labOrder->status)->toBe('completed');
    expect($labOrder->result_values)->toBe($resultData);
    expect($labOrder->result_notes)->toBe($resultNotes);
    expect($labOrder->result_entered_at)->not->toBeNull();
});

test('can cancel lab order with reason', function () {
    $user = User::factory()->create();
    $labOrder = LabOrder::factory()->create([
        'status' => 'ordered',
        'ordered_by' => $user->id,
    ]);

    $reason = 'Patient did not show up for sample collection';

    $response = $this->actingAs($user)
        ->patch(route('lab.orders.cancel', $labOrder), [
            'reason' => $reason,
        ]);

    $response->assertRedirect();

    $labOrder->refresh();
    expect($labOrder->status)->toBe('cancelled');
    expect($labOrder->result_notes)->toBe('Cancelled: '.$reason);
});

test('cannot collect sample from non-ordered test', function () {
    $user = User::factory()->create();
    $labOrder = LabOrder::factory()->create([
        'status' => 'completed',
        'ordered_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->patch(route('lab.orders.collect-sample', $labOrder));

    $response->assertRedirect()
        ->assertSessionHas('error');
});

test('cannot complete test that is not in progress', function () {
    $user = User::factory()->create();
    $labOrder = LabOrder::factory()->create([
        'status' => 'ordered',
        'ordered_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->patch(route('lab.orders.complete', $labOrder), [
            'result_values' => ['test' => 'value'],
            'result_notes' => 'Test notes',
        ]);

    $response->assertRedirect()
        ->assertSessionHas('error');
});

test('lab orders are displayed in consultation', function () {
    $user = User::factory()->create();
    $consultation = Consultation::factory()->create();

    $labOrder = LabOrder::factory()->create([
        'consultation_id' => $consultation->id,
        'status' => 'completed',
        'ordered_by' => $user->id,
        'result_values' => ['hemoglobin' => '13.2 g/dL'],
        'result_notes' => 'Normal hemoglobin levels',
        'result_entered_at' => now(),
    ]);

    $response = $this->actingAs($user)->get("/consultation/{$consultation->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('consultation.lab_orders', 1)
            ->where('consultation.lab_orders.0.status', 'completed')
            ->where('consultation.lab_orders.0.result_values.hemoglobin', '13.2 g/dL')
            ->where('consultation.lab_orders.0.result_notes', 'Normal hemoglobin levels')
        );
});

test('can filter lab orders by status', function () {
    $user = User::factory()->create();
    $consultation = Consultation::factory()->create();
    $labService = LabService::factory()->create();

    // Create orders with different statuses
    LabOrder::factory()->create([
        'consultation_id' => $consultation->id,
        'lab_service_id' => $labService->id,
        'status' => 'ordered',
        'ordered_by' => $user->id,
    ]);

    LabOrder::factory()->create([
        'consultation_id' => $consultation->id,
        'lab_service_id' => $labService->id,
        'status' => 'completed',
        'ordered_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('lab.index', ['status' => 'completed']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('labOrders.data', 1)
            ->where('labOrders.data.0.status', 'completed')
        );
});

test('can search lab orders by patient name', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $consultation = Consultation::factory()
        ->for(PatientCheckin::factory()->for($patient)->create())
        ->create();

    LabOrder::factory()->create([
        'consultation_id' => $consultation->id,
        'ordered_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('lab.index', ['search' => 'John']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('labOrders.data', 1)
        );
});
