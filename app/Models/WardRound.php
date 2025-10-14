<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WardRound extends Model
{
    /** @use HasFactory<\Database\Factories\WardRoundFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_admission_id',
        'doctor_id',
        'progress_note',
        'patient_status',
        'clinical_impression',
        'plan',
        'round_datetime',
    ];

    protected function casts(): array
    {
        return [
            'round_datetime' => 'datetime',
        ];
    }

    public function patientAdmission(): BelongsTo
    {
        return $this->belongsTo(PatientAdmission::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function isImproving(): bool
    {
        return $this->patient_status === 'improving';
    }

    public function isStable(): bool
    {
        return $this->patient_status === 'stable';
    }

    public function isDeteriorating(): bool
    {
        return $this->patient_status === 'deteriorating';
    }

    public function isDischargeReady(): bool
    {
        return $this->patient_status === 'discharge_ready';
    }

    public function scopeRecent($query): void
    {
        $query->orderBy('round_datetime', 'desc');
    }

    public function scopeByDoctor($query, int $doctorId): void
    {
        $query->where('doctor_id', $doctorId);
    }
}
