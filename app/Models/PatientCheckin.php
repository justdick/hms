<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PatientCheckin extends Model
{
    /** @use HasFactory<\Database\Factories\PatientCheckinFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'department_id',
        'checked_in_by',
        'checked_in_at',
        'vitals_taken_at',
        'consultation_started_at',
        'consultation_completed_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'vitals_taken_at' => 'datetime',
            'consultation_started_at' => 'datetime',
            'consultation_completed_at' => 'datetime',
            'status' => 'string',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }

    public function scopeToday($query): void
    {
        $query->whereDate('checked_in_at', today());
    }

    public function scopeByStatus($query, string $status): void
    {
        $query->where('status', $status);
    }

    public function markVitalsTaken(): void
    {
        $this->update([
            'vitals_taken_at' => now(),
            'status' => 'vitals_taken'
        ]);
    }

    public function markAwaitingConsultation(): void
    {
        $this->update([
            'status' => 'awaiting_consultation'
        ]);
    }
}
