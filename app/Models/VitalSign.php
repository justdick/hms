<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSign extends Model
{
    /** @use HasFactory<\Database\Factories\VitalSignFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'patient_checkin_id',
        'patient_admission_id',
        'recorded_by',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'temperature',
        'pulse_rate',
        'respiratory_rate',
        'weight',
        'height',
        'bmi',
        'oxygen_saturation',
        'blood_sugar',
        'notes',
        'recorded_at',
        'migrated_from_mittag',
    ];

    protected function casts(): array
    {
        return [
            'blood_pressure_systolic' => 'decimal:2',
            'blood_pressure_diastolic' => 'decimal:2',
            'temperature' => 'integer',
            'pulse_rate' => 'integer',
            'respiratory_rate' => 'integer',
            'weight' => 'integer',
            'height' => 'decimal:2',
            'bmi' => 'decimal:2',
            'oxygen_saturation' => 'integer',
            'blood_sugar' => 'decimal:1',
            'recorded_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function patientCheckin(): BelongsTo
    {
        return $this->belongsTo(PatientCheckin::class);
    }

    public function patientAdmission(): BelongsTo
    {
        return $this->belongsTo(PatientAdmission::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getBloodPressureAttribute(): string
    {
        if ($this->blood_pressure_systolic && $this->blood_pressure_diastolic) {
            return "{$this->blood_pressure_systolic}/{$this->blood_pressure_diastolic}";
        }

        return 'N/A';
    }

    public function getHeartRateAttribute(): ?int
    {
        return $this->pulse_rate;
    }

    public function calculateBmi(): ?float
    {
        if ($this->weight && $this->height) {
            $heightInMeters = $this->height / 100;

            return round($this->weight / ($heightInMeters * $heightInMeters), 2);
        }

        return null;
    }

    protected static function booted(): void
    {
        static::saving(function (VitalSign $vitalSign) {
            if ($vitalSign->weight && $vitalSign->height && ! $vitalSign->bmi) {
                $vitalSign->bmi = $vitalSign->calculateBmi();
            }
        });
    }
}
