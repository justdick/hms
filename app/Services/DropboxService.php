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

    protected string $tokenUrl = 'https://api.dropboxapi.com/oauth2/token';

    public function isConfigured(): bool
    {
        $settings = BackupSettings::getInstance();

        if (! $settings->dropbox_enabled) {
            return false;
        }

        // Need either a valid access token or a refresh token to get one
        if (! empty($settings->dropbox_refresh_token) && ! empty($settings->dropbox_app_key) && ! empty($settings->dropbox_app_secret)) {
            return true;
        }

        return ! empty($settings->dropbox_access_token);
    }

    /**
     * Get a valid access token, refreshing if needed.
     */
    protected function getAccessToken(): string
    {
        $settings = BackupSettings::getInstance();

        // If we have a refresh token, always use it to get a fresh access token
        if (! empty($settings->dropbox_refresh_token) && ! empty($settings->dropbox_app_key) && ! empty($settings->dropbox_app_secret)) {
            return $this->refreshAccessToken($settings);
        }

        if (! empty($settings->dropbox_access_token)) {
            return $settings->dropbox_access_token;
        }

        throw new RuntimeException('No Dropbox access token or refresh token configured.');
    }

    /**
     * Refresh the access token using the refresh token.
     */
    protected function refreshAccessToken(BackupSettings $settings): string
    {
        try {
            $response = Http::asForm()->post($this->tokenUrl, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $settings->dropbox_refresh_token,
                'client_id' => $settings->dropbox_app_key,
                'client_secret' => $settings->dropbox_app_secret,
            ]);

            if ($response->successful()) {
                $newToken = $response->json('access_token');

                // Save the new access token
                $settings->dropbox_access_token = $newToken;
                $settings->save();

                Log::info('Dropbox access token refreshed successfully');

                return $newToken;
            }

            $error = $response->json('error_description') ?? $response->body();
            throw new RuntimeException('Failed to refresh Dropbox token: '.$error);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to refresh Dropbox token: '.$e->getMessage());
        }
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

        $token = $this->getAccessToken();
        $settings = BackupSettings::getInstance();
        $folderPath = $this->normalizePath($settings->dropbox_folder_path ?? '/HMS Backups');

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->listFolderUrl, [
                'path' => $folderPath === '/' ? '' : $folderPath,
                'limit' => 1,
            ]);

        if ($response->successful() || $response->status() === 409) {
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

        $token = $this->getAccessToken();
        $settings = BackupSettings::getInstance();
        $folderPath = $this->normalizePath($settings->dropbox_folder_path ?? '/HMS Backups');
        $destinationPath = rtrim($folderPath, '/').'/'.$filename;

        try {
            $fileSize = filesize($filePath);
            $timeout = max(300, (int) ceil($fileSize / 50000));

            $previousMaxExecution = (int) ini_get('max_execution_time');
            set_time_limit($timeout + 60);

            $response = Http::timeout($timeout)
                ->withToken($token)
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
                set_time_limit($previousMaxExecution);
                Log::info('Dropbox upload successful', [
                    'path' => $destinationPath,
                    'filename' => $filename,
                ]);

                return $destinationPath;
            }

            set_time_limit($previousMaxExecution);
            $error = $response->json('error_summary') ?? $response->body();
            Log::error('Dropbox upload failed', ['filename' => $filename, 'error' => $error]);

            return null;

        } catch (\Exception $e) {
            set_time_limit($previousMaxExecution ?? 120);
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
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->deleteUrl, ['path' => $filePath]);

            if ($response->successful()) {
                Log::info('Dropbox file deleted', ['path' => $filePath]);

                return true;
            }

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
