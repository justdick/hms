<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAccessOverride extends Model
{
    protected $fillable = [
        'patient_checkin_id',
        'service_type',
        'service_code',
        'reason',
        'authorized_by',
        'authorized_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'authorized_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function patientCheckin(): BelongsTo
    {
        return $this->belongsTo(PatientCheckin::class);
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function getRemainingDuration(): string
    {
        if ($this->isExpired()) {
            return 'Expired';
        }

        $diff = now()->diff($this->expires_at);

        return sprintf('%dh %dm', $diff->h, $diff->i);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>', now());
    }

    public function scopeForService($query, string $serviceType, ?string $serviceCode = null)
    {
        $query = $query->where('service_type', $serviceType);

        if ($serviceCode) {
            $query->where('service_code', $serviceCode);
        }

        return $query;
    }
}
