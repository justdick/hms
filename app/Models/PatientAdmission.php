<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientAdmission extends Model
{
    /** @use HasFactory<\Database\Factories\PatientAdmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'admission_number',
        'patient_id',
        'consultation_id',
        'bed_id',
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
            'status' => 'string',
        ];
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

    public function nursingNotes(): HasMany
    {
        return $this->hasMany(NursingNote::class);
    }

    public function scopeActive($query): void
    {
        $query->where('status', 'admitted');
    }

    public function scopeForWard($query, $wardId): void
    {
        $query->where('ward_id', $wardId);
    }

    public function markAsDischarged(User $dischargedBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'discharged',
            'discharged_at' => now(),
            'discharged_by_id' => $dischargedBy->id,
            'discharge_notes' => $notes,
        ]);

        if ($this->bed) {
            $this->bed->markAsAvailable();
        }
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
