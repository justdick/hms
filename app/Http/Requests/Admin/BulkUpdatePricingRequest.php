<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdatePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:insurance_plans,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', 'in:drug,lab,consultation,procedure'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.code' => ['required', 'string', 'max:191'],
            'items.*.is_mapped' => ['sometimes', 'boolean'],
            'copay' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Insurance plan is required.',
            'plan_id.exists' => 'Invalid insurance plan selected.',
            'items.required' => 'At least one item must be selected.',
            'items.array' => 'Items must be an array.',
            'items.min' => 'At least one item must be selected.',
            'items.*.type.required' => 'Item type is required for all items.',
            'items.*.type.in' => 'Invalid item type. Must be drug, lab, consultation, or procedure.',
            'items.*.id.required' => 'Item ID is required for all items.',
            'items.*.code.required' => 'Item code is required for all items.',
            'copay.required' => 'Copay amount is required.',
            'copay.numeric' => 'Copay must be a valid number.',
            'copay.min' => 'Copay cannot be negative.',
            'copay.max' => 'Copay cannot exceed 9,999,999.99.',
        ];
    }
}
