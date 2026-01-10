<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionStatusChange extends Model
{
    protected $fillable = [
        'prescription_id',
        'action',
        'performed_by_id',
        'performed_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }

    public function isDiscontinue(): bool
    {
        return $this->action === 'discontinued';
    }

    public function isResume(): bool
    {
        return $this->action === 'resumed';
    }
}
