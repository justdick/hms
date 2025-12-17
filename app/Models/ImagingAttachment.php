<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ImagingAttachment extends Model
{
    /** @use HasFactory<\Database\Factories\ImagingAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'lab_order_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'description',
        'is_external',
        'external_facility_name',
        'external_study_date',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_external' => 'boolean',
            'external_study_date' => 'date',
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * Get the lab order this attachment belongs to.
     */
    public function labOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class);
    }

    /**
     * Get the user who uploaded this attachment.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the URL for the attachment.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('local')->url($this->file_path);
    }

    /**
     * Get the thumbnail URL for the attachment.
     * Returns the same URL for now - thumbnail generation can be added later.
     */
    public function getThumbnailUrlAttribute(): string
    {
        // For PDFs, return a generic PDF icon path
        if ($this->file_type === 'application/pdf') {
            return asset('images/pdf-icon.png');
        }

        // For images, return the same path (thumbnail generation can be added later)
        return $this->url;
    }

    /**
     * Check if this is an image file.
     */
    public function isImage(): bool
    {
        return in_array($this->file_type, ['image/jpeg', 'image/png']);
    }

    /**
     * Check if this is a PDF file.
     */
    public function isPdf(): bool
    {
        return $this->file_type === 'application/pdf';
    }
}
