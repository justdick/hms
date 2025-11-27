<?php

use App\Http\Controllers\Admin\DrugImportController;
use App\Http\Controllers\Pharmacy\DispensingController;
use App\Http\Controllers\Pharmacy\DrugController;
use App\Http\Controllers\Pharmacy\PharmacyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:pharmacy.view'])->prefix('pharmacy')->name('pharmacy.')->group(function () {
    // Main Pharmacy Dashboard
    Route::get('/', [PharmacyController::class, 'index'])->name('index');

    // Drug Management
    Route::resource('drugs', DrugController::class);

    // Drug Import
    Route::get('drugs-import/template', [DrugImportController::class, 'downloadTemplate'])->name('drugs.import.template');
    Route::post('drugs-import', [DrugImportController::class, 'import'])->name('drugs.import');
    Route::get('drugs/{drug}/batches', [DrugController::class, 'batches'])->name('drugs.batches')->middleware('can:drugs.manage-batches');
    Route::post('drugs/{drug}/batches', [DrugController::class, 'storeBatch'])->name('drugs.batches.store')->middleware('can:drugs.manage-batches');

    // Dispensing Workflow
    Route::get('dispensing', [DispensingController::class, 'index'])->name('dispensing.index')->middleware('can:dispensing.view');
    Route::get('dispensing/search', [DispensingController::class, 'search'])->name('dispensing.search')->middleware('can:dispensing.view');
    Route::get('dispensing/patients/{patient}', [DispensingController::class, 'show'])->name('dispensing.show')->middleware('can:dispensing.view');
    Route::get('dispensing/patients/{patient}/prescriptions', [DispensingController::class, 'getPrescriptionsForDispensing'])->name('dispensing.prescriptions')->middleware('can:dispensing.view');

    // Touchpoint 1: Review (POST only - handled via modal)
    Route::post('dispensing/patients/{patient}/review', [DispensingController::class, 'updateReview'])->name('dispensing.review.update')->middleware('can:dispensing.review');

    // Touchpoint 2: Dispense
    Route::get('dispensing/patients/{patient}/dispense', [DispensingController::class, 'showDispense'])->name('dispensing.dispense.show')->middleware('can:dispensing.process');
    Route::post('prescriptions/{prescription}/dispense', [DispensingController::class, 'processDispensing'])->name('dispensing.process')->middleware('can:dispensing.process');

    // Minor Procedure Supplies
    Route::get('dispensing/patients/{patient}/supplies', [DispensingController::class, 'getSuppliesForDispensing'])->name('dispensing.supplies')->middleware('can:dispensing.view');
    Route::post('supplies/{supply}/dispense', [DispensingController::class, 'dispenseSupply'])->name('supplies.dispense')->middleware('can:dispensing.process');

    // Stock Management
    Route::get('inventory', [DrugController::class, 'inventory'])->name('inventory.index')->middleware('can:inventory.view');
    Route::get('inventory/low-stock', [DrugController::class, 'lowStock'])->name('inventory.low-stock')->middleware('can:inventory.view');
    Route::get('inventory/expiring', [DrugController::class, 'expiring'])->name('inventory.expiring')->middleware('can:inventory.view');
});
