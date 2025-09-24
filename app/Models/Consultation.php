<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Consultation extends Model
{
    /** @use HasFactory<\Database\Factories\ConsultationFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_checkin_id',
        'doctor_id',
        'started_at',
        'completed_at',
        'status',
        'chief_complaint',
        'subjective_notes',
        'objective_notes',
        'assessment_notes',
        'plan_notes',
        'follow_up_date',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'follow_up_date' => 'date',
            'status' => 'string',
        ];
    }

    public function patientCheckin(): BelongsTo
    {
        return $this->belongsTo(PatientCheckin::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(ConsultationDiagnosis::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function bill(): HasOne
    {
        return $this->hasOne(PatientBill::class);
    }

    public function scopeToday($query): void
    {
        $query->whereDate('started_at', today());
    }

    public function scopeByStatus($query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeInProgress($query): void
    {
        $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query): void
    {
        $query->where('status', 'completed');
    }

    public function scopeAccessibleTo($query, User $user): void
    {
        // Admin can see all consultations
        if ($user->hasRole('Admin') || $user->can('consultations.view-all')) {
            return;
        }

        // Own consultations only
        if ($user->can('consultations.view-own')) {
            $query->where('doctor_id', $user->id);

            return;
        }

        // Department-based access
        if ($user->can('consultations.view-dept')) {
            $query->whereHas('patientCheckin', function ($q) use ($user) {
                $q->whereIn('department_id', $user->departments->pluck('id'));
            });

            return;
        }

        // No access
        $query->whereRaw('1 = 0');
    }

    public function markInProgress(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->patientCheckin->update([
            'consultation_started_at' => now(),
            'status' => 'in_consultation',
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->patientCheckin->update([
            'consultation_completed_at' => now(),
            'status' => 'completed',
        ]);
    }
}
