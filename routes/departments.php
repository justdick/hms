<?php

use App\Http\Controllers\Department\DepartmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
});
