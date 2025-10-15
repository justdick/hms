<?php

use App\Http\Controllers\Vitals\VitalSignController;
use App\Http\Controllers\Ward\MedicationAdministrationController;
use App\Http\Controllers\Ward\NursingNoteController;
use App\Http\Controllers\Ward\WardController;
use App\Http\Controllers\Ward\WardPatientController;
use App\Http\Controllers\Ward\WardRoundController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('wards')->name('wards.')->group(function () {
    Route::get('/', [WardController::class, 'index'])->name('index');
    Route::get('/create', [WardController::class, 'create'])->name('create');
    Route::post('/', [WardController::class, 'store'])->name('store');

    // Patient routes (must come before {ward} routes)
    Route::get('/{ward}/patients/{admission}', [WardPatientController::class, 'show'])->name('patients.show');

    Route::get('/{ward}', [WardController::class, 'show'])->name('show');
    Route::get('/{ward}/edit', [WardController::class, 'edit'])->name('edit');
    Route::put('/{ward}', [WardController::class, 'update'])->name('update');
    Route::delete('/{ward}', [WardController::class, 'destroy'])->name('destroy');
    Route::post('/{ward}/toggle-status', [WardController::class, 'toggleStatus'])->name('toggle-status');
});

// Patient Admission Routes
Route::middleware(['auth', 'verified'])->prefix('admissions')->name('admissions.')->group(function () {
    Route::post('/{admission}/vitals', [VitalSignController::class, 'storeForAdmission'])->name('vitals.store');

    // Nursing Notes
    Route::get('/{admission}/nursing-notes', [NursingNoteController::class, 'index'])->name('nursing-notes.index');
    Route::post('/{admission}/nursing-notes', [NursingNoteController::class, 'store'])->name('nursing-notes.store');
    Route::put('/{admission}/nursing-notes/{nursingNote}', [NursingNoteController::class, 'update'])->name('nursing-notes.update');
    Route::delete('/{admission}/nursing-notes/{nursingNote}', [NursingNoteController::class, 'destroy'])->name('nursing-notes.destroy');

    // Medication Administration
    Route::get('/{admission}/medications', [MedicationAdministrationController::class, 'index'])->name('medications.index');
    Route::post('/{administration}/administer', [MedicationAdministrationController::class, 'administer'])->name('medications.administer');
    Route::post('/{administration}/hold', [MedicationAdministrationController::class, 'hold'])->name('medications.hold');
    Route::post('/{administration}/refuse', [MedicationAdministrationController::class, 'refuse'])->name('medications.refuse');
    Route::post('/{administration}/omit', [MedicationAdministrationController::class, 'omit'])->name('medications.omit');

    // Ward Rounds
    Route::get('/{admission}/ward-rounds', [WardRoundController::class, 'index'])->name('ward-rounds.index');
    Route::post('/{admission}/ward-rounds', [WardRoundController::class, 'store'])->name('ward-rounds.store');
    Route::put('/{admission}/ward-rounds/{wardRound}', [WardRoundController::class, 'update'])->name('ward-rounds.update');
    Route::delete('/{admission}/ward-rounds/{wardRound}', [WardRoundController::class, 'destroy'])->name('ward-rounds.destroy');
});
