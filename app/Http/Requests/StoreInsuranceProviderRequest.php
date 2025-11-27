<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInsuranceProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $providerId = $this->route('provider')?->id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('insurance_providers', 'code')->ignore($providerId),
            ],
            'contact_person' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:5000'],
            'claim_submission_method' => ['required', 'in:online,manual,api'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
            'is_nhis' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Provider name is required.',
            'code.required' => 'Provider code is required.',
            'code.unique' => 'This provider code is already in use.',
            'claim_submission_method.required' => 'Claim submission method is required.',
            'claim_submission_method.in' => 'Invalid claim submission method.',
            'payment_terms_days.min' => 'Payment terms must be at least 0 days.',
            'payment_terms_days.max' => 'Payment terms cannot exceed 365 days.',
        ];
    }
}
