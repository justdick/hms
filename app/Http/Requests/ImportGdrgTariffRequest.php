<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportGdrgTariffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx,xls',
                'max:10240', // 10MB max
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to import.',
            'file.file' => 'The uploaded item must be a file.',
            'file.mimes' => 'Invalid file format. Please use CSV, TXT, XLSX, or XLS format.',
            'file.max' => 'File size cannot exceed 10MB.',
        ];
    }
}
