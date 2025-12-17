<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class SetCreditLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('billing.manage-credit');
    }

    public function rules(): array
    {
        return [
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
