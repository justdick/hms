<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceProvider extends Model
{
    /** @use HasFactory<\Database\Factories\InsuranceProviderFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'contact_person',
        'phone',
        'email',
        'address',
        'claim_submission_method',
        'payment_terms_days',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'payment_terms_days' => 'integer',
        ];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(InsurancePlan::class);
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
