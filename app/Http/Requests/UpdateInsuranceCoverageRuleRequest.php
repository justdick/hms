<?php

namespace App\Http\Requests;

class UpdateInsuranceCoverageRuleRequest extends StoreInsuranceCoverageRuleRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // Override item_code validation to exclude current rule from duplicate check
        $rules['item_code'] = [
            'nullable',
            'string',
            'max:191',
            function ($attribute, $value, $fail) {
                if ($value) {
                    $exists = \App\Models\InsuranceCoverageRule::where('insurance_plan_id', $this->insurance_plan_id)
                        ->where('coverage_category', $this->coverage_category)
                        ->where('item_code', $value)
                        ->where('id', '!=', $this->route('coverageRule')->id)
                        ->exists();

                    if ($exists) {
                        $fail('This item already has a coverage exception. Please edit the existing exception instead.');
                    }
                }
            },
        ];

        return $rules;
    }
}
