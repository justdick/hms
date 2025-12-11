<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LabOrder extends Model
{
    /** @use HasFactory<\Database\Factories\LabOrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // When a lab order is deleted, also delete related charge and claim items
        static::deleting(function (LabOrder $labOrder) {
            // Get the patient checkin ID based on the orderable type
            $checkinId = null;

            if ($labOrder->orderable_type === Consultation::class) {
                $labOrder->loadMissing('orderable.patientCheckin');
                $checkinId = $labOrder->orderable?->patientCheckin?->id;
            } elseif ($labOrder->orderable_type === WardRound::class) {
                $labOrder->loadMissing('orderable.patientAdmission.consultation.patientCheckin');
                $checkinId = $labOrder->orderable?->patientAdmission?->consultation?->patientCheckin?->id;
            } else {
                // Fallback to old consultation relationship
                $labOrder->loadMissing('consultation.patientCheckin');
                $checkinId = $labOrder->consultation?->patientCheckin?->id;
            }

            if (! $checkinId) {
                return;
            }

            // Load lab service to get the code
            $labOrder->loadMissing('labService');
            $serviceCode = $labOrder->labService?->code;

            if (! $serviceCode) {
                return;
            }

            // Find and delete related charge
            $charge = Charge::where('service_type', 'laboratory')
                ->where('patient_checkin_id', $checkinId)
                ->where('service_code', $serviceCode)
                ->first();

            if ($charge) {
                // Delete claim items linked to this charge
                InsuranceClaimItem::where('charge_id', $charge->id)->delete();
                // Delete the charge
                $charge->delete();
            }
        });
    }

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
        'migrated_from_mittag',
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

    public function charge(): HasOne
    {
        return $this->hasOne(Charge::class, 'service_code', 'id')
            ->where('service_type', 'lab');
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
