<?php

namespace App\Services;

use App\Models\LabOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImagingStorageService
{
    /**
     * The storage disk to use for imaging files.
     */
    protected string $disk = 'local';

    /**
     * The base storage path for imaging files.
     */
    protected string $basePath = 'imaging';

    /**
     * Allowed file types for imaging uploads.
     */
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];

    /**
     * Allowed file extensions for imaging uploads.
     */
    protected array $allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'pdf',
    ];

    /**
     * Maximum file size in bytes (50MB).
     */
    protected int $maxFileSize = 52428800;

    /**
     * Store an uploaded imaging file.
     *
     * @param  UploadedFile  $file  The uploaded file
     * @param  LabOrder  $order  The lab order this file belongs to
     * @param  string|null  $customFilename  Optional custom filename
     * @return string The stored file path relative to the disk root
     *
     * @throws RuntimeException If file storage fails
     */
    public function store(UploadedFile $file, LabOrder $order, ?string $customFilename = null): string
    {
        $storagePath = $this->getStoragePath($order);

        // Ensure the directory exists
        if (! Storage::disk($this->disk)->exists($storagePath)) {
            Storage::disk($this->disk)->makeDirectory($storagePath);
        }

        // Generate filename
        $filename = $customFilename ?? $this->generateFilename($file);

        // Store the file
        $fullPath = $storagePath.'/'.$filename;

        $stored = Storage::disk($this->disk)->putFileAs(
            $storagePath,
            $file,
            $filename
        );

        if ($stored === false) {
            throw new RuntimeException('Failed to store imaging file.');
        }

        return $fullPath;
    }

    /**
     * Get the storage path for a lab order's imaging files.
     * Path structure: imaging/{year}/{month}/{patient_id}/{lab_order_id}/original
     *
     * @param  LabOrder  $order  The lab order
     * @return string The storage path
     */
    public function getStoragePath(LabOrder $order): string
    {
        // Load relationships if not already loaded
        $order->loadMissing(['orderable']);

        // Get patient ID based on orderable type
        $patientId = $this->getPatientIdFromOrder($order);

        $year = now()->format('Y');
        $month = now()->format('m');

        return sprintf(
            '%s/%s/%s/%d/%d/original',
            $this->basePath,
            $year,
            $month,
            $patientId,
            $order->id
        );
    }

    /**
     * Delete a file from storage.
     *
     * @param  string  $path  The file path relative to the disk root
     * @return bool True if deletion was successful
     */
    public function delete(string $path): bool
    {
        if (! Storage::disk($this->disk)->exists($path)) {
            return true; // File doesn't exist, consider it deleted
        }

        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Check if a file exists in storage.
     *
     * @param  string  $path  The file path relative to the disk root
     * @return bool True if the file exists
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Get the full filesystem path for a stored file.
     *
     * @param  string  $path  The file path relative to the disk root
     * @return string The full filesystem path
     */
    public function getFullPath(string $path): string
    {
        return Storage::disk($this->disk)->path($path);
    }

    /**
     * Get the file size in bytes.
     *
     * @param  string  $path  The file path relative to the disk root
     * @return int The file size in bytes
     */
    public function getFileSize(string $path): int
    {
        return Storage::disk($this->disk)->size($path);
    }

    /**
     * Get the allowed MIME types for imaging uploads.
     *
     * @return array<string> The allowed MIME types
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /**
     * Get the allowed file extensions for imaging uploads.
     *
     * @return array<string> The allowed extensions
     */
    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    /**
     * Get the maximum file size in bytes.
     *
     * @return int The maximum file size
     */
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * Validate a file for imaging upload.
     *
     * @param  UploadedFile  $file  The file to validate
     * @return array<string> Array of validation error messages (empty if valid)
     */
    public function validateFile(UploadedFile $file): array
    {
        $errors = [];

        // Check file type
        if (! $this->isValidFileType($file)) {
            $errors[] = 'Only JPEG, PNG, and PDF files are allowed.';
        }

        // Check file size
        if (! $this->isValidFileSize($file)) {
            $errors[] = 'File size exceeds 50MB limit.';
        }

        return $errors;
    }

    /**
     * Check if a file has a valid type for imaging upload.
     *
     * @param  UploadedFile  $file  The file to check
     * @return bool True if the file type is valid
     */
    public function isValidFileType(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($mimeType, $this->allowedMimeTypes, true)
            && in_array($extension, $this->allowedExtensions, true);
    }

    /**
     * Check if a file has a valid size for imaging upload.
     *
     * @param  UploadedFile  $file  The file to check
     * @return bool True if the file size is valid
     */
    public function isValidFileSize(UploadedFile $file): bool
    {
        return $file->getSize() <= $this->maxFileSize;
    }

    /**
     * Generate a unique filename for an uploaded file.
     *
     * @param  UploadedFile  $file  The uploaded file
     * @return string The generated filename
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $timestamp = now()->format('Ymd_His');
        $random = substr(md5(uniqid()), 0, 8);

        return sprintf('img_%s_%s.%s', $timestamp, $random, $extension);
    }

    /**
     * Get the patient ID from a lab order.
     *
     * @param  LabOrder  $order  The lab order
     * @return int The patient ID
     */
    protected function getPatientIdFromOrder(LabOrder $order): int
    {
        // Handle polymorphic orderable relationship
        if ($order->orderable) {
            // If orderable is a Consultation
            if ($order->orderable instanceof \App\Models\Consultation) {
                return $order->orderable->patientCheckin->patient_id ?? 0;
            }

            // If orderable is a WardRound
            if ($order->orderable instanceof \App\Models\WardRound) {
                return $order->orderable->patientAdmission->patient_id ?? 0;
            }
        }

        // Fallback to old consultation relationship
        if ($order->consultation) {
            return $order->consultation->patientCheckin->patient_id ?? 0;
        }

        return 0;
    }

    /**
     * Get the storage disk name.
     *
     * @return string The disk name
     */
    public function getDisk(): string
    {
        return $this->disk;
    }

    /**
     * Get the base storage path.
     *
     * @return string The base path
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
