<?php

namespace App\Http\Requests;

use App\Models\Consultation;
use App\Models\LabOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ExternalImageUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $consultation = $this->route('consultation');

        // User must be able to update the consultation
        if (! Gate::allows('update', $consultation)) {
            return false;
        }

        // User must have permission to upload external images
        return Gate::allows('uploadExternalImages', [LabOrder::class, null]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lab_service_id' => [
                'required',
                'exists:lab_services,id',
            ],
            'external_facility_name' => [
                'required',
                'string',
                'max:255',
            ],
            'external_study_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'files' => [
                'required',
                'array',
                'min:1',
                'max:10', // Maximum 10 files per upload
            ],
            'files.*' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,pdf',
                'max:51200', // 50MB in KB
            ],
            'descriptions' => [
                'nullable',
                'array',
            ],
            'descriptions.*' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
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
            'lab_service_id.required' => 'Please select an imaging study type.',
            'lab_service_id.exists' => 'The selected imaging study type is invalid.',
            'external_facility_name.required' => 'External facility name is required for external images.',
            'external_facility_name.max' => 'Facility name cannot exceed 255 characters.',
            'external_study_date.required' => 'Study date is required for external images.',
            'external_study_date.date' => 'Please provide a valid study date.',
            'external_study_date.before_or_equal' => 'Study date cannot be in the future.',
            'files.required' => 'Please select at least one file to upload.',
            'files.min' => 'Please select at least one file to upload.',
            'files.max' => 'You can upload a maximum of 10 files at once.',
            'files.*.required' => 'Each file is required.',
            'files.*.file' => 'The uploaded file is invalid.',
            'files.*.mimes' => 'Only JPEG, PNG, and PDF files are allowed.',
            'files.*.max' => 'File size exceeds 50MB limit.',
            'descriptions.*.max' => 'Description cannot exceed 255 characters.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lab_service_id' => 'imaging study type',
            'external_facility_name' => 'facility name',
            'external_study_date' => 'study date',
            'files.*' => 'file',
            'descriptions.*' => 'description',
        ];
    }
}
