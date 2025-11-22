<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsuranceCoverageRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'insurance_plan_id' => ['required', 'exists:insurance_plans,id'],
            'coverage_category' => ['required', 'in:consultation,drug,lab,procedure,ward,nursing'],
            'item_code' => [
                'nullable',
                'string',
                'max:191',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $exists = \App\Models\InsuranceCoverageRule::where('insurance_plan_id', $this->insurance_plan_id)
                            ->where('coverage_category', $this->coverage_category)
                            ->where('item_code', $value)
                            ->exists();

                        if ($exists) {
                            $fail('This item already has a coverage exception. Please edit the existing exception instead.');
                        }
                    }
                },
            ],
            'item_description' => ['nullable', 'string', 'max:500'],
            'is_covered' => ['boolean'],
            'coverage_type' => ['required', 'in:percentage,fixed,full,excluded'],
            'coverage_value' => ['required_unless:coverage_type,excluded', 'numeric', 'min:0', 'max:9999999.99'],
            'patient_copay_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_quantity_per_visit' => ['nullable', 'integer', 'min:0'],
            'max_amount_per_visit' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'requires_preauthorization' => ['boolean'],
            'is_active' => ['boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'tariff_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'insurance_plan_id.required' => 'Insurance plan is required.',
            'insurance_plan_id.exists' => 'Invalid insurance plan selected.',
            'coverage_category.required' => 'Coverage category is required.',
            'coverage_category.in' => 'Invalid coverage category selected.',
            'coverage_type.required' => 'Coverage type is required.',
            'coverage_type.in' => 'Invalid coverage type selected.',
            'coverage_value.required_unless' => 'Coverage value is required for this coverage type.',
            'coverage_value.min' => 'Coverage value cannot be negative.',
            'patient_copay_percentage.min' => 'Copay percentage cannot be negative.',
            'patient_copay_percentage.max' => 'Copay percentage cannot exceed 100%.',
            'effective_to.after_or_equal' => 'End date must be on or after start date.',
        ];
    }
}
