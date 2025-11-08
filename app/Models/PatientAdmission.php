<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PatientAdmission extends Model
{
    /** @use HasFactory<\Database\Factories\PatientAdmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'admission_number',
        'patient_id',
        'consultation_id',
        'bed_id',
        'bed_assigned_by_id',
        'bed_assigned_at',
        'is_overflow_patient',
        'overflow_notes',
        'ward_id',
        'status',
        'admission_reason',
        'admission_notes',
        'expected_discharge_date',
        'admitted_at',
        'discharged_at',
        'discharge_notes',
        'discharged_by_id',
    ];

    protected function casts(): array
    {
        return [
            'expected_discharge_date' => 'date',
            'admitted_at' => 'datetime',
            'discharged_at' => 'datetime',
            'bed_assigned_at' => 'datetime',
            'is_overflow_patient' => 'boolean',
            'status' => 'string',
        ];
    }

    protected $appends = ['vitals_schedule'];

    /**
     * Accessor to provide activeVitalsSchedule as vitals_schedule for frontend compatibility
     */
    public function getVitalsScheduleAttribute(): ?VitalsSchedule
    {
        return $this->activeVitalsSchedule;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by_id');
    }

    public function bedAssignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bed_assigned_by_id');
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    public function latestVitalSigns(): HasMany
    {
        return $this->vitalSigns()->latest('recorded_at')->limit(1);
    }

    public function medicationAdministrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    public function pendingMedications(): HasMany
    {
        return $this->medicationAdministrations()
            ->where('status', 'scheduled')
            ->where('scheduled_time', '<=', now()->addHours(2));
    }

    public function wardRounds(): HasMany
    {
        return $this->hasMany(WardRound::class);
    }

    public function latestWardRound(): HasMany
    {
        return $this->wardRounds()->with('doctor')->latest('created_at')->limit(1);
    }

    public function nursingNotes(): HasMany
    {
        return $this->hasMany(NursingNote::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(AdmissionDiagnosis::class);
    }

    public function activeDiagnoses(): HasMany
    {
        return $this->diagnoses()->where('is_active', true);
    }

    public function admissionConsultation(): BelongsTo
    {
        return $this->consultation();
    }

    public function vitalsSchedule(): HasOne
    {
        return $this->hasOne(VitalsSchedule::class);
    }

    public function activeVitalsSchedule(): HasOne
    {
        return $this->hasOne(VitalsSchedule::class)->where('is_active', true);
    }

    public function wardRoundConsultations(): HasMany
    {
        return $this->hasMany(Consultation::class, 'admission_id');
    }

    public function scopeActive($query): void
    {
        $query->where('status', 'admitted');
    }

    public function scopeForWard($query, $wardId): void
    {
        $query->where('ward_id', $wardId);
    }

    public function hasUnpaidCopays(): bool
    {
        // Check if patient has active insurance
        if (! $this->patient->activeInsurance) {
            return false;
        }

        // Get all charges for this patient's checkin/consultation
        $checkinId = $this->consultation?->patient_checkin_id;
        if (! $checkinId) {
            return false;
        }

        // Check for unpaid insurance-covered charges
        return Charge::where('patient_checkin_id', $checkinId)
            ->where('is_insurance_claim', true)
            ->where(function ($query) {
                $query->where('patient_copay_amount', '>', 0)
                    ->whereNull('paid_at');
            })
            ->exists();
    }

    public function getUnpaidCopayAmount(): float
    {
        // Check if patient has active insurance
        if (! $this->patient->activeInsurance) {
            return 0;
        }

        // Get all charges for this patient's checkin/consultation
        $checkinId = $this->consultation?->patient_checkin_id;
        if (! $checkinId) {
            return 0;
        }

        // Sum unpaid copay amounts
        return (float) Charge::where('patient_checkin_id', $checkinId)
            ->where('is_insurance_claim', true)
            ->where(function ($query) {
                $query->where('patient_copay_amount', '>', 0)
                    ->whereNull('paid_at');
            })
            ->sum('patient_copay_amount');
    }

    public function markAsDischarged(User $dischargedBy, ?string $notes = null): void
    {
        // Check for unpaid copays before allowing discharge
        if ($this->hasUnpaidCopays()) {
            $unpaidAmount = $this->getUnpaidCopayAmount();
            throw new \RuntimeException(
                "Cannot discharge patient with unpaid copays. Outstanding amount: GHS {$unpaidAmount}. Please collect payment at billing before discharge."
            );
        }

        $this->update([
            'status' => 'discharged',
            'discharged_at' => now(),
            'discharged_by_id' => $dischargedBy->id,
            'discharge_notes' => $notes,
        ]);

        if ($this->bed) {
            $this->bed->markAsAvailable();
        }

        // Disable vitals schedule and dismiss pending alerts
        if ($this->activeVitalsSchedule) {
            $this->activeVitalsSchedule->update(['is_active' => false]);

            $this->activeVitalsSchedule->alerts()
                ->whereIn('status', ['pending', 'due', 'overdue'])
                ->update(['status' => 'dismissed']);
        }
    }

    public function assignBed(Bed $bed, User $assignedBy, ?string $notes = null): void
    {
        $this->update([
            'bed_id' => $bed->id,
            'bed_assigned_by_id' => $assignedBy->id,
            'bed_assigned_at' => now(),
            'is_overflow_patient' => false,
            'overflow_notes' => $notes,
        ]);

        $bed->markAsOccupied();
    }

    public function changeBed(Bed $newBed, User $assignedBy, ?string $notes = null): void
    {
        // Free up the old bed
        if ($this->bed) {
            $this->bed->markAsAvailable();
        }

        // Assign the new bed
        $this->assignBed($newBed, $assignedBy, $notes);
    }

    public function markAsOverflow(?string $notes = null): void
    {
        $this->update([
            'is_overflow_patient' => true,
            'overflow_notes' => $notes,
        ]);
    }

    public function scopeOverflowPatients($query): void
    {
        $query->where('is_overflow_patient', true)->where('status', 'admitted');
    }

    public function scopeWithoutBed($query): void
    {
        $query->whereNull('bed_id')->where('status', 'admitted');
    }

    public static function generateAdmissionNumber(): string
    {
        return 'ADM-'.now()->format('Ymd').'-'.str_pad(
            static::whereDate('created_at', today())->count() + 1,
            4,
            '0',
            STR_PAD_LEFT
        );
    }
}
