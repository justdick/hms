<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInsuranceCopayRequest extends FormRequest
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
            'copay' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
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
            'item_id.integer' => 'Item ID must be a valid integer.',
            'item_code.required' => 'Item code is required.',
            'copay.required' => 'Copay amount is required.',
            'copay.numeric' => 'Copay must be a valid number.',
            'copay.min' => 'Copay cannot be negative.',
            'copay.max' => 'Copay cannot exceed 9,999,999.99.',
        ];
    }
}
