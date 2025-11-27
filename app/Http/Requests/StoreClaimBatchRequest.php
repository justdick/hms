<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClaimBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'submission_period' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Batch name is required.',
            'name.max' => 'Batch name cannot exceed 255 characters.',
            'submission_period.required' => 'Submission period is required.',
            'submission_period.date' => 'Submission period must be a valid date.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
