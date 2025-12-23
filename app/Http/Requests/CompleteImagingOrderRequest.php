<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CompleteImagingOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $labOrder = $this->route('labOrder');

        return Gate::allows('enterReport-radiology', $labOrder);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'result_notes' => 'required|string|min:10|max:5000',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'result_notes.required' => 'A report is required to complete the imaging study.',
            'result_notes.min' => 'The report must be at least 10 characters.',
            'result_notes.max' => 'The report cannot exceed 5000 characters.',
        ];
    }
}
