<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationDiagnosis extends Model
{
    /** @use HasFactory<\Database\Factories\ConsultationDiagnosisFactory> */
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'icd_code',
        'diagnosis_description',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function scopePrimary($query): void
    {
        $query->where('is_primary', true);
    }

    public function scopeSecondary($query): void
    {
        $query->where('is_primary', false);
    }
}
