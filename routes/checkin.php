<?php

use App\Http\Controllers\Checkin\CheckinController;
use App\Http\Controllers\Patient\PatientController;
use App\Http\Controllers\Vitals\VitalSignController;
use Illuminate\Support\Facades\Route;

// Check-in Main Routes
Route::middleware(['auth'])->prefix('checkin')->name('checkin.')->group(function () {

    // Check-in Dashboard
    Route::get('/', [CheckinController::class, 'index'])->name('index');
    Route::get('/dashboard', [CheckinController::class, 'dashboard'])->name('dashboard');

    // Patient Management
    Route::prefix('patients')->name('patients.')->group(function () {
        Route::get('/search', [PatientController::class, 'search'])->name('search');
        Route::post('/', [PatientController::class, 'store'])->name('store');
        Route::get('/{patient}', [PatientController::class, 'show'])->name('show');
    });

    // Patient Check-ins
    Route::prefix('checkins')->name('checkins.')->group(function () {
        Route::get('/today', [CheckinController::class, 'todayCheckins'])->name('today');
        Route::get('/search', [CheckinController::class, 'search'])->name('search');
        Route::post('/', [CheckinController::class, 'store'])->name('store');
        Route::get('/{checkin}', [CheckinController::class, 'show'])->name('show');
        Route::patch('/{checkin}/status', [CheckinController::class, 'updateStatus'])->name('update-status');
        Route::patch('/{checkin}/department', [CheckinController::class, 'updateDepartment'])->name('update-department');
        Route::patch('/{checkin}/date', [CheckinController::class, 'updateDate'])->name('update-date');
        Route::post('/{checkin}/cancel', [CheckinController::class, 'cancel'])->name('cancel');
        Route::get('/department/{department}/queue', [CheckinController::class, 'departmentQueue'])->name('department-queue');
        Route::get('/patients/{patient}/insurance', [CheckinController::class, 'checkInsurance'])->name('patient-insurance');
    });

    // Vital Signs
    Route::prefix('vitals')->name('vitals.')->group(function () {
        Route::post('/', [VitalSignController::class, 'store'])->name('store');
        Route::get('/{vitalSign}', [VitalSignController::class, 'show'])->name('show');
        Route::patch('/{vitalSign}', [VitalSignController::class, 'update'])->name('update');
        Route::get('/patient/{patientId}/history', [VitalSignController::class, 'patientHistory'])->name('patient-history');
    });
});
