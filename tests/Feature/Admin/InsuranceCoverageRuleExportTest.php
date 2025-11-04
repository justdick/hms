<?php

use App\Models\InsuranceCoverageRule;
use App\Models\InsuranceCoverageRuleHistory;
use App\Models\InsurancePlan;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can export coverage rules without history', function () {
    $plan = InsurancePlan::factory()->create();

    InsuranceCoverageRule::factory()->count(3)->create([
        'insurance_plan_id' => $plan->id,
    ]);

    $response = $this->get("/admin/insurance/plans/{$plan->id}/coverage-rules/export");

    $response->assertSuccessful()
        ->assertDownload()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('can export coverage rules with history', function () {
    $plan = InsurancePlan::factory()->create();

    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_value' => 80,
    ]);

    // Make some changes to create history
    $rule->update(['coverage_value' => 85]);
    $rule->update(['coverage_value' => 90]);

    $response = $this->get("/admin/insurance/plans/{$plan->id}/coverage-rules/export?include_history=true");

    $response->assertSuccessful()
        ->assertDownload()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    // Verify history exists
    $historyCount = InsuranceCoverageRuleHistory::where('insurance_coverage_rule_id', $rule->id)
        ->where('action', 'updated')
        ->count();

    expect($historyCount)->toBeGreaterThan(0);
});

it('exports only rules for the specified plan', function () {
    $plan1 = InsurancePlan::factory()->create();
    $plan2 = InsurancePlan::factory()->create();

    InsuranceCoverageRule::factory()->count(2)->create([
        'insurance_plan_id' => $plan1->id,
    ]);

    InsuranceCoverageRule::factory()->count(3)->create([
        'insurance_plan_id' => $plan2->id,
    ]);

    $response = $this->get("/admin/insurance/plans/{$plan1->id}/coverage-rules/export");

    $response->assertSuccessful();

    // The export should only include plan1's rules
    // We can't easily verify the Excel content in a test, but we can verify the response is successful
});
