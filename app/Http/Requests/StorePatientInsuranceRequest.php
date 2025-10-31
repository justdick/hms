<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientInsuranceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'insurance_plan_id' => ['required', 'exists:insurance_plans,id'],
            'membership_id' => ['required', 'string', 'max:191'],
            'policy_number' => ['nullable', 'string', 'max:191'],
            'folder_id_prefix' => ['nullable', 'string', 'max:50'],
            'is_dependent' => ['boolean'],
            'principal_member_name' => ['nullable', 'required_if:is_dependent,true', 'string', 'max:191'],
            'relationship_to_principal' => [
                'nullable',
                'required_if:is_dependent,true',
                'in:self,spouse,child,parent,other',
            ],
            'coverage_start_date' => ['required', 'date'],
            'coverage_end_date' => ['nullable', 'date', 'after:coverage_start_date'],
            'status' => ['nullable', 'in:active,expired,suspended,cancelled'],
            'card_number' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient is required.',
            'patient_id.exists' => 'Invalid patient selected.',
            'insurance_plan_id.required' => 'Insurance plan is required.',
            'insurance_plan_id.exists' => 'Invalid insurance plan selected.',
            'membership_id.required' => 'Membership ID is required.',
            'principal_member_name.required_if' => 'Principal member name is required for dependents.',
            'relationship_to_principal.required_if' => 'Relationship to principal is required for dependents.',
            'relationship_to_principal.in' => 'Invalid relationship selected.',
            'coverage_start_date.required' => 'Coverage start date is required.',
            'coverage_end_date.after' => 'Coverage end date must be after start date.',
            'status.in' => 'Invalid status selected.',
        ];
    }
}
