<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddClaimsToBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'claim_ids' => ['required', 'array', 'min:1'],
            'claim_ids.*' => ['required', 'integer', 'exists:insurance_claims,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'claim_ids.required' => 'At least one claim must be selected.',
            'claim_ids.array' => 'Claim IDs must be provided as an array.',
            'claim_ids.min' => 'At least one claim must be selected.',
            'claim_ids.*.required' => 'Each claim ID is required.',
            'claim_ids.*.integer' => 'Each claim ID must be a valid integer.',
            'claim_ids.*.exists' => 'One or more selected claims do not exist.',
        ];
    }
}
