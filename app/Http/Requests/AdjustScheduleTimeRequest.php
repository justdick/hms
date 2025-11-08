<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustScheduleTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('adjustSchedule', $this->route('administration'));
    }

    public function rules(): array
    {
        return [
            'scheduled_time' => ['required', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_time.required' => 'Scheduled time is required.',
            'scheduled_time.date' => 'Scheduled time must be a valid date.',
            'scheduled_time.after' => 'Scheduled time must be in the future.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
        ];
    }
}
