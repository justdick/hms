<?php

use App\Http\Controllers\Patient\PatientController;
use Illuminate\Support\Facades\Route;

// Patient Management Routes
Route::middleware(['auth'])->prefix('patients')->name('patients.')->group(function () {

    // Patient List and Management
    Route::get('/', [PatientController::class, 'index'])->name('index');
    Route::get('/{patient}', [PatientController::class, 'show'])->name('show');
    Route::get('/{patient}/edit', [PatientController::class, 'edit'])->name('edit');
    Route::patch('/{patient}', [PatientController::class, 'update'])->name('update');
});
