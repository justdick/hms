<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInsuranceCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:insurance_plans,id'],
            'item_type' => ['required', 'string', 'in:drug,lab,consultation,procedure'],
            'item_id' => ['required', 'integer', 'min:1'],
            'item_code' => ['required', 'string', 'max:191'],
            'tariff_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'coverage_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'coverage_type' => ['nullable', 'string', 'in:percentage,fixed,full,excluded'],
            'patient_copay_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Insurance plan is required.',
            'plan_id.exists' => 'Invalid insurance plan selected.',
            'item_type.required' => 'Item type is required.',
            'item_type.in' => 'Invalid item type. Must be drug, lab, consultation, or procedure.',
            'item_id.required' => 'Item ID is required.',
            'item_code.required' => 'Item code is required.',
            'tariff_amount.numeric' => 'Tariff amount must be a valid number.',
            'tariff_amount.min' => 'Tariff amount cannot be negative.',
            'coverage_value.numeric' => 'Coverage value must be a valid number.',
            'coverage_value.min' => 'Coverage value cannot be negative.',
            'coverage_value.max' => 'Coverage value cannot exceed 100%.',
            'coverage_type.in' => 'Invalid coverage type.',
            'patient_copay_amount.numeric' => 'Copay amount must be a valid number.',
            'patient_copay_amount.min' => 'Copay amount cannot be negative.',
        ];
    }
}
