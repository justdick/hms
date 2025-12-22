<?php

use App\Http\Controllers\Admin\ClaimExportController;
use App\Http\Controllers\Admin\Insurance\InsuranceReportController;
use App\Http\Controllers\Admin\InsuranceClaimController;
use App\Http\Controllers\Admin\InsuranceCoverageImportController;
use App\Http\Controllers\Admin\InsuranceCoverageRuleController;
use App\Http\Controllers\Admin\InsurancePlanController;
use App\Http\Controllers\Admin\InsuranceProviderController;
use App\Http\Controllers\Admin\NhisMappingController;
use App\Http\Controllers\Admin\NhisTariffController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin/insurance')->name('admin.insurance.')->group(function () {
    // Insurance Providers
    Route::resource('providers', InsuranceProviderController::class);

    // Insurance Plans
    Route::resource('plans', InsurancePlanController::class);

    // Redirect old coverage management pages to Pricing Dashboard
    Route::get('plans/{plan}/coverage', function ($plan) {
        return redirect("/admin/pricing-dashboard?plan={$plan}");
    })->name('plans.coverage');
    Route::get('plans/{plan}/coverage-rules', function ($plan) {
        return redirect("/admin/pricing-dashboard?plan={$plan}");
    })->name('plans.coverage-rules');

    // Keep API endpoints for backward compatibility
    Route::get('plans/{plan}/coverage/{category}/exceptions', [InsurancePlanController::class, 'getCategoryExceptions'])->name('plans.coverage.exceptions');
    Route::get('plans/{plan}/recent-items', [InsurancePlanController::class, 'getRecentItems'])->name('plans.recent-items');
    Route::get('coverage-presets', [InsurancePlanController::class, 'getCoveragePresets'])->name('coverage-presets');

    // Redirect old coverage rule UI pages to Pricing Dashboard
    Route::get('coverage-rules/create', function () {
        $planId = request()->query('plan');

        return redirect('/admin/pricing-dashboard'.($planId ? "?plan={$planId}" : ''));
    })->name('coverage-rules.create');
    Route::get('coverage-rules/{coverageRule}/edit', function () {
        return redirect('/admin/pricing-dashboard');
    })->name('coverage-rules.edit');

    // Coverage Rules (API endpoints only - UI is in Pricing Dashboard)
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

    // NHIS Coverage Export/Import (Requirements 6.1, 6.2, 6.3, 6.4)
    Route::get('plans/{plan}/nhis-coverage/template/{category}', [InsuranceCoverageImportController::class, 'downloadNhisTemplate'])->name('nhis-coverage.template');
    Route::post('plans/{plan}/nhis-coverage/import', [InsuranceCoverageImportController::class, 'importNhisCoverage'])->name('nhis-coverage.import');

    // Tariffs (managed through Coverage Management - no standalone UI)

    // Claims
    Route::get('claims', [InsuranceClaimController::class, 'index'])->name('claims.index');
    Route::get('claims/export', [InsuranceClaimController::class, 'export'])->name('claims.export');
    Route::get('claims/{claim}/vetting-data', [InsuranceClaimController::class, 'getVettingData'])->name('claims.vetting-data');
    Route::post('claims/{claim}/vet', [InsuranceClaimController::class, 'vet'])->name('claims.vet');
    Route::post('claims/{claim}/diagnoses', [InsuranceClaimController::class, 'updateDiagnoses'])->name('claims.diagnoses');
    Route::post('claims/{claim}/items', [InsuranceClaimController::class, 'addItem'])->name('claims.items.store');
    Route::delete('claims/{claim}/items/{item}', [InsuranceClaimController::class, 'removeItem'])->name('claims.items.destroy');

    // Claims Submission & Tracking (Phase 8)
    Route::post('claims/submit', [InsuranceClaimController::class, 'submit'])->name('claims.submit');
    Route::post('claims/{claim}/mark-paid', [InsuranceClaimController::class, 'markAsPaid'])->name('claims.mark-paid');
    Route::post('claims/{claim}/mark-rejected', [InsuranceClaimController::class, 'markAsRejected'])->name('claims.mark-rejected');
    Route::post('claims/{claim}/resubmit', [InsuranceClaimController::class, 'resubmit'])->name('claims.resubmit');
    Route::put('claims/{claim}', [InsuranceClaimController::class, 'update'])->name('claims.update');
    Route::delete('claims/{claim}', [InsuranceClaimController::class, 'destroy'])->name('claims.destroy');
    Route::post('claims/{claim}/prepare-resubmission', [InsuranceClaimController::class, 'prepareForResubmission'])->name('claims.prepare-resubmission');

    // Reports & Analytics (Phase 9)
    Route::get('reports', [InsuranceReportController::class, 'index'])->name('reports.index');
    Route::get('reports/claims-summary', [InsuranceReportController::class, 'claimsSummary'])->name('reports.claims-summary');
    Route::get('reports/revenue-analysis', [InsuranceReportController::class, 'revenueAnalysis'])->name('reports.revenue-analysis');
    Route::get('reports/outstanding-claims', [InsuranceReportController::class, 'outstandingClaims'])->name('reports.outstanding-claims');
    Route::get('reports/vetting-performance', [InsuranceReportController::class, 'vettingPerformance'])->name('reports.vetting-performance');
    Route::get('reports/utilization', [InsuranceReportController::class, 'utilizationReport'])->name('reports.utilization');
    Route::get('reports/rejection-analysis', [InsuranceReportController::class, 'rejectionAnalysis'])->name('reports.rejection-analysis');
    Route::get('reports/tariff-coverage', [InsuranceReportController::class, 'tariffCoverage'])->name('reports.tariff-coverage');

    // Report Exports (Requirement 18.5)
    Route::get('reports/claims-summary/export', [InsuranceReportController::class, 'exportClaimsSummary'])->name('reports.claims-summary.export');
    Route::get('reports/outstanding-claims/export', [InsuranceReportController::class, 'exportOutstandingClaims'])->name('reports.outstanding-claims.export');
    Route::get('reports/rejection-analysis/export', [InsuranceReportController::class, 'exportRejectionAnalysis'])->name('reports.rejection-analysis.export');
    Route::get('reports/tariff-coverage/export', [InsuranceReportController::class, 'exportTariffCoverage'])->name('reports.tariff-coverage.export');

    // Claim Batches
    Route::get('batches', [\App\Http\Controllers\Admin\ClaimBatchController::class, 'index'])->name('batches.index');
    Route::post('batches', [\App\Http\Controllers\Admin\ClaimBatchController::class, 'store'])->name('batches.store');
    Route::get('batches/{batch}', [\App\Http\Controllers\Admin\ClaimBatchController::class, 'show'])->name('batches.show');
    Route::post('batches/{batch}/claims', [\App\Http\Controllers\Admin\ClaimBatchController::class, 'addClaims'])->name('batches.add-claims');
    Route::delete('batches/{batch}/claims/{claim}', [\App\Http\Controllers\Admin\ClaimBatchController::class, 'removeClaim'])->name('batches.remove-claim');
    Route::post('batches/{batch}/finalize', [\App\Http\Controllers\Admin\ClaimBatchController::class, 'finalize'])->name('batches.finalize');
    Route::post('batches/{batch}/unfinalize', [\App\Http\Controllers\Admin\ClaimBatchController::class, 'unfinalize'])->name('batches.unfinalize');
    Route::post('batches/{batch}/submit', [\App\Http\Controllers\Admin\ClaimBatchController::class, 'markSubmitted'])->name('batches.submit');
    Route::post('batches/{batch}/response', [\App\Http\Controllers\Admin\ClaimBatchController::class, 'recordResponse'])->name('batches.response');
    Route::get('batches/{batch}/export', [\App\Http\Controllers\Admin\ClaimBatchController::class, 'exportXml'])->name('batches.export');

    // Claim Export (dedicated controller)
    Route::get('claims/export-batch/{batch}', [ClaimExportController::class, 'exportXml'])->name('claims.export-batch');
});

