<?php

namespace App\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'drug_id' => ['required', 'integer', 'exists:drugs,id'],
            'medication_name' => ['required', 'string', 'max:255'],
            'dose_quantity' => ['nullable', 'string', 'max:50'],
            'frequency' => ['required', 'string', 'max:100'],
            'duration' => ['required', 'string', 'max:100'],
            'quantity_to_dispense' => ['nullable', 'integer', 'min:1'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'drug_id.required' => 'Please select a drug.',
            'drug_id.exists' => 'The selected drug does not exist.',
            'medication_name.required' => 'Medication name is required.',
            'frequency.required' => 'Frequency is required.',
            'duration.required' => 'Duration is required.',
            'quantity_to_dispense.min' => 'Quantity to dispense must be at least 1.',
        ];
    }
}
