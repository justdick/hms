<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordInsurancePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_reference' => ['required', 'string', 'max:191'],
            'approval_date' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_date.required' => 'Payment date is required.',
            'payment_date.date' => 'Payment date must be a valid date.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
            'payment_amount.required' => 'Payment amount is required.',
            'payment_amount.numeric' => 'Payment amount must be a valid number.',
            'payment_amount.min' => 'Payment amount must be greater than zero.',
            'payment_reference.required' => 'Payment reference is required.',
            'payment_reference.max' => 'Payment reference cannot exceed 191 characters.',
            'approval_date.date' => 'Approval date must be a valid date.',
            'approval_date.before_or_equal' => 'Approval date cannot be in the future.',
        ];
    }
}
