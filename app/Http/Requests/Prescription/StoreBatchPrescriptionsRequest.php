<?php

namespace App\Http\Requests\Prescription;

use App\Models\Drug;
use Illuminate\Foundation\Http\FormRequest;

class StoreBatchPrescriptionsRequest extends FormRequest
{
    private const TOPICAL_FORMS = ['cream', 'ointment', 'gel', 'lotion'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prescriptions' => ['required', 'array', 'min:1'],
            'prescriptions.*.drug_id' => ['required', 'integer', 'exists:drugs,id'],
            'prescriptions.*.medication_name' => ['required', 'string', 'max:255'],
            'prescriptions.*.dose_quantity' => ['nullable', 'string', 'max:50'],
            'prescriptions.*.frequency' => ['nullable', 'string', 'max:100'],
            'prescriptions.*.duration' => ['nullable', 'string', 'max:100'],
            'prescriptions.*.quantity_to_dispense' => ['nullable', 'integer', 'min:1'],
            'prescriptions.*.instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'prescriptions.required' => 'At least one prescription is required.',
            'prescriptions.min' => 'At least one prescription is required.',
            'prescriptions.*.drug_id.required' => 'Drug is required for each prescription.',
            'prescriptions.*.drug_id.exists' => 'One of the selected drugs does not exist.',
            'prescriptions.*.medication_name.required' => 'Medication name is required.',
        ];
    }

    /**
     * Additional validation after rules pass.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $prescriptions = $this->input('prescriptions', []);

            foreach ($prescriptions as $index => $prescription) {
                $drugId = $prescription['drug_id'] ?? null;
                if (! $drugId) {
                    continue;
                }

                $drug = Drug::find($drugId);
                if (! $drug) {
                    continue;
                }

                $isTopical = in_array(strtolower($drug->form), self::TOPICAL_FORMS);

                if ($isTopical) {
                    // Topical requires quantity and instructions
                    if (empty($prescription['quantity_to_dispense'])) {
                        $validator->errors()->add(
                            "prescriptions.{$index}.quantity_to_dispense",
                            'Quantity is required for topical preparations.'
                        );
                    }
                    if (empty($prescription['instructions'])) {
                        $validator->errors()->add(
                            "prescriptions.{$index}.instructions",
                            'Application instructions are required for topical preparations.'
                        );
                    }
                } else {
                    // Non-topical requires frequency and duration
                    if (empty($prescription['frequency'])) {
                        $validator->errors()->add(
                            "prescriptions.{$index}.frequency",
                            'Frequency is required.'
                        );
                    }
                    if (empty($prescription['duration'])) {
                        $validator->errors()->add(
                            "prescriptions.{$index}.duration",
                            'Duration is required.'
                        );
                    }
                }
            }
        });
    }

    /**
     * Get validated prescriptions with defaults applied.
     */
    public function getPrescriptions(): array
    {
        $prescriptions = $this->validated()['prescriptions'];

        return array_map(function ($prescription) {
            $drug = Drug::find($prescription['drug_id']);
            $isTopical = $drug && in_array(strtolower($drug->form), self::TOPICAL_FORMS);

            return [
                'drug_id' => $prescription['drug_id'],
                'medication_name' => $prescription['medication_name'],
                'dose_quantity' => $prescription['dose_quantity'] ?? null,
                'frequency' => $prescription['frequency'] ?? ($isTopical ? 'As directed' : null),
                'duration' => $prescription['duration'] ?? ($isTopical ? 'As directed' : null),
                'quantity_to_dispense' => $prescription['quantity_to_dispense'] ?? null,
                'instructions' => $prescription['instructions'] ?? null,
            ];
        }, $prescriptions);
    }
}
