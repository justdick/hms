<?php

namespace App\Http\Requests;

use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VetClaimRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $claim = $this->route('claim');
        $isNhisClaim = $claim instanceof InsuranceClaim && $claim->isNhisClaim();

        return [
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
            'gdrg_tariff_id' => [
                $this->input('action') === 'approve' && $isNhisClaim ? 'required' : 'nullable',
                'integer',
                'exists:gdrg_tariffs,id',
            ],
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*.diagnosis_id' => ['required', 'integer', 'exists:diagnoses,id'],
            'diagnoses.*.is_primary' => ['nullable', 'boolean'],
            // Item-level approval/rejection
            'items' => ['nullable', 'array'],
            'items.*.id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $claim = $this->route('claim');
                    if ($claim && ! InsuranceClaimItem::where('id', $value)
                        ->where('insurance_claim_id', $claim->id)
                        ->exists()) {
                        $fail('The selected item does not belong to this claim.');
                    }
                },
            ],
            'items.*.is_approved' => ['required', 'boolean'],
            'items.*.rejection_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gdrg_tariff_id.required' => 'G-DRG selection is required for NHIS claims.',
            'gdrg_tariff_id.exists' => 'The selected G-DRG tariff is invalid.',
            'rejection_reason.required_if' => 'A rejection reason is required when rejecting a claim.',
            'diagnoses.*.diagnosis_id.required' => 'Each diagnosis must have a valid diagnosis ID.',
            'diagnoses.*.diagnosis_id.exists' => 'One or more selected diagnoses are invalid.',
            'items.*.id.required' => 'Each item must have a valid ID.',
            'items.*.is_approved.required' => 'Each item must have an approval status.',
        ];
    }
}
