<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNhisTariffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nhis_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('nhis_tariffs', 'nhis_code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(['medicine', 'lab', 'procedure', 'consultation', 'consumable'])],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nhis_code.required' => 'NHIS code is required.',
            'nhis_code.unique' => 'An NHIS tariff with this code already exists.',
            'nhis_code.max' => 'NHIS code cannot exceed 50 characters.',
            'name.required' => 'Tariff name is required.',
            'name.max' => 'Tariff name cannot exceed 255 characters.',
            'category.required' => 'Category is required.',
            'category.in' => 'Invalid category. Must be one of: medicine, lab, procedure, consultation, consumable.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a number.',
            'price.min' => 'Price cannot be negative.',
            'unit.max' => 'Unit cannot exceed 50 characters.',
        ];
    }
}
