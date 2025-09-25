<?php

use App\Http\Controllers\Ward\WardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('wards')->name('wards.')->group(function () {
    Route::get('/', [WardController::class, 'index'])->name('index');
    Route::get('/create', [WardController::class, 'create'])->name('create');
    Route::post('/', [WardController::class, 'store'])->name('store');
    Route::get('/{ward}', [WardController::class, 'show'])->name('show');
    Route::get('/{ward}/edit', [WardController::class, 'edit'])->name('edit');
    Route::put('/{ward}', [WardController::class, 'update'])->name('update');
    Route::delete('/{ward}', [WardController::class, 'destroy'])->name('destroy');
    Route::post('/{ward}/toggle-status', [WardController::class, 'toggleStatus'])->name('toggle-status');
});