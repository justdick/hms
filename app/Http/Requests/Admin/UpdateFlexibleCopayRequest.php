<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFlexibleCopayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:insurance_plans,id'],
            'item_type' => ['required', 'string', 'in:drug,lab,consultation,procedure'],
            'item_id' => ['required', 'integer'],
            'item_code' => ['required', 'string'],
            'copay_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'An insurance plan must be selected.',
            'plan_id.exists' => 'The selected insurance plan does not exist.',
            'item_type.required' => 'The item type is required.',
            'item_type.in' => 'The item type must be one of: drug, lab, consultation, procedure.',
            'item_id.required' => 'The item ID is required.',
            'item_code.required' => 'The item code is required.',
            'copay_amount.numeric' => 'The copay amount must be a number.',
            'copay_amount.min' => 'The copay amount cannot be negative.',
        ];
    }
}
