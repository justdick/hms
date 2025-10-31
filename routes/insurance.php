<?php

use App\Http\Controllers\Admin\Insurance\InsuranceReportController;
use App\Http\Controllers\Admin\InsuranceClaimController;
use App\Http\Controllers\Admin\InsuranceCoverageRuleController;
use App\Http\Controllers\Admin\InsurancePlanController;
use App\Http\Controllers\Admin\InsuranceProviderController;
use App\Http\Controllers\Admin\InsuranceTariffController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin/insurance')->name('admin.insurance.')->group(function () {
    // Insurance Providers
    Route::resource('providers', InsuranceProviderController::class);

    // Insurance Plans
    Route::resource('plans', InsurancePlanController::class);

    // Coverage Rules
    Route::resource('coverage-rules', InsuranceCoverageRuleController::class);

    // Tariffs
    Route::resource('tariffs', InsuranceTariffController::class);

    // Claims
    Route::get('claims', [InsuranceClaimController::class, 'index'])->name('claims.index');
    Route::get('claims/export', [InsuranceClaimController::class, 'export'])->name('claims.export');
    Route::get('claims/{claim}', [InsuranceClaimController::class, 'show'])->name('claims.show');
    Route::post('claims/{claim}/vet', [InsuranceClaimController::class, 'vet'])->name('claims.vet');

    // Claims Submission & Tracking (Phase 8)
    Route::post('claims/submit', [InsuranceClaimController::class, 'submit'])->name('claims.submit');
    Route::post('claims/{claim}/mark-paid', [InsuranceClaimController::class, 'markAsPaid'])->name('claims.mark-paid');
    Route::post('claims/{claim}/mark-rejected', [InsuranceClaimController::class, 'markAsRejected'])->name('claims.mark-rejected');
    Route::post('claims/{claim}/resubmit', [InsuranceClaimController::class, 'resubmit'])->name('claims.resubmit');

    // Reports & Analytics (Phase 9)
    Route::get('reports', [InsuranceReportController::class, 'index'])->name('reports.index');
    Route::get('reports/claims-summary', [InsuranceReportController::class, 'claimsSummary'])->name('reports.claims-summary');
    Route::get('reports/revenue-analysis', [InsuranceReportController::class, 'revenueAnalysis'])->name('reports.revenue-analysis');
    Route::get('reports/outstanding-claims', [InsuranceReportController::class, 'outstandingClaims'])->name('reports.outstanding-claims');
    Route::get('reports/vetting-performance', [InsuranceReportController::class, 'vettingPerformance'])->name('reports.vetting-performance');
    Route::get('reports/utilization', [InsuranceReportController::class, 'utilizationReport'])->name('reports.utilization');
    Route::get('reports/rejection-analysis', [InsuranceReportController::class, 'rejectionAnalysis'])->name('reports.rejection-analysis');
});
