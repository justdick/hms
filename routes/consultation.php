<?php

use App\Http\Controllers\Consultation\ConsultationController;
use App\Http\Controllers\Consultation\DiagnosisController;
use App\Http\Controllers\Consultation\LabOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('consultation')->name('consultation.')->group(function () {

    // Consultation management
    Route::get('/', [ConsultationController::class, 'index'])->name('index');
    Route::post('/', [ConsultationController::class, 'store'])->name('store');
    Route::get('/{consultation}', [ConsultationController::class, 'show'])->name('show');
    Route::patch('/{consultation}', [ConsultationController::class, 'update'])->name('update');
    Route::post('/{consultation}/complete', [ConsultationController::class, 'complete'])->name('complete');

    // Diagnosis management
    Route::prefix('/{consultation}/diagnoses')->name('diagnoses.')->group(function () {
        Route::post('/', [DiagnosisController::class, 'store'])->name('store');
        Route::patch('/{diagnosis}', [DiagnosisController::class, 'update'])->name('update');
        Route::delete('/{diagnosis}', [DiagnosisController::class, 'destroy'])->name('destroy');
    });

    // ICD code search
    Route::get('/diagnoses/search', [DiagnosisController::class, 'search'])->name('diagnoses.search');

    // Lab order management
    Route::prefix('/{consultation}/lab-orders')->name('lab-orders.')->group(function () {
        Route::post('/', [LabOrderController::class, 'store'])->name('store');
        Route::patch('/{labOrder}', [LabOrderController::class, 'update'])->name('update');
        Route::post('/{labOrder}/cancel', [LabOrderController::class, 'cancel'])->name('cancel');
    });

    // Lab orders management (for lab technicians)
    Route::prefix('lab-orders')->name('lab-orders.')->group(function () {
        Route::get('/', [LabOrderController::class, 'index'])->name('index');
        Route::patch('/{labOrder}/status', [LabOrderController::class, 'updateStatus'])->name('update-status');
    });

});
