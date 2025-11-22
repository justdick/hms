<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MinorProcedure extends Model
{
    /** @use HasFactory<\Database\Factories\MinorProcedureFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_checkin_id',
        'nurse_id',
        'minor_procedure_type_id',
        'procedure_type', // Keep for backward compatibility during migration
        'procedure_notes',
        'performed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'status' => 'string',
        ];
    }

    public function patientCheckin(): BelongsTo
    {
        return $this->belongsTo(PatientCheckin::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(MinorProcedureType::class, 'minor_procedure_type_id');
    }

    public function diagnoses(): BelongsToMany
    {
        return $this->belongsToMany(Diagnosis::class, 'minor_procedure_diagnoses');
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(MinorProcedureSupply::class);
    }

    public function scopeToday($query): void
    {
        $query->whereDate('performed_at', today());
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
        // Admin can see all procedures
        if ($user->hasRole('Admin') || $user->can('minor-procedures.view-all')) {
            return;
        }

        // Department-based access
        if ($user->can('minor-procedures.view-dept')) {
            $query->whereHas('patientCheckin', function ($q) use ($user) {
                $q->whereIn('department_id', $user->departments->pluck('id'));
            });

            return;
        }

        // No access
        $query->whereRaw('1 = 0');
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
        ]);

        $this->patientCheckin->update([
            'status' => 'completed',
        ]);
    }
}
