<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateGdrgTariffRequest extends StoreGdrgTariffRequest
{
    public function rules(): array
    {
        $tariffId = $this->route('gdrg_tariff')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('gdrg_tariffs', 'code')->ignore($tariffId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'mdc_category' => ['required', 'string', 'max:100'],
            'tariff_price' => ['required', 'numeric', 'min:0'],
            'age_category' => ['nullable', 'string', Rule::in(['adult', 'child', 'all'])],
            'is_active' => ['boolean'],
        ];
    }
}
