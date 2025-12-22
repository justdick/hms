<?php

namespace App\Http\Requests\Prescription;

use App\Models\Drug;
use App\Services\Prescription\PrescriptionParserService;
use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionRequest extends FormRequest
{
    // Topical drug forms that don't require frequency/duration
    private const TOPICAL_FORMS = ['cream', 'ointment', 'gel', 'lotion'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'medication_name' => ['required', 'string', 'max:255'],
            'drug_id' => ['nullable', 'integer', 'exists:drugs,id'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'use_smart_mode' => ['nullable', 'boolean'],
            'smart_input' => ['nullable', 'string', 'max:500'],
        ];

        // Check if this is a topical preparation
        $isTopical = $this->isTopicalDrug();

        // If using smart mode, smart_input is required and classic fields are optional
        if ($this->boolean('use_smart_mode')) {
            $rules['smart_input'] = ['required', 'string', 'max:500'];
            $rules['dose_quantity'] = ['nullable', 'string', 'max:50'];
            $rules['frequency'] = ['nullable', 'string', 'max:100'];
            $rules['duration'] = ['nullable', 'string', 'max:100'];
            $rules['quantity_to_dispense'] = ['nullable', 'integer', 'min:1'];
        } elseif ($isTopical) {
            // Topical preparations - only quantity and instructions required
            $rules['dose_quantity'] = ['nullable', 'string', 'max:50'];
            $rules['frequency'] = ['nullable', 'string', 'max:100'];
            $rules['duration'] = ['nullable', 'string', 'max:100'];
            $rules['quantity_to_dispense'] = ['required', 'integer', 'min:1'];
            $rules['instructions'] = ['required', 'string', 'max:1000'];
        } else {
            // Classic mode - frequency and duration are required
            $rules['dose_quantity'] = ['nullable', 'string', 'max:50'];
            $rules['frequency'] = ['required', 'string', 'max:100'];
            $rules['duration'] = ['required', 'string', 'max:100'];
            $rules['quantity_to_dispense'] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'medication_name.required' => 'Medication name is required.',
            'smart_input.required' => 'Prescription input is required when using Smart mode.',
            'frequency.required' => 'Frequency is required.',
            'duration.required' => 'Duration is required.',
            'drug_id.exists' => 'The selected drug does not exist.',
            'quantity_to_dispense.required' => 'Quantity is required for topical preparations.',
            'quantity_to_dispense.min' => 'Quantity to dispense must be at least 1.',
            'instructions.required' => 'Application instructions are required for topical preparations.',
        ];
    }

    /**
     * Check if the selected drug is a topical preparation.
     */
    private function isTopicalDrug(): bool
    {
        $drugId = $this->input('drug_id');
        if (! $drugId) {
            return false;
        }

        $drug = Drug::find($drugId);
        if (! $drug) {
            return false;
        }

        return in_array(strtolower($drug->form), self::TOPICAL_FORMS);
    }

    /**
     * Get the validated prescription data, parsing smart input if needed.
     *
     * @return array{
     *     medication_name: string,
     *     drug_id: int|null,
     *     dose_quantity: string|null,
     *     frequency: string,
     *     duration: string,
     *     quantity_to_dispense: int|null,
     *     instructions: string|null
     * }
     */
    public function getPrescriptionData(): array
    {
        $validated = $this->validated();

        if ($this->boolean('use_smart_mode') && ! empty($validated['smart_input'])) {
            return $this->parseSmartInput($validated);
        }

        // For topical preparations, use "As directed" for frequency/duration
        $isTopical = $this->isTopicalDrug();

        // Classic mode - return data as-is
        return [
            'medication_name' => $validated['medication_name'],
            'drug_id' => $validated['drug_id'] ?? null,
            'dose_quantity' => $validated['dose_quantity'] ?? null,
            'frequency' => $validated['frequency'] ?? ($isTopical ? 'As directed' : null),
            'duration' => $validated['duration'] ?? ($isTopical ? 'As directed' : null),
            'quantity_to_dispense' => $validated['quantity_to_dispense'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
        ];
    }

    /**
     * Parse smart input and return prescription data.
     */
    private function parseSmartInput(array $validated): array
    {
        $parser = app(PrescriptionParserService::class);

        // Get drug if provided for quantity calculation
        $drug = null;
        if (! empty($validated['drug_id'])) {
            $drug = Drug::find($validated['drug_id']);
        }

        $result = $parser->parse($validated['smart_input'], $drug);

        if (! $result->isValid) {
            // If parsing fails, throw validation exception
            throw \Illuminate\Validation\ValidationException::withMessages([
                'smart_input' => $result->errors ?: ['Could not parse prescription input.'],
            ]);
        }

        return [
            'medication_name' => $validated['medication_name'],
            'drug_id' => $validated['drug_id'] ?? null,
            'dose_quantity' => $result->doseQuantity,
            'frequency' => $result->frequency,
            'duration' => $result->duration ?? 'As directed',
            'quantity_to_dispense' => $result->quantityToDispense,
            'instructions' => $validated['instructions'] ?? null,
        ];
    }
}
