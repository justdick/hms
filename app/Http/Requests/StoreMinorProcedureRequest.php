<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMinorProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'patient_checkin_id' => ['required', 'exists:patient_checkins,id'],
            'minor_procedure_type_id' => ['required', 'exists:minor_procedure_types,id'],
            'procedure_notes' => ['nullable', 'string', 'min:2'],
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*' => ['exists:diagnoses,id'],
            'supplies' => ['nullable', 'array'],
            'supplies.*.drug_id' => ['required', 'exists:drugs,id'],
            'supplies.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_checkin_id.required' => 'Patient check-in is required.',
            'patient_checkin_id.exists' => 'Invalid patient check-in selected.',
            'minor_procedure_type_id.required' => 'Procedure type is required.',
            'minor_procedure_type_id.exists' => 'Invalid procedure type selected.',
            'procedure_notes.min' => 'Procedure notes must be at least 10 characters when provided.',
            'diagnoses.array' => 'Diagnoses must be an array.',
            'diagnoses.*.exists' => 'One or more selected diagnoses are invalid.',
            'supplies.array' => 'Supplies must be an array.',
            'supplies.*.drug_id.required' => 'Drug is required for each supply.',
            'supplies.*.drug_id.exists' => 'One or more selected drugs are invalid.',
            'supplies.*.quantity.required' => 'Quantity is required for each supply.',
            'supplies.*.quantity.numeric' => 'Quantity must be a number.',
            'supplies.*.quantity.min' => 'Quantity must be at least 0.01.',
        ];
    }
}
