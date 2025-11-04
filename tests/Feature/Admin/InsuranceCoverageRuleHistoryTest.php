<?php

use App\Models\InsuranceCoverageRule;
use App\Models\InsuranceCoverageRuleHistory;
use App\Models\InsurancePlan;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('records history when a coverage rule is created', function () {
    $plan = InsurancePlan::factory()->create();

    $rule = InsuranceCoverageRule::create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_type' => 'percentage',
        'coverage_value' => 80,
        'patient_copay_percentage' => 20,
        'is_covered' => true,
        'is_active' => true,
    ]);

    $history = InsuranceCoverageRuleHistory::where('insurance_coverage_rule_id', $rule->id)
        ->where('action', 'created')
        ->first();

    expect($history)->not->toBeNull()
        ->and($history->user_id)->toBe($this->user->id)
        ->and($history->action)->toBe('created')
        ->and($history->new_values)->toBeArray()
        ->and((float) $history->new_values['coverage_value'])->toBe(80.0);
});

it('records history when a coverage rule is updated', function () {
    $plan = InsurancePlan::factory()->create();

    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'coverage_value' => 80,
        'patient_copay_percentage' => 20,
    ]);

    // Clear the created history
    InsuranceCoverageRuleHistory::where('insurance_coverage_rule_id', $rule->id)->delete();

    $rule->update([
        'coverage_value' => 90,
        'patient_copay_percentage' => 10,
    ]);

    $history = InsuranceCoverageRuleHistory::where('insurance_coverage_rule_id', $rule->id)
        ->where('action', 'updated')
        ->first();

    expect($history)->not->toBeNull()
        ->and($history->user_id)->toBe($this->user->id)
        ->and($history->action)->toBe('updated')
        ->and((float) $history->old_values['coverage_value'])->toBe(80.0)
        ->and((float) $history->new_values['coverage_value'])->toBe(90.0);
});

it('records history when a coverage rule is deleted', function () {
    $plan = InsurancePlan::factory()->create();

    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
    ]);

    $ruleId = $rule->id;

    // Clear created history to isolate the delete test
    InsuranceCoverageRuleHistory::where('insurance_coverage_rule_id', $ruleId)->delete();

    $rule->delete();

    // Check for history with null rule_id since it gets nulled on delete
    $history = InsuranceCoverageRuleHistory::whereNull('insurance_coverage_rule_id')
        ->where('action', 'deleted')
        ->where('user_id', $this->user->id)
        ->latest()
        ->first();

    expect($history)->not->toBeNull()
        ->and($history->user_id)->toBe($this->user->id)
        ->and($history->action)->toBe('deleted')
        ->and($history->old_values)->toBeArray();
});

it('can retrieve history for a coverage rule', function () {
    $plan = InsurancePlan::factory()->create();

    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'coverage_value' => 80,
    ]);

    // Clear created history
    InsuranceCoverageRuleHistory::where('insurance_coverage_rule_id', $rule->id)->delete();

    // Make multiple updates
    $rule->update(['coverage_value' => 85]);
    $rule->update(['coverage_value' => 90]);

    $response = $this->getJson("/admin/insurance/coverage-rules/{$rule->id}/history");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'history' => [
                '*' => [
                    'id',
                    'action',
                    'user',
                    'old_values',
                    'new_values',
                    'batch_id',
                    'created_at',
                ],
            ],
        ]);

    $history = $response->json('history');
    expect($history)->toHaveCount(2)
        ->and($history[0]['action'])->toBe('updated')
        ->and($history[1]['action'])->toBe('updated');
});

it('groups related changes with batch_id', function () {
    $plan = InsurancePlan::factory()->create();

    $batchId = 'batch-'.uniqid();
    session(['coverage_rule_batch_id' => $batchId]);

    $rule1 = InsuranceCoverageRule::create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'coverage_type' => 'percentage',
        'coverage_value' => 80,
        'patient_copay_percentage' => 20,
        'is_covered' => true,
        'is_active' => true,
    ]);

    $rule2 = InsuranceCoverageRule::create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'lab',
        'coverage_type' => 'percentage',
        'coverage_value' => 90,
        'patient_copay_percentage' => 10,
        'is_covered' => true,
        'is_active' => true,
    ]);

    $history1 = InsuranceCoverageRuleHistory::where('insurance_coverage_rule_id', $rule1->id)->first();
    $history2 = InsuranceCoverageRuleHistory::where('insurance_coverage_rule_id', $rule2->id)->first();

    expect($history1->batch_id)->toBe($batchId)
        ->and($history2->batch_id)->toBe($batchId);
});
