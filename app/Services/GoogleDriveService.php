<?php

namespace App\Services;

use App\Models\BackupSettings;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleDriveService
{
    /**
     * The Google Client instance.
     */
    protected ?GoogleClient $client = null;

    /**
     * The Google Drive service instance.
     */
    protected ?Drive $driveService = null;

    /**
     * Check if Google Drive integration is configured.
     */
    public function isConfigured(): bool
    {
        $settings = BackupSettings::getInstance();

        if (! $settings->google_drive_enabled) {
            return false;
        }

        if (empty($settings->google_credentials)) {
            return false;
        }

        if (empty($settings->google_drive_folder_id)) {
            return false;
        }

        return true;
    }

    /**
     * Test the Google Drive connection.
     *
     * @return bool True if connection is successful
     *
     * @throws RuntimeException If connection fails
     */
    public function testConnection(): bool
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Drive is not configured.');
        }

        try {
            $driveService = $this->getDriveService();
            $settings = BackupSettings::getInstance();

            // Try to get the folder to verify access
            $folder = $driveService->files->get($settings->google_drive_folder_id, [
                'fields' => 'id,name',
            ]);

            Log::info('Google Drive connection test successful', [
                'folder_id' => $folder->getId(),
                'folder_name' => $folder->getName(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Google Drive connection test failed', [
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Google Drive connection failed: '.$e->getMessage());
        }
    }

    /**
     * Upload a file to Google Drive.
     *
     * @param  string  $filePath  The local file path
     * @param  string  $filename  The filename to use on Google Drive
     * @return string|null The Google Drive file ID, or null on failure
     */
    public function upload(string $filePath, string $filename): ?string
    {
        if (! $this->isConfigured()) {
            Log::warning('Google Drive upload skipped - not configured');

            return null;
        }

        if (! file_exists($filePath)) {
            Log::error('Google Drive upload failed - file not found', [
                'file_path' => $filePath,
            ]);

            return null;
        }

        try {
            $driveService = $this->getDriveService();
            $settings = BackupSettings::getInstance();

            // Create file metadata
            $fileMetadata = new DriveFile([
                'name' => $filename,
                'parents' => [$settings->google_drive_folder_id],
            ]);

            // Read file content
            $content = file_get_contents($filePath);

            // Upload file
            $file = $driveService->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/gzip',
                'uploadType' => 'multipart',
                'fields' => 'id,name,size',
            ]);

            Log::info('Google Drive upload successful', [
                'file_id' => $file->getId(),
                'filename' => $filename,
                'size' => $file->getSize(),
            ]);

            return $file->getId();

        } catch (\Exception $e) {
            Log::error('Google Drive upload failed', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Download a file from Google Drive.
     *
     * @param  string  $fileId  The Google Drive file ID
     * @return string The file content
     *
     * @throws RuntimeException If download fails
     */
    public function download(string $fileId): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Drive is not configured.');
        }

        try {
            $driveService = $this->getDriveService();

            // Get file content
            $response = $driveService->files->get($fileId, [
                'alt' => 'media',
            ]);

            $content = $response->getBody()->getContents();

            Log::info('Google Drive download successful', [
                'file_id' => $fileId,
                'size' => strlen($content),
            ]);

            return $content;

        } catch (\Exception $e) {
            Log::error('Google Drive download failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Google Drive download failed: '.$e->getMessage());
        }
    }

    /**
     * Delete a file from Google Drive.
     *
     * @param  string  $fileId  The Google Drive file ID
     * @return bool True if deletion was successful
     */
    public function delete(string $fileId): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Google Drive delete skipped - not configured');

            return false;
        }

        try {
            $driveService = $this->getDriveService();

            $driveService->files->delete($fileId);

            Log::info('Google Drive file deleted', [
                'file_id' => $fileId,
            ]);

            return true;

        } catch (\Google\Service\Exception $e) {
            // Check if file was already deleted (404)
            if ($e->getCode() === 404) {
                Log::warning('Google Drive file not found for deletion', [
                    'file_id' => $fileId,
                ]);

                return true; // Consider it a success if file doesn't exist
            }

            Log::error('Google Drive delete failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Google Drive delete failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the Google Drive service instance.
     *
     * @return Drive The Google Drive service
     *
     * @throws RuntimeException If service cannot be initialized
     */
    protected function getDriveService(): Drive
    {
        if ($this->driveService !== null) {
            return $this->driveService;
        }

        $client = $this->getClient();
        $this->driveService = new Drive($client);

        return $this->driveService;
    }

    /**
     * Get the Google Client instance.
     *
     * @return GoogleClient The Google Client
     *
     * @throws RuntimeException If client cannot be initialized
     */
    protected function getClient(): GoogleClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $settings = BackupSettings::getInstance();

        if (empty($settings->google_credentials)) {
            throw new RuntimeException('Google credentials not configured.');
        }

        try {
            $this->client = new GoogleClient;
            $this->client->setApplicationName('HMS Backup System');
            $this->client->setScopes([Drive::DRIVE_FILE]);

            // Parse credentials - can be JSON string or array
            $credentials = $settings->google_credentials;
            if (is_string($credentials)) {
                $credentials = json_decode($credentials, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Invalid Google credentials JSON.');
                }
            }

            // Use service account authentication
            $this->client->setAuthConfig($credentials);

            return $this->client;

        } catch (\Exception $e) {
            throw new RuntimeException('Failed to initialize Google client: '.$e->getMessage());
        }
    }

    /**
     * Reset the service instances (useful for testing).
     */
    public function reset(): void
    {
        $this->client = null;
        $this->driveService = null;
    }
}
