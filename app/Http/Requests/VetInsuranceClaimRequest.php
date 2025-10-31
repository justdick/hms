<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VetInsuranceClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Diagnosis information
            'primary_diagnosis_code' => ['nullable', 'string', 'max:20'],
            'primary_diagnosis_description' => ['nullable', 'string', 'max:191'],
            'secondary_diagnoses' => ['nullable', 'array'],
            'secondary_diagnoses.*.code' => ['required', 'string', 'max:20'],
            'secondary_diagnoses.*.description' => ['required', 'string', 'max:191'],
            'c_drg_code' => ['nullable', 'string', 'max:50'],
            'hin_number' => ['nullable', 'string', 'max:191'],

            // Item approvals
            'item_approvals' => ['required', 'array', 'min:1'],
            'item_approvals.*.item_id' => ['required', 'exists:insurance_claim_items,id'],
            'item_approvals.*.is_approved' => ['required', 'boolean'],
            'item_approvals.*.rejection_reason' => ['nullable', 'required_if:item_approvals.*.is_approved,false', 'string', 'max:500'],

            // Additional notes
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_approvals.required' => 'At least one item must be approved or rejected.',
            'item_approvals.min' => 'At least one item must be approved or rejected.',
            'item_approvals.*.item_id.required' => 'Item ID is required.',
            'item_approvals.*.item_id.exists' => 'Invalid claim item.',
            'item_approvals.*.is_approved.required' => 'Approval status is required for each item.',
            'item_approvals.*.rejection_reason.required_if' => 'Rejection reason is required when item is rejected.',
        ];
    }
}
