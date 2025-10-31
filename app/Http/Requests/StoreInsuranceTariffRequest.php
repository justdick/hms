<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInsuranceTariffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'insurance_plan_id' => ['required', 'exists:insurance_plans,id'],
            'item_type' => ['required', 'in:drug,service,lab,procedure,ward'],
            'item_code' => ['required', 'string', 'max:191'],
            'item_description' => ['nullable', 'string', 'max:500'],
            'standard_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'insurance_tariff' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'insurance_plan_id.required' => 'Insurance plan is required.',
            'insurance_plan_id.exists' => 'Invalid insurance plan selected.',
            'item_type.required' => 'Item type is required.',
            'item_type.in' => 'Invalid item type selected.',
            'item_code.required' => 'Item code is required.',
            'standard_price.required' => 'Standard price is required.',
            'standard_price.min' => 'Standard price cannot be negative.',
            'insurance_tariff.required' => 'Insurance tariff is required.',
            'insurance_tariff.min' => 'Insurance tariff cannot be negative.',
            'effective_from.required' => 'Effective from date is required.',
            'effective_to.after_or_equal' => 'End date must be on or after start date.',
        ];
    }
}
