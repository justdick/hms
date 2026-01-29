<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRecord extends Model
{
    use HasFactory;

    public const DELIVERY_MODES = [
        'svd_live' => 'Spontaneous Vaginal Delivery (Live Birth)',
        'svd_stillbirth' => 'Spontaneous Vaginal Delivery (Stillbirth)',
        'svd_multiple' => 'Spontaneous Vaginal Delivery (Multiple Gestation)',
        'emergency_cs' => 'Emergency C/S',
        'elective_cs' => 'Elective C/S',
        'emergency_cs_sterilization' => 'Emergency C/S + Sterilization',
        'elective_cs_sterilization' => 'Elective C/S + Sterilization',
        'emergency_cs_hysterectomy' => 'Emergency C/S + Hysterectomy',
    ];

    protected $fillable = [
        'patient_admission_id',
        'patient_id',
        'delivery_date',
        'gestational_age',
        'parity',
        'delivery_mode',
        'outcomes',
        'surgical_notes',
        'notes',
        'recorded_by_id',
        'last_edited_by_id',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'outcomes' => 'array',
        ];
    }

    public function patientAdmission(): BelongsTo
    {
        return $this->belongsTo(PatientAdmission::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by_id');
    }

    /**
     * Get the delivery mode label
     */
    public function getDeliveryModeLabelAttribute(): string
    {
        return self::DELIVERY_MODES[$this->delivery_mode] ?? $this->delivery_mode;
    }

    /**
     * Check if this is a C-section delivery
     */
    public function isCSection(): bool
    {
        return str_contains($this->delivery_mode, 'cs');
    }

    /**
     * Get the number of babies delivered
     */
    public function getBabyCountAttribute(): int
    {
        return is_array($this->outcomes) ? count($this->outcomes) : 0;
    }

    /**
     * Scope to filter by maternity ward admissions
     */
    public function scopeForMaternityWard($query)
    {
        return $query->whereHas('patientAdmission.ward', function ($q) {
            $q->where('code', 'MATERNITY-WARD');
        });
    }
}
