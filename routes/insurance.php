<?php

use App\Http\Controllers\Admin\Insurance\InsuranceReportController;
use App\Http\Controllers\Admin\InsuranceClaimController;
use App\Http\Controllers\Admin\InsuranceCoverageImportController;
use App\Http\Controllers\Admin\InsuranceCoverageRuleController;
use App\Http\Controllers\Admin\InsurancePlanController;
use App\Http\Controllers\Admin\InsuranceProviderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin/insurance')->name('admin.insurance.')->group(function () {
    // Insurance Providers
    Route::resource('providers', InsuranceProviderController::class);

    // Insurance Plans
    Route::resource('plans', InsurancePlanController::class);
    Route::get('plans/{plan}/coverage', [InsurancePlanController::class, 'showCoverage'])->name('plans.coverage');
    Route::get('plans/{plan}/coverage/{category}/exceptions', [InsurancePlanController::class, 'getCategoryExceptions'])->name('plans.coverage.exceptions');
    Route::get('plans/{plan}/coverage-rules', [InsurancePlanController::class, 'manageCoverageRules'])->name('plans.coverage-rules');
    Route::get('plans/{plan}/recent-items', [InsurancePlanController::class, 'getRecentItems'])->name('plans.recent-items');
    Route::get('coverage-presets', [InsurancePlanController::class, 'getCoveragePresets'])->name('coverage-presets');

    // Coverage Rules (API endpoints only - UI is in Coverage Management)
    Route::post('coverage-rules', [InsuranceCoverageRuleController::class, 'store'])->name('coverage-rules.store');
    Route::patch('coverage-rules/{coverageRule}', [InsuranceCoverageRuleController::class, 'update'])->name('coverage-rules.update');
    Route::delete('coverage-rules/{coverageRule}', [InsuranceCoverageRuleController::class, 'destroy'])->name('coverage-rules.destroy');
    Route::get('coverage-rules/search-items/{category}', [InsuranceCoverageRuleController::class, 'searchItems'])->name('coverage-rules.search-items');
    Route::patch('coverage-rules/{coverageRule}/quick-update', [InsuranceCoverageRuleController::class, 'quickUpdate'])->name('coverage-rules.quick-update');
    Route::get('coverage-rules/{coverageRule}/history', [InsuranceCoverageRuleController::class, 'history'])->name('coverage-rules.history');
    Route::get('plans/{plan}/coverage-rules/export', [InsuranceCoverageRuleController::class, 'exportWithHistory'])->name('plans.coverage-rules.export');
    Route::get('plans/{plan}/coverage-rules/template/{category}', [InsuranceCoverageImportController::class, 'downloadTemplate'])->name('insurance.coverage.template');

    // Bulk Import
    Route::get('plans/{plan}/coverage/import-template/{category}', [InsuranceCoverageImportController::class, 'downloadTemplate'])->name('coverage.import-template');
    Route::post('plans/{plan}/coverage/import-preview', [InsuranceCoverageImportController::class, 'preview'])->name('coverage.import-preview');
    Route::post('plans/{plan}/coverage/import', [InsuranceCoverageImportController::class, 'import'])->name('coverage.import');

    // Tariffs (managed through Coverage Management - no standalone UI)

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
