<?php

use App\Http\Controllers\Billing\AccountsController;
use App\Http\Controllers\Billing\BillAdjustmentController;
use App\Http\Controllers\Billing\BillingConfigurationController;
use App\Http\Controllers\Billing\HistoryController;
use App\Http\Controllers\Billing\PaymentController;
use App\Http\Controllers\Billing\ReconciliationController;
use App\Http\Controllers\Billing\ReportController;
use App\Http\Controllers\Billing\ServiceOverrideController;
use App\Http\Controllers\Billing\StatementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('billing')->name('billing.')->group(function () {
    // Main Billing Dashboard - Use NEW integrated system (PaymentController)
    Route::get('/', [PaymentController::class, 'index'])->name('index')->middleware('can:billing.view-all');

    // Integrated Billing System Routes
    Route::get('/charges', [PaymentController::class, 'index'])->name('charges.index')->middleware('can:billing.view-all');
    Route::get('/patients/search', [PaymentController::class, 'searchPatients'])->name('patients.search')->middleware('can:billing.view-all');
    Route::get('/checkin/{checkin}/billing', [PaymentController::class, 'show'])->name('checkin.billing')->middleware('can:billing.view-dept');
    Route::post('/checkin/{checkin}/payment', [PaymentController::class, 'processPayment'])->name('checkin.payment')->middleware('can:billing.create');
    Route::post('/checkin/{checkin}/emergency-override', [PaymentController::class, 'emergencyOverride'])->name('checkin.emergency-override')->middleware('can:billing.update');
    Route::get('/checkin/{checkin}/billing-status', [PaymentController::class, 'getBillingStatus'])->name('checkin.billing-status')->middleware('can:billing.view-dept');

    // My Collections Route (for cashiers)
    Route::get('/my-collections', [PaymentController::class, 'myCollections'])->name('my-collections')->middleware('can:billing.collect');

    // Quick Pay Routes
    Route::post('/charges/{charge}/quick-pay', [PaymentController::class, 'quickPay'])->name('charges.quick-pay')->middleware('can:billing.create');
    Route::post('/charges/quick-pay-all', [PaymentController::class, 'quickPayAll'])->name('charges.quick-pay-all')->middleware('can:billing.create');

    // Receipt Routes
    Route::post('/receipt', [PaymentController::class, 'receipt'])->name('receipt')->middleware('can:billing.collect');
    Route::post('/receipt/log-print', [PaymentController::class, 'logReceiptPrint'])->name('receipt.log-print')->middleware('can:billing.collect');

    // Billing Override Routes (for marking charges as owing)
    Route::post('/checkin/{checkin}/billing-override', [PaymentController::class, 'createBillingOverride'])->name('billing-override.create')->middleware('can:billing.override');
    Route::get('/checkin/{checkin}/owing-charges', [PaymentController::class, 'getOwingCharges'])->name('owing-charges')->middleware('can:billing.view-dept');

    // Patient Credit Tag Routes
    Route::post('/patients/{patient}/credit-tag', [PaymentController::class, 'addCreditTag'])->name('patients.credit-tag.add')->middleware('can:billing.manage-credit');
    Route::delete('/patients/{patient}/credit-tag', [PaymentController::class, 'removeCreditTag'])->name('patients.credit-tag.remove')->middleware('can:billing.manage-credit');
    Route::get('/credit-patients', [PaymentController::class, 'getCreditPatients'])->name('credit-patients')->middleware('can:billing.manage-credit');

    // Bill Adjustment Routes
    Route::post('/charges/{charge}/waive', [BillAdjustmentController::class, 'waive'])->name('charges.waive');
    Route::post('/charges/{charge}/adjust', [BillAdjustmentController::class, 'adjust'])->name('charges.adjust');

    // Service Override Routes
    Route::post('/checkin/{checkin}/override', [ServiceOverrideController::class, 'activate'])->name('override.activate');
    Route::post('/overrides/{override}/deactivate', [ServiceOverrideController::class, 'deactivate'])->name('override.deactivate');
    Route::get('/checkin/{checkin}/overrides', [ServiceOverrideController::class, 'index'])->name('override.index');

    // Billing Configuration Management
    Route::prefix('configuration')->name('configuration.')->group(function () {
        Route::get('/', [BillingConfigurationController::class, 'index'])->name('index')->middleware('can:billing.configure');
        Route::post('/system', [BillingConfigurationController::class, 'updateSystemConfig'])->name('system.update')->middleware('can:billing.configure');
        Route::post('/department', [BillingConfigurationController::class, 'createDepartmentBilling'])->name('department.create')->middleware('can:billing.configure');
        Route::post('/department/bulk', [BillingConfigurationController::class, 'bulkCreateDepartmentBilling'])->name('department.bulk')->middleware('can:billing.configure');
        Route::put('/department/{departmentBilling}', [BillingConfigurationController::class, 'updateDepartmentBilling'])->name('department.update')->middleware('can:billing.configure');
        Route::post('/service-rule', [BillingConfigurationController::class, 'createServiceRule'])->name('service-rule.create')->middleware('can:billing.configure');
        Route::put('/service-rule/{serviceRule}', [BillingConfigurationController::class, 'updateServiceRule'])->name('service-rule.update')->middleware('can:billing.configure');
    });

    // Finance Officer Accounts Section
    Route::prefix('accounts')->name('accounts.')->middleware('can:billing.view-all')->group(function () {
        Route::get('/', [AccountsController::class, 'index'])->name('index');

        // Credit Patients Route
        Route::get('/credit-patients', [AccountsController::class, 'creditPatients'])->name('credit-patients')->middleware('can:billing.manage-credit');

        // Reconciliation Routes
        Route::prefix('reconciliation')->name('reconciliation.')->middleware('can:billing.reconcile')->group(function () {
            Route::get('/', [ReconciliationController::class, 'index'])->name('index');
            Route::post('/', [ReconciliationController::class, 'store'])->name('store');
            Route::get('/system-total', [ReconciliationController::class, 'getSystemTotal'])->name('system-total');
        });

        // Payment History Routes
        Route::prefix('history')->name('history.')->group(function () {
            Route::get('/', [HistoryController::class, 'index'])->name('index');
            Route::get('/{charge}', [HistoryController::class, 'show'])->name('show');
        });

        // Void and Refund Routes
        Route::post('/charges/{charge}/void', [PaymentController::class, 'voidPayment'])->name('charges.void')->middleware('can:billing.void');
        Route::post('/charges/{charge}/refund', [PaymentController::class, 'refundPayment'])->name('charges.refund')->middleware('can:billing.refund');

        // Statement Routes
        Route::prefix('statements')->name('statements.')->middleware('can:billing.statements')->group(function () {
            Route::post('/{patient}/generate', [StatementController::class, 'generate'])->name('generate');
            Route::get('/{patient}/preview', [StatementController::class, 'preview'])->name('preview');
        });

        // Report Routes
        Route::prefix('reports')->name('reports.')->middleware('can:billing.reports')->group(function () {
            Route::get('/outstanding', [ReportController::class, 'outstanding'])->name('outstanding');
            Route::get('/outstanding/export/excel', [ReportController::class, 'exportOutstandingExcel'])->name('outstanding.export.excel');
            Route::get('/outstanding/export/pdf', [ReportController::class, 'exportOutstandingPdf'])->name('outstanding.export.pdf');

            // Revenue Reports
            Route::get('/revenue', [ReportController::class, 'revenue'])->name('revenue');
            Route::get('/revenue/export/excel', [ReportController::class, 'exportRevenueExcel'])->name('revenue.export.excel');
            Route::get('/revenue/export/pdf', [ReportController::class, 'exportRevenuePdf'])->name('revenue.export.pdf');
        });
    });
});
