<?php

namespace App\Services;

use App\Models\BackupSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DropboxService
{
    protected string $uploadUrl = 'https://content.dropboxapi.com/2/files/upload';

    protected string $deleteUrl = 'https://api.dropboxapi.com/2/files/delete_v2';

    protected string $listFolderUrl = 'https://api.dropboxapi.com/2/files/list_folder';

    public function isConfigured(): bool
    {
        $settings = BackupSettings::getInstance();

        return $settings->dropbox_enabled
            && ! empty($settings->dropbox_access_token);
    }

    /**
     * Test the Dropbox connection.
     *
     * @throws RuntimeException
     */
    public function testConnection(): bool
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Dropbox is not configured.');
        }

        $settings = BackupSettings::getInstance();
        $folderPath = $this->normalizePath($settings->dropbox_folder_path ?? '/HMS Backups');

        $response = Http::withToken($settings->dropbox_access_token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->listFolderUrl, [
                'path' => $folderPath === '/' ? '' : $folderPath,
                'limit' => 1,
            ]);

        if ($response->successful() || $response->status() === 409) {
            // 409 means folder doesn't exist yet — that's fine, we'll create on upload
            Log::info('Dropbox connection test successful');

            return true;
        }

        $error = $response->json('error_summary') ?? $response->body();
        throw new RuntimeException('Dropbox connection failed: '.$error);
    }

    /**
     * Upload a file to Dropbox.
     *
     * @return string|null The Dropbox file path on success, null on failure
     */
    public function upload(string $filePath, string $filename): ?string
    {
        if (! $this->isConfigured()) {
            Log::warning('Dropbox upload skipped - not configured');

            return null;
        }

        if (! file_exists($filePath)) {
            Log::error('Dropbox upload failed - file not found', ['file_path' => $filePath]);

            return null;
        }

        $settings = BackupSettings::getInstance();
        $folderPath = $this->normalizePath($settings->dropbox_folder_path ?? '/HMS Backups');
        $destinationPath = rtrim($folderPath, '/').'/'.$filename;

        try {
            $fileSize = filesize($filePath);
            // Allow 5 minutes for large files
            $timeout = max(300, (int) ceil($fileSize / 50000));

            $response = Http::timeout($timeout)
                ->withToken($settings->dropbox_access_token)
                ->withHeaders([
                    'Content-Type' => 'application/octet-stream',
                    'Dropbox-API-Arg' => json_encode([
                        'path' => $destinationPath,
                        'mode' => 'overwrite',
                        'autorename' => false,
                        'mute' => false,
                    ]),
                ])
                ->withBody(file_get_contents($filePath), 'application/octet-stream')
                ->post($this->uploadUrl);

            if ($response->successful()) {
                Log::info('Dropbox upload successful', [
                    'path' => $destinationPath,
                    'filename' => $filename,
                ]);

                return $destinationPath;
            }

            $error = $response->json('error_summary') ?? $response->body();
            Log::error('Dropbox upload failed', ['filename' => $filename, 'error' => $error]);

            return null;

        } catch (\Exception $e) {
            Log::error('Dropbox upload failed', ['filename' => $filename, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Delete a file from Dropbox.
     */
    public function delete(string $filePath): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $settings = BackupSettings::getInstance();

            $response = Http::withToken($settings->dropbox_access_token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->deleteUrl, ['path' => $filePath]);

            if ($response->successful()) {
                Log::info('Dropbox file deleted', ['path' => $filePath]);

                return true;
            }

            // 409 = file not found, treat as success
            if ($response->status() === 409) {
                return true;
            }

            Log::error('Dropbox delete failed', ['path' => $filePath, 'error' => $response->body()]);

            return false;

        } catch (\Exception $e) {
            Log::error('Dropbox delete failed', ['path' => $filePath, 'error' => $e->getMessage()]);

            return false;
        }
    }

    protected function normalizePath(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return $path === '/' ? '' : $path;
    }
}
