<?php

use App\Http\Controllers\Admin\NhisSettingsController;
use App\Http\Controllers\Admin\PricingDashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Settings\ThemeSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Routes for administrative functions like user management, role management,
| and system configuration.
|
*/

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Theme Settings
    Route::get('theme-settings', [ThemeSettingController::class, 'index'])->name('theme-settings.index');
    // User Management
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    // Role Management
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // NHIS Settings
    Route::get('nhis-settings', [NhisSettingsController::class, 'index'])->name('nhis-settings.index');
    Route::put('nhis-settings', [NhisSettingsController::class, 'update'])->name('nhis-settings.update');

    // Pricing Dashboard
    Route::get('pricing-dashboard', [PricingDashboardController::class, 'index'])->name('pricing-dashboard.index');
    Route::put('pricing-dashboard/cash-price', [PricingDashboardController::class, 'updateCashPrice'])->name('pricing-dashboard.update-cash-price');
    Route::put('pricing-dashboard/insurance-copay', [PricingDashboardController::class, 'updateInsuranceCopay'])->name('pricing-dashboard.update-insurance-copay');
    Route::put('pricing-dashboard/insurance-coverage', [PricingDashboardController::class, 'updateInsuranceCoverage'])->name('pricing-dashboard.update-insurance-coverage');
    Route::put('pricing-dashboard/flexible-copay', [PricingDashboardController::class, 'updateFlexibleCopay'])->name('pricing-dashboard.update-flexible-copay');
    Route::post('pricing-dashboard/bulk-update', [PricingDashboardController::class, 'bulkUpdate'])->name('pricing-dashboard.bulk-update');
    Route::get('pricing-dashboard/export', [PricingDashboardController::class, 'export'])->name('pricing-dashboard.export');
    Route::post('pricing-dashboard/import', [PricingDashboardController::class, 'import'])->name('pricing-dashboard.import');
    Route::get('pricing-dashboard/import-template', [PricingDashboardController::class, 'downloadImportTemplate'])->name('pricing-dashboard.import-template');
    Route::get('pricing-dashboard/item-history', [PricingDashboardController::class, 'itemHistory'])->name('pricing-dashboard.item-history');
});
