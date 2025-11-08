<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VitalsSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\VitalsScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_admission_id',
        'interval_minutes',
        'next_due_at',
        'last_recorded_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'interval_minutes' => 'integer',
            'next_due_at' => 'datetime',
            'last_recorded_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function patientAdmission(): BelongsTo
    {
        return $this->belongsTo(PatientAdmission::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(VitalsAlert::class);
    }

    public function activeAlert(): HasOne
    {
        return $this->hasOne(VitalsAlert::class)
            ->whereIn('status', ['pending', 'due', 'overdue'])
            ->latest('due_at');
    }

    public function calculateNextDueTime(Carbon $fromTime): Carbon
    {
        return $fromTime->copy()->addMinutes($this->interval_minutes);
    }

    public function updateNextDueTime(): void
    {
        $baseTime = $this->last_recorded_at ?? now();
        $this->next_due_at = $this->calculateNextDueTime($baseTime);
        $this->save();
    }

    public function getCurrentStatus(): string
    {
        if (! $this->next_due_at) {
            return 'upcoming';
        }

        $now = now();
        $gracePeriodEnd = $this->next_due_at->copy()->addMinutes(15);

        if ($now->greaterThanOrEqualTo($gracePeriodEnd)) {
            return 'overdue';
        }

        if ($now->greaterThanOrEqualTo($this->next_due_at)) {
            return 'due';
        }

        return 'upcoming';
    }

    public function getTimeUntilDue(): ?int
    {
        if (! $this->next_due_at) {
            return null;
        }

        $now = now();

        if ($now->greaterThanOrEqualTo($this->next_due_at)) {
            return 0;
        }

        return (int) $now->diffInMinutes($this->next_due_at);
    }

    public function getTimeOverdue(): ?int
    {
        if (! $this->next_due_at) {
            return null;
        }

        $now = now();
        $gracePeriodEnd = $this->next_due_at->copy()->addMinutes(15);

        if ($now->lessThan($gracePeriodEnd)) {
            return 0;
        }

        return (int) $gracePeriodEnd->diffInMinutes($now);
    }

    public function markAsCompleted(VitalSign $vitalSign): void
    {
        $this->last_recorded_at = $vitalSign->recorded_at;
        $this->next_due_at = $this->calculateNextDueTime($vitalSign->recorded_at);
        $this->save();

        $this->alerts()
            ->whereIn('status', ['pending', 'due', 'overdue'])
            ->update(['status' => 'completed']);
    }
}
