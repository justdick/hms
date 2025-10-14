<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewPrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('dispensing.review');
    }

    public function rules(): array
    {
        return [
            'reviews' => ['required', 'array', 'min:1'],
            'reviews.*.prescription_id' => ['required', 'exists:prescriptions,id'],
            'reviews.*.action' => ['required', 'in:keep,partial,external,cancel'],
            'reviews.*.quantity_to_dispense' => ['required_if:reviews.*.action,partial', 'integer', 'min:1'],
            'reviews.*.notes' => ['nullable', 'string', 'max:500'],
            'reviews.*.reason' => ['required_if:reviews.*.action,external,cancel', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reviews.required' => 'At least one prescription must be reviewed.',
            'reviews.*.action.required' => 'Review action is required for each prescription.',
            'reviews.*.action.in' => 'Invalid review action. Must be: keep, partial, external, or cancel.',
            'reviews.*.quantity_to_dispense.required_if' => 'Quantity is required for partial dispensing.',
            'reviews.*.reason.required_if' => 'Reason is required when marking as external or cancelled.',
        ];
    }
}
