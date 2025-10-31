<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
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

            // Insurance information (optional)
            'has_insurance' => ['boolean'],
            'insurance_plan_id' => ['nullable', 'required_if:has_insurance,true', 'exists:insurance_plans,id'],
            'membership_id' => ['nullable', 'required_if:has_insurance,true', 'string', 'max:191'],
            'policy_number' => ['nullable', 'string', 'max:191'],
            'card_number' => ['nullable', 'string', 'max:191'],
            'is_dependent' => ['boolean'],
            'principal_member_name' => ['nullable', 'required_if:is_dependent,true', 'string', 'max:191'],
            'relationship_to_principal' => [
                'nullable',
                'required_if:is_dependent,true',
                'in:self,spouse,child,parent,other',
            ],
            'coverage_start_date' => ['nullable', 'required_if:has_insurance,true', 'date'],
            'coverage_end_date' => ['nullable', 'date', 'after:coverage_start_date'],
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

            'insurance_plan_id.required_if' => 'Insurance plan is required when patient has insurance.',
            'insurance_plan_id.exists' => 'Invalid insurance plan selected.',
            'membership_id.required_if' => 'Membership ID is required when patient has insurance.',
            'principal_member_name.required_if' => 'Principal member name is required for dependents.',
            'relationship_to_principal.required_if' => 'Relationship to principal is required for dependents.',
            'relationship_to_principal.in' => 'Invalid relationship selected.',
            'coverage_start_date.required_if' => 'Coverage start date is required when patient has insurance.',
            'coverage_end_date.after' => 'Coverage end date must be after start date.',
        ];
    }
}
