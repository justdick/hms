<?php

use App\Http\Controllers\OpdController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientCheckinController;
use App\Http\Controllers\VitalSignController;
use Illuminate\Support\Facades\Route;

// Check-in Main Routes
Route::middleware(['auth', 'verified'])->prefix('checkin')->name('checkin.')->group(function () {

    // Check-in Dashboard
    Route::get('/', [OpdController::class, 'index'])->name('index');
    Route::get('/dashboard', [OpdController::class, 'dashboard'])->name('dashboard');

    // Patient Management
    Route::prefix('patients')->name('patients.')->group(function () {
        Route::get('/search', [PatientController::class, 'search'])->name('search');
        Route::post('/', [PatientController::class, 'store'])->name('store');
        Route::get('/{patient}', [PatientController::class, 'show'])->name('show');
    });

    // Patient Check-ins
    Route::prefix('checkins')->name('checkins.')->group(function () {
        Route::get('/today', [PatientCheckinController::class, 'todayCheckins'])->name('today');
        Route::post('/', [PatientCheckinController::class, 'store'])->name('store');
        Route::get('/{checkin}', [PatientCheckinController::class, 'show'])->name('show');
        Route::patch('/{checkin}/status', [PatientCheckinController::class, 'updateStatus'])->name('update-status');
        Route::get('/department/{department}/queue', [PatientCheckinController::class, 'departmentQueue'])->name('department-queue');
    });

    // Vital Signs
    Route::prefix('vitals')->name('vitals.')->group(function () {
        Route::post('/', [VitalSignController::class, 'store'])->name('store');
        Route::get('/{vitalSign}', [VitalSignController::class, 'show'])->name('show');
        Route::patch('/{vitalSign}', [VitalSignController::class, 'update'])->name('update');
        Route::get('/patient/{patientId}/history', [VitalSignController::class, 'patientHistory'])->name('patient-history');
    });
});