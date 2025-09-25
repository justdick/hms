<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'attending_doctor_id',
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

    public function attendingDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attending_doctor_id');
    }

    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by_id');
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
