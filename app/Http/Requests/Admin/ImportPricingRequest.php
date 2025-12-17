<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'plan_id' => ['nullable', 'integer', 'exists:insurance_plans,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to import.',
            'file.file' => 'The uploaded file is invalid.',
            'file.mimes' => 'The file must be a CSV or Excel file (csv, txt, xlsx, xls).',
            'file.max' => 'The file size cannot exceed 10MB.',
            'plan_id.exists' => 'Invalid insurance plan selected.',
        ];
    }
}
