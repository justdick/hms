<?php

namespace App\Http\Requests;

use App\Models\ClaimBatch;
use Carbon\Carbon;
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
            'name' => ['nullable', 'string', 'max:255'],
            'submission_period' => ['required', 'date', function (string $attribute, mixed $value, \Closure $fail) {
                $period = Carbon::parse($value)->startOfMonth();

                $exists = ClaimBatch::whereYear('submission_period', $period->year)
                    ->whereMonth('submission_period', $period->month)
                    ->exists();

                if ($exists) {
                    $fail("A batch already exists for {$period->format('F Y')}.");
                }
            }],
            'notes' => ['nullable', 'string', 'max:1000'],
            'auto_populate' => ['nullable', 'boolean'],
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
