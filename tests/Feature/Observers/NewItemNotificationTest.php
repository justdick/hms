<?php

use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\LabService;
use App\Models\User;
use App\Notifications\NewItemAddedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create insurance admin role if it doesn't exist
    $role = \Spatie\Permission\Models\Role::firstOrCreate([
        'name' => 'insurance_admin',
        'guard_name' => 'web',
    ]);

    // Create insurance admin user
    $this->admin = User::factory()->create();
    $this->admin->assignRole('insurance_admin');
});

test('creating a drug notifies insurance admins when default coverage exists', function () {
    Notification::fake();

    // Create insurance plan with default drug coverage
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
        'require_explicit_approval_for_new_items' => false,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    // Create a new drug
    $drug = Drug::factory()->create([
        'name' => 'Paracetamol',
        'drug_code' => 'PARA001',
        'unit_price' => 50.00,
    ]);

    // Assert notification was sent
    Notification::assertSentTo(
        $this->admin,
        NewItemAddedNotification::class,
        function ($notification) use ($drug, $plan) {
            $data = $notification->toArray($this->admin);

            return $data['item_id'] === $drug->id
                && $data['item_code'] === $drug->drug_code
                && $data['item_name'] === $drug->name
                && $data['category'] === 'drug'
                && $data['plan_id'] === $plan->id
                && $data['default_coverage'] === 80.0;
        }
    );
});

test('creating a lab service notifies insurance admins when default coverage exists', function () {
    Notification::fake();

    // Create insurance plan with default lab coverage
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
        'require_explicit_approval_for_new_items' => false,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'lab',
        'item_code' => null,
        'coverage_value' => 90.00,
        'is_active' => true,
    ]);

    // Create a new lab service
    $labService = LabService::factory()->create([
        'name' => 'Complete Blood Count',
        'code' => 'CBC001',
        'price' => 100.00,
    ]);

    // Assert notification was sent
    Notification::assertSentTo(
        $this->admin,
        NewItemAddedNotification::class,
        function ($notification) use ($labService, $plan) {
            $data = $notification->toArray($this->admin);

            return $data['item_id'] === $labService->id
                && $data['item_code'] === $labService->code
                && $data['item_name'] === $labService->name
                && $data['category'] === 'lab'
                && $data['plan_id'] === $plan->id
                && $data['default_coverage'] === 90.0;
        }
    );
});

test('no notification sent when plan requires explicit approval', function () {
    Notification::fake();

    // Create insurance plan with explicit approval required
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
        'require_explicit_approval_for_new_items' => true,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    // Create a new drug
    Drug::factory()->create([
        'name' => 'Paracetamol',
        'drug_code' => 'PARA001',
        'unit_price' => 50.00,
    ]);

    // Assert no notification was sent
    Notification::assertNothingSent();
});

test('no notification sent when no default coverage exists', function () {
    Notification::fake();

    // Create insurance plan without default drug coverage
    $provider = InsuranceProvider::factory()->create();
    InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
        'require_explicit_approval_for_new_items' => false,
    ]);

    // Create a new drug
    Drug::factory()->create([
        'name' => 'Paracetamol',
        'drug_code' => 'PARA001',
        'unit_price' => 50.00,
    ]);

    // Assert no notification was sent
    Notification::assertNothingSent();
});

test('no notification sent when coverage rule is inactive', function () {
    Notification::fake();

    // Create insurance plan with inactive default drug coverage
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
        'require_explicit_approval_for_new_items' => false,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80.00,
        'is_active' => false,
    ]);

    // Create a new drug
    Drug::factory()->create([
        'name' => 'Paracetamol',
        'drug_code' => 'PARA001',
        'unit_price' => 50.00,
    ]);

    // Assert no notification was sent
    Notification::assertNothingSent();
});

test('notification sent to multiple insurance admins', function () {
    Notification::fake();

    // Create multiple insurance admin users
    $admin1 = User::factory()->create();
    $admin1->assignRole('insurance_admin');

    $admin2 = User::factory()->create();
    $admin2->assignRole('insurance_admin');

    // Create insurance plan with default drug coverage
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
        'require_explicit_approval_for_new_items' => false,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    // Create a new drug
    Drug::factory()->create([
        'name' => 'Paracetamol',
        'drug_code' => 'PARA001',
        'unit_price' => 50.00,
    ]);

    // Assert notification was sent to all admins
    Notification::assertSentTo([$this->admin, $admin1, $admin2], NewItemAddedNotification::class);
});

test('notification sent for multiple plans with default coverage', function () {
    Notification::fake();

    // Create two insurance plans with default drug coverage
    $provider = InsuranceProvider::factory()->create();

    $plan1 = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
        'plan_name' => 'Gold Plan',
        'require_explicit_approval_for_new_items' => false,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan1->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    $plan2 = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
        'plan_name' => 'Silver Plan',
        'require_explicit_approval_for_new_items' => false,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan2->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 60.00,
        'is_active' => true,
    ]);

    // Create a new drug
    $drug = Drug::factory()->create([
        'name' => 'Paracetamol',
        'drug_code' => 'PARA001',
        'unit_price' => 50.00,
    ]);

    // Assert notification was sent twice (once for each plan)
    Notification::assertSentTo(
        $this->admin,
        NewItemAddedNotification::class,
        function ($notification) use ($plan1) {
            $data = $notification->toArray($this->admin);

            return $data['plan_id'] === $plan1->id;
        }
    );

    Notification::assertSentTo(
        $this->admin,
        NewItemAddedNotification::class,
        function ($notification) use ($plan2) {
            $data = $notification->toArray($this->admin);

            return $data['plan_id'] === $plan2->id;
        }
    );
});

test('notification includes correct item price', function () {
    Notification::fake();

    // Create insurance plan with default drug coverage
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
        'require_explicit_approval_for_new_items' => false,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    // Create a new drug with specific price
    $drug = Drug::factory()->create([
        'name' => 'Expensive Drug',
        'drug_code' => 'EXP001',
        'unit_price' => 850.00,
    ]);

    // Assert notification includes price
    Notification::assertSentTo(
        $this->admin,
        NewItemAddedNotification::class,
        function ($notification) {
            $data = $notification->toArray($this->admin);

            return $data['item_price'] === 850.0;
        }
    );
});

test('notification includes action URLs', function () {
    Notification::fake();

    // Create insurance plan with default drug coverage
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
        'require_explicit_approval_for_new_items' => false,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    // Create a new drug
    $drug = Drug::factory()->create([
        'name' => 'Paracetamol',
        'drug_code' => 'PARA001',
        'unit_price' => 50.00,
    ]);

    // Assert notification includes action URLs
    Notification::assertSentTo(
        $this->admin,
        NewItemAddedNotification::class,
        function ($notification) use ($drug, $plan) {
            $data = $notification->toArray($this->admin);

            return isset($data['actions']['add_exception'])
                && str_contains($data['actions']['add_exception'], 'admin/insurance/coverage-rules/create')
                && str_contains($data['actions']['add_exception'], "plan_id={$plan->id}")
                && str_contains($data['actions']['add_exception'], 'category=drug')
                && str_contains($data['actions']['add_exception'], "item_code={$drug->drug_code}");
        }
    );
});
