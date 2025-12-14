<?php

namespace App\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;

class ParsePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'input' => ['required', 'string', 'max:500'],
            'drug_id' => ['nullable', 'integer', 'exists:drugs,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'input.required' => 'Prescription input is required.',
            'input.max' => 'Prescription input must not exceed 500 characters.',
            'drug_id.exists' => 'The selected drug does not exist.',
        ];
    }
}