// NHIS Tariff Management Routes
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    // NHIS Tariffs
    Route::get('nhis-tariffs', [NhisTariffController::class, 'index'])->name('nhis-tariffs.index');
    Route::post('nhis-tariffs', [NhisTariffController::class, 'store'])->name('nhis-tariffs.store');
    Route::put('nhis-tariffs/{nhis_tariff}', [NhisTariffController::class, 'update'])->name('nhis-tariffs.update');
    Route::delete('nhis-tariffs/{nhis_tariff}', [NhisTariffController::class, 'destroy'])->name('nhis-tariffs.destroy');
    Route::post('nhis-tariffs/import', [NhisTariffController::class, 'import'])->name('nhis-tariffs.import');

    // NHIS Item Mappings
    Route::get('nhis-mappings', [NhisMappingController::class, 'index'])->name('nhis-mappings.index');
    Route::post('nhis-mappings', [NhisMappingController::class, 'store'])->name('nhis-mappings.store');
    Route::delete('nhis-mappings/{nhis_mapping}', [NhisMappingController::class, 'destroy'])->name('nhis-mappings.destroy');
    Route::post('nhis-mappings/import', [NhisMappingController::class, 'import'])->name('nhis-mappings.import');
    Route::get('nhis-mappings/unmapped/export', [NhisMappingController::class, 'exportUnmapped'])->name('nhis-mappings.unmapped.export');
    Route::get('nhis-mappings/mapped/export', [NhisMappingController::class, 'exportMapped'])->name('nhis-mappings.mapped.export');
});

// G-DRG Tariff Management Routes
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    // G-DRG Tariffs
    Route::get('gdrg-tariffs', [\App\Http\Controllers\Admin\GdrgTariffController::class, 'index'])->name('gdrg-tariffs.index');
    Route::post('gdrg-tariffs', [\App\Http\Controllers\Admin\GdrgTariffController::class, 'store'])->name('gdrg-tariffs.store');
    Route::put('gdrg-tariffs/{gdrg_tariff}', [\App\Http\Controllers\Admin\GdrgTariffController::class, 'update'])->name('gdrg-tariffs.update');
    Route::delete('gdrg-tariffs/{gdrg_tariff}', [\App\Http\Controllers\Admin\GdrgTariffController::class, 'destroy'])->name('gdrg-tariffs.destroy');
    Route::post('gdrg-tariffs/import', [\App\Http\Controllers\Admin\GdrgTariffController::class, 'import'])->name('gdrg-tariffs.import');
});

// NHIS Tariff Search API (JSON response for dropdowns)
Route::middleware('auth')->prefix('api')->name('api.')->group(function () {
    Route::get('nhis-tariffs/search', [NhisTariffController::class, 'search'])->name('nhis-tariffs.search');
    Route::get('gdrg-tariffs/search', [\App\Http\Controllers\Admin\GdrgTariffController::class, 'search'])->name('gdrg-tariffs.search');
});
