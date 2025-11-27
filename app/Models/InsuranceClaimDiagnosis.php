<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaimDiagnosis extends Model
{
    /** @use HasFactory<\Database\Factories\InsuranceClaimDiagnosisFactory> */
    use HasFactory;

    protected $fillable = [
        'insurance_claim_id',
        'diagnosis_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function insuranceClaim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class);
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class);
    }

    /**
     * Scope to filter primary diagnoses.
     */
    public function scopePrimary($query): void
    {
        $query->where('is_primary', true);
    }

    /**
     * Scope to filter secondary diagnoses.
     */
    public function scopeSecondary($query): void
    {
        $query->where('is_primary', false);
    }
}
