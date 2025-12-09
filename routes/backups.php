<?php

use App\Http\Controllers\Backup\BackupController;
use App\Http\Controllers\Backup\BackupSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // Backup settings routes (must be before {backup} routes to avoid conflict)
    Route::get('backups/settings', [BackupSettingsController::class, 'edit'])->name('backups.settings');
    Route::put('backups/settings', [BackupSettingsController::class, 'update'])->name('backups.settings.update');
    Route::post('backups/settings/test-google-drive', [BackupSettingsController::class, 'testGoogleDrive'])->name('backups.settings.test-google-drive');

    // Backup management routes
    Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
    Route::get('backups/{backup}', [BackupController::class, 'show'])->name('backups.show');
    Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
    Route::get('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::post('backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::post('backups/{backup}/toggle-protection', [BackupController::class, 'toggleProtection'])->name('backups.toggle-protection');
});
