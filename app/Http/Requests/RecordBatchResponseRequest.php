<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordBatchResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'responses' => ['required', 'array', 'min:1'],
            'responses.*' => ['required', 'array'],
            'responses.*.status' => [
                'required',
                'string',
                Rule::in(['pending', 'approved', 'rejected', 'paid']),
            ],
            'responses.*.approved_amount' => ['nullable', 'numeric', 'min:0'],
            'responses.*.rejection_reason' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'responses.required' => 'At least one claim response is required.',
            'responses.array' => 'Responses must be provided as an array.',
            'responses.min' => 'At least one claim response is required.',
            'responses.*.required' => 'Each response must be an array.',
            'responses.*.status.required' => 'Status is required for each claim response.',
            'responses.*.status.in' => 'Invalid status. Must be one of: pending, approved, rejected, paid.',
            'responses.*.approved_amount.numeric' => 'Approved amount must be a number.',
            'responses.*.approved_amount.min' => 'Approved amount cannot be negative.',
            'responses.*.rejection_reason.max' => 'Rejection reason cannot exceed 500 characters.',
            'paid_at.date' => 'Payment date must be a valid date.',
            'paid_amount.numeric' => 'Payment amount must be a number.',
            'paid_amount.min' => 'Payment amount cannot be negative.',
        ];
    }
}
