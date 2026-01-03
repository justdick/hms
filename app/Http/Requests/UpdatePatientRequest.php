<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('patient'));
    }

    public function rules(): array
    {
        $hasInsurance = $this->boolean('has_insurance');

        return [
            // Patient basic information
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'past_medical_surgical_history' => ['nullable', 'string'],
            'drug_history' => ['nullable', 'string'],
            'family_history' => ['nullable', 'string'],
            'social_history' => ['nullable', 'string'],

            // Insurance information (conditional based on has_insurance)
            'has_insurance' => ['boolean'],
            'insurance_plan_id' => $hasInsurance
                ? ['required', 'exists:insurance_plans,id']
                : ['nullable', 'exists:insurance_plans,id'],
            'membership_id' => $hasInsurance
                ? ['required', 'string', 'max:191']
                : ['nullable', 'string', 'max:191'],
            'policy_number' => ['nullable', 'string', 'max:191'],
            'card_number' => ['nullable', 'string', 'max:191'],
            'is_dependent' => ['boolean'],
            'principal_member_name' => ['nullable', 'required_if:is_dependent,true', 'string', 'max:191'],
            'relationship_to_principal' => [
                'nullable',
                'required_if:is_dependent,true',
                'in:self,spouse,child,parent,other',
            ],
            'coverage_start_date' => $hasInsurance
                ? ['required', 'date']
                : ['nullable', 'date'],
            'coverage_end_date' => ['nullable', 'date', 'after_or_equal:coverage_start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Invalid gender selected.',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.before' => 'Date of birth must be before today.',

            'insurance_plan_id.required' => 'Insurance plan is required when patient has insurance.',
            'insurance_plan_id.exists' => 'Invalid insurance plan selected.',
            'membership_id.required' => 'Membership ID is required when patient has insurance.',
            'principal_member_name.required_if' => 'Principal member name is required for dependents.',
            'relationship_to_principal.required_if' => 'Relationship to principal is required for dependents.',
            'relationship_to_principal.in' => 'Invalid relationship selected.',
            'coverage_start_date.required' => 'Coverage start date is required when patient has insurance.',
            'coverage_end_date.after_or_equal' => 'Coverage end date must be on or after start date.',
        ];
    }
}
