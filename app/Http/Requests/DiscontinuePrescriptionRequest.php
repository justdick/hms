<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiscontinuePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('discontinue', $this->route('prescription'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500', 'min:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Reason for discontinuation is required.',
            'reason.min' => 'Reason must be at least 10 characters.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
        ];
    }
}
