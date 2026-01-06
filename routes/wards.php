<?php

use App\Http\Controllers\Admission\AdmissionController;
use App\Http\Controllers\Vitals\VitalSignController;
use App\Http\Controllers\Ward\BedAssignmentController;
use App\Http\Controllers\Ward\MedicationAdministrationController;
use App\Http\Controllers\Ward\NursingNoteController;
use App\Http\Controllers\Ward\VitalsAlertController;
use App\Http\Controllers\Ward\VitalsScheduleController;
use App\Http\Controllers\Ward\WardController;
use App\Http\Controllers\Ward\WardPatientController;
use App\Http\Controllers\Ward\WardRoundController;
use App\Http\Controllers\Ward\WardRoundProcedureController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('wards')->name('wards.')->group(function () {
    Route::get('/', [WardController::class, 'index'])->name('index');
    Route::get('/create', [WardController::class, 'create'])->name('create');
    Route::post('/', [WardController::class, 'store'])->name('store');

    // Patient routes (must come before {ward} routes)
    Route::get('/{ward}/patients/{admission}', [WardPatientController::class, 'show'])->name('patients.show');
    Route::post('/{ward}/patients/{admission}/discharge', [WardPatientController::class, 'discharge'])->name('patients.discharge');

    Route::get('/{ward}', [WardController::class, 'show'])->name('show');
    Route::get('/{ward}/edit', [WardController::class, 'edit'])->name('edit');
    Route::put('/{ward}', [WardController::class, 'update'])->name('update');
    Route::delete('/{ward}', [WardController::class, 'destroy'])->name('destroy');
    Route::post('/{ward}/toggle-status', [WardController::class, 'toggleStatus'])->name('toggle-status');

    // Vitals Schedule Management
    Route::post('/{ward}/patients/{admission}/vitals-schedule', [VitalsScheduleController::class, 'store'])->name('patients.vitals-schedule.store');
    Route::put('/{ward}/patients/{admission}/vitals-schedule/{schedule}', [VitalsScheduleController::class, 'update'])->name('patients.vitals-schedule.update');
    Route::delete('/{ward}/patients/{admission}/vitals-schedule/{schedule}', [VitalsScheduleController::class, 'destroy'])->name('patients.vitals-schedule.destroy');
});

// Patient Admission Routes
Route::middleware(['auth'])->prefix('admissions')->name('admissions.')->group(function () {
    // Ward Transfer
    Route::post('/{admission}/transfer', [AdmissionController::class, 'transfer'])->name('transfer');
    Route::get('/{admission}/transfers', [AdmissionController::class, 'transferHistory'])->name('transfers.index');

    // Bed Assignment
    Route::get('/{admission}/bed-assignment', [BedAssignmentController::class, 'create'])->name('bed-assignment.create');
    Route::post('/{admission}/bed-assignment', [BedAssignmentController::class, 'store'])->name('bed-assignment.store');
    Route::put('/{admission}/bed-assignment', [BedAssignmentController::class, 'update'])->name('bed-assignment.update');
    Route::delete('/{admission}/bed-assignment', [BedAssignmentController::class, 'destroy'])->name('bed-assignment.destroy');

    Route::post('/{admission}/vitals', [VitalSignController::class, 'storeForAdmission'])->name('vitals.store');
    Route::patch('/{admission}/vitals/{vitalSign}', [VitalSignController::class, 'updateForAdmission'])->name('vitals.update');

    // Nursing Notes
    Route::get('/{admission}/nursing-notes', [NursingNoteController::class, 'index'])->name('nursing-notes.index');
    Route::post('/{admission}/nursing-notes', [NursingNoteController::class, 'store'])->name('nursing-notes.store');
    Route::put('/{admission}/nursing-notes/{nursingNote}', [NursingNoteController::class, 'update'])->name('nursing-notes.update');
    Route::delete('/{admission}/nursing-notes/{nursingNote}', [NursingNoteController::class, 'destroy'])->name('nursing-notes.destroy');

    // Medication Administration (on-demand recording)
    Route::get('/{admission}/medications', [MedicationAdministrationController::class, 'index'])->name('medications.index');
    Route::post('/{admission}/medications', [MedicationAdministrationController::class, 'store'])->name('medications.store');
    Route::post('/{admission}/medications/hold', [MedicationAdministrationController::class, 'hold'])->name('medications.hold');
    Route::post('/{admission}/medications/refuse', [MedicationAdministrationController::class, 'refuse'])->name('medications.refuse');
    Route::post('/{admission}/medications/omit', [MedicationAdministrationController::class, 'omit'])->name('medications.omit');
    Route::delete('/{admission}/medications/{medication}', [MedicationAdministrationController::class, 'destroy'])->name('medications.destroy');
});

