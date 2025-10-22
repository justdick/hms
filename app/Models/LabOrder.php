<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LabOrder extends Model
{
    /** @use HasFactory<\Database\Factories\LabOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'orderable_type',
        'orderable_id',
        'lab_service_id',
        'ordered_by',
        'ordered_at',
        'status',
        'priority',
        'special_instructions',
        'sample_collected_at',
        'result_entered_at',
        'result_values',
        'result_notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'sample_collected_at' => 'datetime',
            'result_entered_at' => 'datetime',
            'result_values' => 'array',
            'status' => 'string',
            'priority' => 'string',
        ];
    }

    public function orderable(): MorphTo
    {
        return $this->morphTo();
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function labService(): BelongsTo
    {
        return $this->belongsTo(LabService::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function scopeByStatus($query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeByPriority($query, string $priority): void
    {
        $query->where('priority', $priority);
    }

    public function scopePending($query): void
    {
        $query->whereIn('status', ['ordered', 'sample_collected', 'in_progress']);
    }

    public function scopeCompleted($query): void
    {
        $query->where('status', 'completed');
    }

    public function markSampleCollected(): void
    {
        $this->update([
            'status' => 'sample_collected',
            'sample_collected_at' => now(),
        ]);
    }

    public function markInProgress(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    public function markCompleted(?array $resultValues = null, ?string $resultNotes = null): void
    {
        $this->update([
            'status' => 'completed',
            'result_entered_at' => now(),
            'result_values' => $resultValues,
            'result_notes' => $resultNotes,
        ]);
    }
}
