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
        'diagnosis_id',
        'type',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class);
    }

    public function scopeProvisional($query): void
    {
        $query->where('type', 'provisional');
    }

    public function scopePrincipal($query): void
    {
        $query->where('type', 'principal');
    }
}
