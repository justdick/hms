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
        'day_number',
        'round_type',
        'presenting_complaint',
        'history_presenting_complaint',
        'on_direct_questioning',
        'examination_findings',
        'assessment_notes',
        'plan_notes',
        'round_datetime',
        'status',
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

    public function labOrders()
    {
        return $this->morphMany(LabOrder::class, 'orderable');
    }

    public function prescriptions()
    {
        return $this->morphMany(Prescription::class, 'prescribable');
    }

    public function diagnoses()
    {
        return $this->morphMany(AdmissionDiagnosis::class, 'source');
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
