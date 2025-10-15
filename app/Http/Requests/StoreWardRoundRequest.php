<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWardRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ward_rounds.create');
    }

    public function rules(): array
    {
        return [
            // Round metadata
            'round_type' => ['nullable', 'in:daily_round,specialist_consult,procedure_note'],
            'round_datetime' => ['nullable', 'date', 'before_or_equal:now'],

            // Clinical documentation fields (matching consultation structure)
            'presenting_complaint' => ['nullable', 'string', 'max:2000'],
            'history_presenting_complaint' => ['nullable', 'string', 'max:5000'],
            'on_direct_questioning' => ['nullable', 'string', 'max:2000'],
            'examination_findings' => ['nullable', 'string', 'max:5000'],
            'assessment_notes' => ['required', 'string', 'min:10', 'max:5000'],
            'plan_notes' => ['nullable', 'string', 'max:5000'],

            // Patient status
            'patient_status' => ['required', 'in:improving,stable,deteriorating,discharge_ready,critical'],

            // Lab orders
            'lab_orders' => ['nullable', 'array'],
            'lab_orders.*.test_id' => ['required', 'exists:lab_tests,id'],
            'lab_orders.*.notes' => ['nullable', 'string', 'max:500'],
            'lab_orders.*.priority' => ['nullable', 'in:routine,urgent,stat'],

            // Prescriptions
            'prescriptions' => ['nullable', 'array'],
            'prescriptions.*.drug_id' => ['required', 'exists:drugs,id'],
            'prescriptions.*.dosage' => ['required', 'string', 'max:100'],
            'prescriptions.*.frequency' => ['required', 'string', 'max:100'],
            'prescriptions.*.route' => ['required', 'string', 'max:50'],
            'prescriptions.*.duration' => ['nullable', 'string', 'max:100'],
            'prescriptions.*.instructions' => ['nullable', 'string', 'max:500'],

            // Diagnoses
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*.icd_code' => ['required', 'string', 'max:20'],
            'diagnoses.*.icd_version' => ['required', 'in:10,11'],
            'diagnoses.*.diagnosis_name' => ['required', 'string', 'max:500'],
            'diagnoses.*.diagnosis_type' => ['nullable', 'in:working,complication,comorbidity'],
            'diagnoses.*.clinical_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'assessment_notes.required' => 'Clinical assessment is required.',
            'assessment_notes.min' => 'Clinical assessment must be at least 10 characters.',
            'assessment_notes.max' => 'Clinical assessment cannot exceed 5000 characters.',

            'patient_status.required' => 'Patient status is required.',
            'patient_status.in' => 'Invalid patient status.',

            'round_datetime.before_or_equal' => 'Ward round time cannot be in the future.',

            'lab_orders.*.test_id.required' => 'Lab test is required.',
            'lab_orders.*.test_id.exists' => 'Invalid lab test selected.',

            'prescriptions.*.drug_id.required' => 'Drug is required.',
            'prescriptions.*.drug_id.exists' => 'Invalid drug selected.',
            'prescriptions.*.dosage.required' => 'Dosage is required.',
            'prescriptions.*.frequency.required' => 'Frequency is required.',
            'prescriptions.*.route.required' => 'Route is required.',

            'diagnoses.*.icd_code.required' => 'ICD code is required.',
            'diagnoses.*.diagnosis_name.required' => 'Diagnosis name is required.',
            'diagnoses.*.icd_version.in' => 'ICD version must be 10 or 11.',
        ];
    }
}
