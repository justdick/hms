<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('configureSchedule', $this->route('prescription'));
    }

    public function rules(): array
    {
        return [
            'schedule_pattern' => ['required', 'array'],
            'schedule_pattern.day_1' => ['required', 'array', 'min:1'],
            'schedule_pattern.day_1.*' => ['required', 'date_format:H:i'],
            'schedule_pattern.day_*' => ['nullable', 'array'],
            'schedule_pattern.day_*.*' => ['required', 'date_format:H:i'],
            'schedule_pattern.subsequent' => ['required', 'array', 'min:1'],
            'schedule_pattern.subsequent.*' => ['required', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'schedule_pattern.required' => 'Schedule pattern is required.',
            'schedule_pattern.array' => 'Schedule pattern must be an array.',
            'schedule_pattern.day_1.required' => 'Day 1 schedule is required.',
            'schedule_pattern.day_1.array' => 'Day 1 schedule must be an array.',
            'schedule_pattern.day_1.min' => 'Day 1 must have at least one time.',
            'schedule_pattern.day_1.*.required' => 'Each time is required.',
            'schedule_pattern.day_1.*.date_format' => 'Each time must be in HH:MM format.',
            'schedule_pattern.subsequent.required' => 'Subsequent days schedule is required.',
            'schedule_pattern.subsequent.array' => 'Subsequent days schedule must be an array.',
            'schedule_pattern.subsequent.min' => 'Subsequent days must have at least one time.',
            'schedule_pattern.subsequent.*.required' => 'Each time is required.',
            'schedule_pattern.subsequent.*.date_format' => 'Each time must be in HH:MM format.',
        ];
    }
}
