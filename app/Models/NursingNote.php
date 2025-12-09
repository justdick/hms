<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NursingNote extends Model
{
    /** @use HasFactory<\Database\Factories\NursingNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_admission_id',
        'nurse_id',
        'type',
        'note',
        'noted_at',
        'migrated_from_mittag',
    ];

    protected function casts(): array
    {
        return [
            'noted_at' => 'datetime',
        ];
    }

    public function patientAdmission(): BelongsTo
    {
        return $this->belongsTo(PatientAdmission::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }

    public function scopeByType($query, string $type): void
    {
        $query->where('type', $type);
    }

    public function scopeRecent($query): void
    {
        $query->orderBy('noted_at', 'desc');
    }
}
