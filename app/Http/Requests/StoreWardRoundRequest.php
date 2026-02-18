<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWardRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled by the controller's policy check
        // Just verify user is authenticated
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Round metadata
            'round_type' => ['nullable', 'in:daily_round,specialist_consult,procedure_note'],
            'round_datetime' => [
                'nullable',
                'date',
            ],

            // Clinical documentation fields (matching consultation structure)
            'presenting_complaint' => ['nullable', 'string', 'max:2000'],
            'history_presenting_complaint' => ['nullable', 'string', 'max:5000'],
            'on_direct_questioning' => ['nullable', 'string', 'max:2000'],
            'examination_findings' => ['nullable', 'string', 'max:5000'],
            'assessment_notes' => ['nullable', 'string', 'max:5000'],
            'plan_notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_date' => ['nullable', 'date', 'after:today'],

            // Patient history fields
            'past_medical_surgical_history' => ['nullable', 'string', 'max:10000'],
            'drug_history' => ['nullable', 'string', 'max:10000'],
            'family_history' => ['nullable', 'string', 'max:10000'],
            'social_history' => ['nullable', 'string', 'max:10000'],

            // Lab orders
            'lab_orders' => ['nullable', 'array'],
            'lab_orders.*.test_id' => ['required', 'exists:lab_services,id'],
            'lab_orders.*.notes' => ['nullable', 'string', 'max:500'],
            'lab_orders.*.priority' => ['nullable', 'in:routine,urgent,stat'],

            // Prescriptions
            'prescriptions' => ['nullable', 'array'],
            'prescriptions.*.drug_id' => ['required', 'exists:drugs,id'],
            'prescriptions.*.medication_name' => ['nullable', 'string', 'max:200'],
            'prescriptions.*.dosage' => ['nullable', 'string', 'max:100'],
            'prescriptions.*.frequency' => ['required', 'string', 'max:100'],
            'prescriptions.*.route' => ['required', 'string', 'max:50'],
            'prescriptions.*.duration' => ['nullable', 'string', 'max:100'],
            'prescriptions.*.instructions' => ['nullable', 'string', 'max:500'],

            // Diagnoses
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*.diagnosis_id' => ['required', 'exists:diagnoses,id'],
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
            'follow_up_date.after' => 'Follow-up date must be in the future.',

            'lab_orders.*.test_id.required' => 'Lab test is required.',
            'lab_orders.*.test_id.exists' => 'Invalid lab test selected.',

            'prescriptions.*.drug_id.required' => 'Drug is required.',
            'prescriptions.*.drug_id.exists' => 'Invalid drug selected.',
            'prescriptions.*.frequency.required' => 'Frequency is required.',
            'prescriptions.*.route.required' => 'Route is required.',

            'diagnoses.*.diagnosis_id.required' => 'Diagnosis is required.',
            'diagnoses.*.diagnosis_id.exists' => 'Invalid diagnosis selected.',
            'diagnoses.*.icd_code.required' => 'ICD code is required.',
            'diagnoses.*.diagnosis_name.required' => 'Diagnosis name is required.',
            'diagnoses.*.icd_version.in' => 'ICD version must be 10 or 11.',
        ];
    }
}
