<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    /** @use HasFactory<\Database\Factories\ConsultationFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'patient_checkin_id',
        'department_id',
        'doctor_id',
        'consultation_number',
        'chief_complaint',
        'history_present_illness',
        'examination_findings',
        'diagnosis',
        'treatment_plan',
        'prescriptions',
        'follow_up_instructions',
        'status',
        'consultation_date',
    ];

    protected function casts(): array
    {
        return [
            'consultation_date' => 'datetime',
            'status' => 'string',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function scopeToday($query): void
    {
        $query->whereDate('consultation_date', today());
    }

    public function scopeByStatus($query, string $status): void
    {
        $query->where('status', $status);
    }

    public function markInProgress(): void
    {
        $this->update([
            'status' => 'in_progress'
        ]);

        $this->patientCheckin->update([
            'consultation_started_at' => now(),
            'status' => 'in_consultation'
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed'
        ]);

        $this->patientCheckin->update([
            'consultation_completed_at' => now(),
            'status' => 'completed'
        ]);
    }
}
