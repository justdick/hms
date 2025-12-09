<?php

use App\Http\Controllers\Admin\ProcedureTypeImportController;
use App\Http\Controllers\MinorProcedure\MinorProcedureController;
use App\Http\Controllers\MinorProcedure\MinorProcedureTypeController;
use Illuminate\Support\Facades\Route;

// Procedure Type Import Routes (admin prefix)
Route::middleware(['auth', 'verified'])->prefix('admin/procedure-types')->group(function () {
    Route::get('/template', [ProcedureTypeImportController::class, 'downloadTemplate'])->name('procedure-types.import.template');
    Route::post('/import', [ProcedureTypeImportController::class, 'import'])->name('procedure-types.import');
});

Route::middleware(['auth', 'verified'])->prefix('minor-procedures')->name('minor-procedures.')->group(function () {

    // Procedure Types Configuration (must come before /{minorProcedure} route)
    Route::prefix('types')->name('types.')->group(function () {
        Route::get('/', [MinorProcedureTypeController::class, 'index'])->name('index');
        Route::get('/suggest-code', [MinorProcedureTypeController::class, 'suggestCode'])->name('suggest-code');
        Route::post('/', [MinorProcedureTypeController::class, 'store'])->name('store');
        Route::put('/{procedureType}', [MinorProcedureTypeController::class, 'update'])->name('update');
        Route::delete('/{procedureType}', [MinorProcedureTypeController::class, 'destroy'])->name('destroy');
    });

    // Minor Procedures management
    Route::get('/', [MinorProcedureController::class, 'index'])->name('index');
    Route::get('/search', [MinorProcedureController::class, 'search'])->name('search');
    Route::post('/', [MinorProcedureController::class, 'store'])->name('store');
    Route::get('/{minorProcedure}', [MinorProcedureController::class, 'show'])->name('show');

});
