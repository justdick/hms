<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkBatchSubmittedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'submitted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'submitted_at.date' => 'Submission date must be a valid date.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