// Vitals Alert API Routes (JSON responses for AJAX calls)
Route::middleware(['auth'])->prefix('api')->name('api.')->group(function () {
    // Vitals Alert API endpoints
    Route::prefix('vitals-alerts')->name('vitals-alerts.')->group(function () {
        Route::get('/active', [VitalsAlertController::class, 'active'])->name('active');
        Route::post('/{alert}/acknowledge', [VitalsAlertController::class, 'acknowledge'])->name('acknowledge');
        Route::post('/{alert}/dismiss', [VitalsAlertController::class, 'dismiss'])->name('dismiss');
    });
});

// Continue with Ward Rounds routes
Route::middleware(['auth'])->prefix('admissions')->name('admissions.')->group(function () {

    // Ward Rounds (IPD Patient Reviews)
    Route::get('/{admission}/ward-rounds', [WardRoundController::class, 'index'])->name('ward-rounds.index');
    Route::get('/{admission}/ward-rounds/create', [WardRoundController::class, 'create'])->name('ward-rounds.create');
    Route::get('/{admission}/ward-rounds/{wardRound}', [WardRoundController::class, 'show'])->name('ward-rounds.show');
    Route::get('/{admission}/ward-rounds/{wardRound}/edit', [WardRoundController::class, 'edit'])->name('ward-rounds.edit');

    // Auto-save consultation notes
    Route::patch('/{admission}/ward-rounds/{wardRound}', [WardRoundController::class, 'autoSave'])->name('ward-rounds.auto-save');

    // Immediately save diagnoses, prescriptions, and lab orders
    Route::post('/{admission}/ward-rounds/{wardRound}/diagnoses', [WardRoundController::class, 'addDiagnosis'])->name('ward-rounds.diagnoses.store');
    Route::delete('/{admission}/ward-rounds/{wardRound}/diagnoses/{diagnosis}', [WardRoundController::class, 'removeDiagnosis'])->name('ward-rounds.diagnoses.destroy');

    Route::post('/{admission}/ward-rounds/{wardRound}/prescriptions', [WardRoundController::class, 'addPrescription'])->name('ward-rounds.prescriptions.store');
    Route::delete('/{admission}/ward-rounds/{wardRound}/prescriptions/{prescription}', [WardRoundController::class, 'removePrescription'])->name('ward-rounds.prescriptions.destroy');

    Route::post('/{admission}/ward-rounds/{wardRound}/lab-orders', [WardRoundController::class, 'addLabOrder'])->name('ward-rounds.lab-orders.store');
    Route::delete('/{admission}/ward-rounds/{wardRound}/lab-orders/{labOrder}', [WardRoundController::class, 'removeLabOrder'])->name('ward-rounds.lab-orders.destroy');

    // Procedure management (Theatre tab)
    Route::post('/{admission}/ward-rounds/{wardRound}/procedures', [WardRoundProcedureController::class, 'store'])->name('ward-rounds.procedures.store');
    Route::delete('/{admission}/ward-rounds/{wardRound}/procedures/{procedure}', [WardRoundProcedureController::class, 'destroy'])->name('ward-rounds.procedures.destroy');

    // Complete ward round (mark as completed)
    Route::post('/{admission}/ward-rounds/{wardRound}/complete', [WardRoundController::class, 'complete'])->name('ward-rounds.complete');

    Route::put('/{admission}/ward-rounds/{wardRound}', [WardRoundController::class, 'update'])->name('ward-rounds.update');
    Route::delete('/{admission}/ward-rounds/{wardRound}', [WardRoundController::class, 'destroy'])->name('ward-rounds.destroy');
});
