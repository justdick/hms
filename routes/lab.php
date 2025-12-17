<?php

use App\Http\Controllers\Admin\LabServiceImportController;
use App\Http\Controllers\Lab\LabController;
use App\Http\Controllers\Lab\LabServiceConfigurationController;
use App\Http\Controllers\Lab\LabServiceSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('lab')->name('lab.')->group(function () {
    // Lab service search for async select
    Route::get('/services/search', [LabServiceSearchController::class, 'search'])->name('services.search');

    Route::get('/', [LabController::class, 'index'])->middleware('lab.hide_details')->name('index');
    Route::get('/consultations/{consultation}', [LabController::class, 'showConsultation'])->middleware('lab.hide_details')->name('consultations.show');
    Route::get('/ward-rounds/{wardRound}', [LabController::class, 'showWardRound'])->middleware('lab.hide_details')->name('ward-rounds.show');
    Route::get('/orders/{labOrder}', [LabController::class, 'show'])->middleware('lab.hide_details')->name('orders.show');
    Route::patch('/orders/{labOrder}/collect-sample', [LabController::class, 'collectSample'])->middleware('billing.enforce:laboratory')->name('orders.collect-sample');
    Route::patch('/orders/{labOrder}/start-processing', [LabController::class, 'startProcessing'])->middleware('billing.enforce:laboratory')->name('orders.start-processing');
    Route::patch('/orders/{labOrder}/complete', [LabController::class, 'complete'])->middleware('billing.enforce:laboratory')->name('orders.complete');
    Route::patch('/orders/{labOrder}/cancel', [LabController::class, 'cancel'])->name('orders.cancel');

    // Lab Service Configuration Routes
    Route::prefix('services')->name('services.')->group(function () {
        Route::get('/configuration', [LabServiceConfigurationController::class, 'index'])->name('configuration.index');
        Route::post('/configuration', [LabServiceConfigurationController::class, 'store'])->name('configuration.store');

        // Lab Service Import
        Route::get('/import/template', [LabServiceImportController::class, 'downloadTemplate'])->name('import.template');
        Route::post('/import', [LabServiceImportController::class, 'import'])->name('import');
        Route::get('/configuration/{labService}', [LabServiceConfigurationController::class, 'show'])->name('configuration.show');
        Route::put('/configuration/{labService}', [LabServiceConfigurationController::class, 'update'])->name('configuration.update');

        // API routes for dynamic functionality
        Route::get('/suggest-code', [LabServiceConfigurationController::class, 'suggestCode'])->name('suggest-code');
        Route::post('/create-category', [LabServiceConfigurationController::class, 'createCategory'])->name('create-category');
    });
});
