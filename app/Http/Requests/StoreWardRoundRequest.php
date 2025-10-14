<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWardRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ward_rounds.create');
    }

    public function rules(): array
    {
        return [
            'progress_note' => ['required', 'string', 'min:10', 'max:5000'],
            'patient_status' => ['required', 'in:improving,stable,deteriorating,discharge_ready'],
            'clinical_impression' => ['nullable', 'string', 'max:2000'],
            'plan' => ['nullable', 'string', 'max:2000'],
            'round_datetime' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'progress_note.required' => 'Progress note is required.',
            'progress_note.min' => 'Progress note must be at least 10 characters.',
            'progress_note.max' => 'Progress note cannot exceed 5000 characters.',
            'patient_status.required' => 'Patient status is required.',
            'patient_status.in' => 'Invalid patient status. Must be: improving, stable, deteriorating, or discharge ready.',
            'clinical_impression.max' => 'Clinical impression cannot exceed 2000 characters.',
            'plan.max' => 'Plan cannot exceed 2000 characters.',
            'round_datetime.before_or_equal' => 'Ward round time cannot be in the future.',
        ];
    }
}
