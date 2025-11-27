<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGdrgTariffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('gdrg_tariffs', 'code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'mdc_category' => ['required', 'string', 'max:100'],
            'tariff_price' => ['required', 'numeric', 'min:0'],
            'age_category' => ['nullable', 'string', Rule::in(['adult', 'child', 'all'])],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'G-DRG code is required.',
            'code.unique' => 'A G-DRG tariff with this code already exists.',
            'code.max' => 'G-DRG code cannot exceed 20 characters.',
            'name.required' => 'Tariff name is required.',
            'name.max' => 'Tariff name cannot exceed 255 characters.',
            'mdc_category.required' => 'MDC category is required.',
            'mdc_category.max' => 'MDC category cannot exceed 100 characters.',
            'tariff_price.required' => 'Tariff price is required.',
            'tariff_price.numeric' => 'Tariff price must be a number.',
            'tariff_price.min' => 'Tariff price cannot be negative.',
            'age_category.in' => 'Invalid age category. Must be one of: adult, child, all.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set default age_category if not provided
        if (! $this->has('age_category') || $this->age_category === null) {
            $this->merge(['age_category' => 'all']);
        }

        // Set default is_active if not provided
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
