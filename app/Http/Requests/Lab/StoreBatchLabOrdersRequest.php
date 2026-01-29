<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchLabOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'lab_orders' => ['required', 'array', 'min:1'],
            'lab_orders.*.lab_service_id' => ['required', 'integer', 'exists:lab_services,id'],
            'lab_orders.*.priority' => ['nullable', 'in:routine,urgent,stat'],
            'lab_orders.*.special_instructions' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'lab_orders.required' => 'At least one lab order is required.',
            'lab_orders.min' => 'At least one lab order is required.',
            'lab_orders.*.lab_service_id.required' => 'Lab service is required for each order.',
            'lab_orders.*.lab_service_id.exists' => 'One of the selected lab services does not exist.',
        ];
    }

    public function getLabOrders(): array
    {
        return array_map(function ($order) {
            return [
                'lab_service_id' => $order['lab_service_id'],
                'priority' => $order['priority'] ?? 'routine',
                'special_instructions' => $order['special_instructions'] ?? null,
            ];
        }, $this->validated()['lab_orders']);
    }
}
