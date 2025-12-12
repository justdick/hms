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
            // At least one of reviews or supply_reviews must be present
            'reviews' => ['sometimes', 'array', 'min:1'],
            'reviews.*.prescription_id' => ['required', 'exists:prescriptions,id'],
            'reviews.*.action' => ['required', 'in:keep,partial,external,cancel'],
            'reviews.*.quantity_to_dispense' => ['required_if:reviews.*.action,partial', 'integer', 'min:1'],
            'reviews.*.notes' => ['nullable', 'string', 'max:500'],
            'reviews.*.reason' => ['required_if:reviews.*.action,cancel', 'string', 'max:255'],

            // Supply reviews
            'supply_reviews' => ['sometimes', 'array', 'min:1'],
            'supply_reviews.*.supply_id' => ['required', 'exists:minor_procedure_supplies,id'],
            'supply_reviews.*.action' => ['required', 'in:keep,partial,external,cancel'],
            'supply_reviews.*.quantity_to_dispense' => ['required_if:supply_reviews.*.action,partial', 'numeric', 'min:0.01'],
            'supply_reviews.*.notes' => ['nullable', 'string', 'max:500'],
            'supply_reviews.*.reason' => ['required_if:supply_reviews.*.action,cancel', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reviews.*.action.required' => 'Review action is required for each prescription.',
            'reviews.*.action.in' => 'Invalid review action. Must be: keep, partial, external, or cancel.',
            'reviews.*.quantity_to_dispense.required_if' => 'Quantity is required for partial dispensing.',
            'reviews.*.reason.required_if' => 'Reason is required when cancelling a prescription.',

            'supply_reviews.*.action.required' => 'Review action is required for each supply.',
            'supply_reviews.*.action.in' => 'Invalid review action. Must be: keep, partial, external, or cancel.',
            'supply_reviews.*.quantity_to_dispense.required_if' => 'Quantity is required for partial dispensing.',
            'supply_reviews.*.reason.required_if' => 'Reason is required when cancelling a supply.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Ensure at least one of reviews or supply_reviews is present
            if (empty($this->input('reviews')) && empty($this->input('supply_reviews'))) {
                $validator->errors()->add('reviews', 'At least one prescription or supply must be reviewed.');
            }
        });
    }
}
