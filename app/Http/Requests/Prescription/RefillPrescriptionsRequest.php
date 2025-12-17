<?php

namespace App\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;

class RefillPrescriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prescription_ids' => ['required', 'array', 'min:1'],
            'prescription_ids.*' => ['required', 'integer', 'exists:prescriptions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'prescription_ids.required' => 'Please select at least one prescription to refill.',
            'prescription_ids.min' => 'Please select at least one prescription to refill.',
            'prescription_ids.*.exists' => 'One or more selected prescriptions do not exist.',
        ];
    }
}
