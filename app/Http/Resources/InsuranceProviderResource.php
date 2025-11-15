<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'claim_submission_method' => $this->claim_submission_method,
            'payment_terms_days' => $this->payment_terms_days,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'plans' => $this->when($this->relationLoaded('plans'), fn () => InsurancePlanResource::collection($this->plans)->resolve()),
            'plans_count' => isset($this->plans_count) ? $this->plans_count : ($this->relationLoaded('plans') ? $this->plans->count() : null),
        ];
    }
}
