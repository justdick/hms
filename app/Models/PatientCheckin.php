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
        'service_date',
        'vitals_taken_at',
        'consultation_started_at',
        'consultation_completed_at',
        'status',
        'notes',
        'claim_check_code',
        'migrated_from_mittag',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'service_date' => 'date',
            'vitals_taken_at' => 'datetime',
            'consultation_started_at' => 'datetime',
            'consultation_completed_at' => 'datetime',
            'status' => 'string',
        ];
    }

    /**
     * Check if this check-in was backdated (entered after the service date).
     */
    public function isBackdated(): bool
    {
        if (! $this->service_date || ! $this->created_at) {
            return false;
        }

        return $this->service_date->toDateString() !== $this->created_at->toDateString();
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

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function insuranceClaim(): HasOne
    {
        return $this->hasOne(InsuranceClaim::class);
    }

    public function minorProcedures(): HasMany
    {
        return $this->hasMany(MinorProcedure::class);
    }

    public function scopeToday($query): void
    {
        $query->whereDate('checked_in_at', today());
    }

    public function scopeByStatus($query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeAccessibleTo($query, User $user): void
    {
        // Admin can see all
        if ($user->hasRole('Admin') || $user->can('checkins.view-all')) {
            return;
        }

        // Department-based access
        if ($user->can('checkins.view-dept')) {
            $query->whereIn('department_id', $user->departments->pluck('id'));
        } else {
            // No access
            $query->whereRaw('1 = 0');
        }
    }

    public function markVitalsTaken(): void
    {
        $this->update([
            'vitals_taken_at' => now(),
            'status' => 'vitals_taken',
        ]);
    }

    public function markAwaitingConsultation(): void
    {
        $this->update([
            'status' => 'awaiting_consultation',
        ]);
    }

    public function cancel(?string $reason = null): void
    {
        // Update check-in status
        $this->update([
            'status' => 'cancelled',
            'notes' => $reason ? "{$this->notes}\nCancelled: {$reason}" : "{$this->notes}\nCancelled",
        ]);

        // Delete the associated insurance claim to free up the CCC for reuse
        // Use query to ensure we get the claim even if relationship wasn't loaded
        InsuranceClaim::where('patient_checkin_id', $this->id)->delete();

        // Void all pending charges (unpaid charges are forgiven)
        // Keep paid charges as-is (no refund policy)
        $this->charges()
            ->where('status', 'pending')
            ->each(function ($charge) use ($reason) {
                $charge->markAsVoided($reason);
            });
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isIncomplete(): bool
    {
        return in_array($this->status, ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation']);
    }
}
