<?php

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Http\Requests\RestoreBackupRequest;
use App\Http\Requests\StoreBackupRequest;
use App\Models\Backup;
use App\Services\BackupService;
use App\Services\RestoreService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
        protected RestoreService $restoreService
    ) {}

    /**
     * Display a listing of backups.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Backup::class);

        $backups = Backup::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Backup/Index', [
            'backups' => $backups,
        ]);
    }

    /**
     * Store a newly created backup.
     */
    public function store(StoreBackupRequest $request): RedirectResponse
    {
        $this->authorize('create', Backup::class);

        try {
            $backup = $this->backupService->createBackup('manual_ui', $request->user());

            return redirect()->route('admin.backups.index')
                ->with('success', "Backup '{$backup->filename}' created successfully.");
        } catch (RuntimeException $e) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'Backup creation failed: '.$e->getMessage());
        }
    }

    /**
     * Display the specified backup.
     */
    public function show(Backup $backup): Response
    {
        $this->authorize('view', $backup);

        return Inertia::render('Backup/Show', [
            'backup' => $backup->load('creator', 'logs.user'),
        ]);
    }

    /**
     * Remove the specified backup.
     */
    public function destroy(Backup $backup): RedirectResponse
    {
        $this->authorize('delete', $backup);

        $filename = $backup->filename;

        if ($this->backupService->deleteBackup($backup, request()->user())) {
            return redirect()->route('admin.backups.index')
                ->with('success', "Backup '{$filename}' deleted successfully.");
        }

        return redirect()->route('admin.backups.index')
            ->with('error', 'Failed to delete backup.');
    }

    /**
     * Download the specified backup.
     */
    public function download(Backup $backup): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $backup);

        try {
            return $this->backupService->downloadBackup($backup);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'Download failed: '.$e->getMessage());
        }
    }

    /**
     * Restore from the specified backup.
     */
    public function restore(RestoreBackupRequest $request, Backup $backup): RedirectResponse
    {
        $this->authorize('restore', $backup);

        try {
            $this->restoreService->restore($backup, $request->user());

            return redirect()->route('admin.backups.index')
                ->with('success', "Database restored from backup '{$backup->filename}' successfully.");
        } catch (RuntimeException $e) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'Restore failed: '.$e->getMessage());
        }
    }

    /**
     * Toggle protection status of a backup.
     */
    public function toggleProtection(Backup $backup): RedirectResponse
    {
        $this->authorize('delete', $backup);

        $backup->is_protected = ! $backup->is_protected;
        $backup->save();

        $status = $backup->is_protected ? 'protected' : 'unprotected';

        return redirect()->back()
            ->with('success', "Backup '{$backup->filename}' is now {$status}.");
    }
}
