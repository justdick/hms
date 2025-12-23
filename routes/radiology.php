<?php

use App\Http\Controllers\Radiology\ImagingAttachmentController;
use App\Http\Controllers\Radiology\RadiologyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('radiology')->name('radiology.')->group(function () {
    // Radiology worklist
    Route::get('/', [RadiologyController::class, 'index'])->name('index');
    Route::get('/orders/{labOrder}', [RadiologyController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{labOrder}/in-progress', [RadiologyController::class, 'markInProgress'])->name('orders.in-progress');
    Route::patch('/orders/{labOrder}/complete', [RadiologyController::class, 'complete'])->name('orders.complete');

    // Imaging attachments
    Route::post('/orders/{labOrder}/attachments', [ImagingAttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{imagingAttachment}', [ImagingAttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/attachments/{imagingAttachment}/download', [ImagingAttachmentController::class, 'download'])->name('attachments.download');
});
