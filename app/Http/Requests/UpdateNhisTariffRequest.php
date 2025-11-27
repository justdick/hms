<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateNhisTariffRequest extends StoreNhisTariffRequest
{
    public function rules(): array
    {
        $tariffId = $this->route('nhis_tariff')?->id;

        return [
            'nhis_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('nhis_tariffs', 'nhis_code')->ignore($tariffId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(['medicine', 'lab', 'procedure', 'consultation', 'consumable'])],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }
}
