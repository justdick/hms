<?php

namespace App\Http\Requests;

use App\Models\NhisItemMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNhisMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'item_type' => [
                'required',
                'string',
                Rule::in(['drug', 'lab_service', 'procedure', 'consumable']),
            ],
            'item_id' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    // Validate item exists
                    $itemType = $this->input('item_type');
                    $modelClass = NhisItemMapping::getModelClassForType($itemType);

                    if (! $modelClass || ! $modelClass::find($value)) {
                        $fail('The selected item does not exist.');
                    }
                },
            ],
            'nhis_tariff_id' => [
                'required',
                'integer',
                'exists:nhis_tariffs,id',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if item is already mapped
            $itemType = $this->input('item_type');
            $itemId = $this->input('item_id');

            if ($itemType && $itemId) {
                $existingMapping = NhisItemMapping::forItem($itemType, $itemId)->first();

                if ($existingMapping) {
                    $validator->errors()->add(
                        'item_id',
                        'This item is already mapped to an NHIS tariff.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'item_type.required' => 'Item type is required.',
            'item_type.in' => 'Invalid item type. Must be one of: drug, lab_service, procedure, consumable.',
            'item_id.required' => 'Item is required.',
            'item_id.integer' => 'Item ID must be a valid integer.',
            'item_id.min' => 'Item ID must be a positive integer.',
            'nhis_tariff_id.required' => 'NHIS tariff is required.',
            'nhis_tariff_id.exists' => 'The selected NHIS tariff does not exist.',
        ];
    }
}
