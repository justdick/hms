<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/checkin.php';
require __DIR__.'/patients.php';
require __DIR__.'/consultation.php';
require __DIR__.'/departments.php';
require __DIR__.'/wards.php';
require __DIR__.'/lab.php';
require __DIR__.'/radiology.php';
require __DIR__.'/pharmacy.php';
require __DIR__.'/billing.php';
require __DIR__.'/insurance.php';
require __DIR__.'/minor-procedures.php';
require __DIR__.'/backups.php';
require __DIR__.'/admin.php';
