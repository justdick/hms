<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalsAlert extends Model
{
    /** @use HasFactory<\Database\Factories\VitalsAlertFactory> */
    use HasFactory;

    protected $fillable = [
        'vitals_schedule_id',
        'patient_admission_id',
        'due_at',
        'status',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function vitalsSchedule(): BelongsTo
    {
        return $this->belongsTo(VitalsSchedule::class);
    }

    public function patientAdmission(): BelongsTo
    {
        return $this->belongsTo(PatientAdmission::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function markAsDue(): void
    {
        $this->status = 'due';
        $this->save();
    }

    public function markAsOverdue(): void
    {
        $this->status = 'overdue';
        $this->save();
    }

    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->save();
    }

    public function acknowledge(User $user): void
    {
        $this->acknowledged_at = now();
        $this->acknowledged_by = $user->id;
        $this->save();
    }
}
