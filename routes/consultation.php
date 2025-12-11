<?php

use App\Http\Controllers\Admission\AdmissionController;
use App\Http\Controllers\Consultation\ConsultationController;
use App\Http\Controllers\Consultation\ConsultationProcedureController;
use App\Http\Controllers\Consultation\ConsultationTransferController;
use App\Http\Controllers\Consultation\DiagnosisController;
use App\Http\Controllers\Consultation\LabOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('consultation')->name('consultation.')->group(function () {

    // Consultation management
    Route::get('/', [ConsultationController::class, 'index'])->name('index');
    Route::post('/', [ConsultationController::class, 'store'])->middleware('billing.enforce:consultation')->name('store');
    Route::get('/{consultation}', [ConsultationController::class, 'show'])->name('show');
    Route::patch('/{consultation}', [ConsultationController::class, 'update'])->name('update');
    Route::post('/{consultation}/complete', [ConsultationController::class, 'complete'])->name('complete');
    Route::post('/{consultation}/transfer', [ConsultationTransferController::class, 'store'])->name('transfer');

    // Prescription management
    Route::post('/{consultation}/prescriptions', [ConsultationController::class, 'storePrescription'])->name('prescriptions.store');
    Route::delete('/{consultation}/prescriptions/{prescription}', [ConsultationController::class, 'destroyPrescription'])->name('prescriptions.destroy');

    // Admission management
    Route::post('/{consultation}/admit', [AdmissionController::class, 'store'])->name('admit');
    Route::get('/wards/available', [AdmissionController::class, 'getAvailableWards'])->name('wards.available');

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
        Route::delete('/{labOrder}', [LabOrderController::class, 'destroy'])->name('destroy');
    });

    // Procedure management (Theatre tab)
    Route::prefix('/{consultation}/procedures')->name('procedures.')->group(function () {
        Route::post('/', [ConsultationProcedureController::class, 'store'])->name('store');
        Route::delete('/{procedure}', [ConsultationProcedureController::class, 'destroy'])->name('destroy');
    });

    // Lab orders management (for lab technicians)
    Route::prefix('lab-orders')->name('lab-orders.')->group(function () {
        Route::get('/', [LabOrderController::class, 'index'])->name('index');
        Route::patch('/{labOrder}/status', [LabOrderController::class, 'updateStatus'])->name('update-status');
    });

});
