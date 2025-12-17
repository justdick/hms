<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCashPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'item_type' => ['required', 'string', 'in:drug,lab,consultation,procedure'],
            'item_id' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_type.required' => 'Item type is required.',
            'item_type.in' => 'Invalid item type. Must be drug, lab, consultation, or procedure.',
            'item_id.required' => 'Item ID is required.',
            'item_id.integer' => 'Item ID must be a valid integer.',
            'item_id.min' => 'Item ID must be a positive number.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a valid number.',
            'price.gt' => 'Price must be greater than zero.',
            'price.max' => 'Price cannot exceed 9,999,999.99.',
        ];
    }
}
