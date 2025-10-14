<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/checkin.php';
require __DIR__.'/consultation.php';
require __DIR__.'/wards.php';
require __DIR__.'/lab.php';
require __DIR__.'/pharmacy.php';
require __DIR__.'/billing.php';
