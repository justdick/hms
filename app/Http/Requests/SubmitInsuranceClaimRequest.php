<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitInsuranceClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // For single claim submission
            'claim_id' => ['nullable', 'exists:insurance_claims,id'],
            'submission_date' => ['nullable', 'date', 'before_or_equal:today'],

            // For batch submission
            'claim_ids' => ['nullable', 'array', 'min:1'],
            'claim_ids.*' => ['required', 'exists:insurance_claims,id'],
            'batch_reference' => ['nullable', 'string', 'max:100'],

            // Optional notes
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'claim_id.exists' => 'Invalid claim selected.',
            'submission_date.date' => 'Submission date must be a valid date.',
            'submission_date.before_or_equal' => 'Submission date cannot be in the future.',
            'claim_ids.required' => 'At least one claim must be selected for batch submission.',
            'claim_ids.min' => 'At least one claim must be selected for batch submission.',
            'claim_ids.*.exists' => 'One or more invalid claims selected.',
        ];
    }

    /**
     * Determine if this is a batch submission.
     */
    public function isBatchSubmission(): bool
    {
        return $this->has('claim_ids') && is_array($this->input('claim_ids'));
    }

    /**
     * Get the claim IDs to submit.
     */
    public function getClaimIds(): array
    {
        if ($this->isBatchSubmission()) {
            return $this->input('claim_ids', []);
        }

        return $this->has('claim_id') ? [$this->input('claim_id')] : [];
    }
}
