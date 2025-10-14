<?php

use App\Http\Controllers\Billing\BillingConfigurationController;
use App\Http\Controllers\Billing\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function () {
    // Main Billing Dashboard - Use NEW integrated system (PaymentController)
    Route::get('/', [PaymentController::class, 'index'])->name('index')->middleware('can:billing.view-all');

    // Integrated Billing System Routes
    Route::get('/charges', [PaymentController::class, 'index'])->name('charges.index')->middleware('can:billing.view-all');
    Route::get('/patients/search', [PaymentController::class, 'searchPatients'])->name('patients.search')->middleware('can:billing.view-all');
    Route::get('/checkin/{checkin}/billing', [PaymentController::class, 'show'])->name('checkin.billing')->middleware('can:billing.view-dept');
    Route::post('/checkin/{checkin}/payment', [PaymentController::class, 'processPayment'])->name('checkin.payment')->middleware('can:billing.create');
    Route::post('/checkin/{checkin}/emergency-override', [PaymentController::class, 'emergencyOverride'])->name('checkin.emergency-override')->middleware('can:billing.update');
    Route::get('/checkin/{checkin}/billing-status', [PaymentController::class, 'getBillingStatus'])->name('checkin.billing-status')->middleware('can:billing.view-dept');
    Route::post('/charges/{charge}/quick-pay', [PaymentController::class, 'quickPay'])->name('charges.quick-pay')->middleware('can:billing.create');

    // Billing Configuration Management
    Route::prefix('configuration')->name('configuration.')->group(function () {
        Route::get('/', [BillingConfigurationController::class, 'index'])->name('index')->middleware('can:billing.configure');
        Route::post('/system', [BillingConfigurationController::class, 'updateSystemConfig'])->name('system.update')->middleware('can:billing.configure');
        Route::post('/department', [BillingConfigurationController::class, 'createDepartmentBilling'])->name('department.create')->middleware('can:billing.configure');
        Route::put('/department/{departmentBilling}', [BillingConfigurationController::class, 'updateDepartmentBilling'])->name('department.update')->middleware('can:billing.configure');
        Route::post('/service-rule', [BillingConfigurationController::class, 'createServiceRule'])->name('service-rule.create')->middleware('can:billing.configure');
        Route::put('/service-rule/{serviceRule}', [BillingConfigurationController::class, 'updateServiceRule'])->name('service-rule.update')->middleware('can:billing.configure');
    });
});
