<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Backup extends Model
{
    /** @use HasFactory<\Database\Factories\BackupFactory> */
    use HasFactory;

    protected $fillable = [
        'filename',
        'file_size',
        'file_path',
        'google_drive_file_id',
        'status',
        'source',
        'is_protected',
        'created_by',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_protected' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who created this backup.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the audit logs for this backup.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(BackupLog::class);
    }

    /**
     * Scope to get only completed backups.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get only unprotected backups.
     */
    public function scopeUnprotected(Builder $query): Builder
    {
        return $query->where('is_protected', false);
    }

    /**
     * Scope to get backups that exist locally.
     */
    public function scopeLocal(Builder $query): Builder
    {
        return $query->whereNotNull('file_path');
    }

    /**
     * Scope to get backups that exist on Google Drive.
     */
    public function scopeOnGoogleDrive(Builder $query): Builder
    {
        return $query->whereNotNull('google_drive_file_id');
    }

    /**
     * Check if the backup exists on Google Drive.
     */
    public function isOnGoogleDrive(): bool
    {
        return ! empty($this->google_drive_file_id);
    }

    /**
     * Check if the backup exists locally.
     */
    public function isLocal(): bool
    {
        return ! empty($this->file_path);
    }
}
