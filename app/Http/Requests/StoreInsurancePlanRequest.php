<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsurancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'insurance_provider_id' => ['required', 'exists:insurance_providers,id'],
            'plan_name' => ['required', 'string', 'max:191'],
            'plan_code' => ['required', 'string', 'max:50'],
            'plan_type' => ['required', 'in:individual,family,corporate'],
            'coverage_type' => ['required', 'in:inpatient,outpatient,comprehensive'],
            'annual_limit' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'visit_limit' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'default_copay_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'consultation_default' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'drugs_default' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'labs_default' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'procedures_default' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'requires_referral' => ['boolean'],
            'require_explicit_approval_for_new_items' => ['boolean'],
            'is_active' => ['boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'description' => ['nullable', 'string', 'max:10000'],
            'coverage_rules' => ['nullable', 'array'],
            'coverage_rules.*.coverage_category' => ['required', 'in:consultation,drug,lab,procedure,ward,nursing'],
            'coverage_rules.*.coverage_value' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'insurance_provider_id.required' => 'Insurance provider is required.',
            'insurance_provider_id.exists' => 'Invalid insurance provider selected.',
            'plan_name.required' => 'Plan name is required.',
            'plan_code.required' => 'Plan code is required.',
            'plan_type.required' => 'Plan type is required.',
            'plan_type.in' => 'Invalid plan type selected.',
            'coverage_type.required' => 'Coverage type is required.',
            'coverage_type.in' => 'Invalid coverage type selected.',
            'annual_limit.min' => 'Annual limit cannot be negative.',
            'visit_limit.min' => 'Visit limit cannot be negative.',
            'default_copay_percentage.min' => 'Copay percentage cannot be negative.',
            'default_copay_percentage.max' => 'Copay percentage cannot exceed 100%.',
            'effective_to.after_or_equal' => 'End date must be on or after start date.',
        ];
    }
}
