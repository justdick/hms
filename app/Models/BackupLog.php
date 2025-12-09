<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    /** @use HasFactory<\Database\Factories\BackupLogFactory> */
    use HasFactory;

    protected $fillable = [
        'backup_id',
        'user_id',
        'action',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'action' => 'string',
        ];
    }

    /**
     * Get the backup this log entry belongs to.
     */
    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
