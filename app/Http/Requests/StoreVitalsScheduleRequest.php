<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVitalsScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'interval_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
        ];
    }

    public function messages(): array
    {
        return [
            'interval_minutes.required' => 'Recording interval is required.',
            'interval_minutes.integer' => 'Recording interval must be a number.',
            'interval_minutes.min' => 'Recording interval must be at least 15 minutes.',
            'interval_minutes.max' => 'Recording interval cannot exceed 24 hours (1440 minutes).',
        ];
    }
}
