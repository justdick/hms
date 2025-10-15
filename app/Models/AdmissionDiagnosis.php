<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdmissionDiagnosis extends Model
{
    /** @use HasFactory<\Database\Factories\AdmissionDiagnosisFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_admission_id',
        'icd_code',
        'icd_version',
        'diagnosis_name',
        'diagnosis_type',
        'source_type',
        'source_id',
        'diagnosed_by',
        'diagnosed_at',
        'is_active',
        'clinical_notes',
    ];

    protected function casts(): array
    {
        return [
            'diagnosed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function patientAdmission(): BelongsTo
    {
        return $this->belongsTo(PatientAdmission::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function diagnosedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diagnosed_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('diagnosis_type', $type);
    }
}
