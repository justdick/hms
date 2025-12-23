<?php

namespace App\Providers;

use App\Models\Backup;
use App\Models\Charge;
use App\Models\ClaimBatch;
use App\Models\Drug;
use App\Models\GdrgTariff;
use App\Models\LabService;
use App\Models\MinorProcedure;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\VitalSign;
use App\Observers\ChargeObserver;
use App\Observers\DrugObserver;
use App\Observers\LabOrderObserver;
use App\Observers\LabServiceObserver;
use App\Observers\PrescriptionObserver;
use App\Observers\VitalSignObserver;
use App\Policies\BackupPolicy;
use App\Policies\BillingPolicy;
use App\Policies\ClaimBatchPolicy;
use App\Policies\GdrgTariffPolicy;
use App\Policies\MinorProcedurePolicy;
use App\Policies\NhisMappingPolicy;
use App\Policies\NhisTariffPolicy;
use App\Policies\PatientCheckinPolicy;
use App\Policies\PatientPolicy;
use App\Policies\PricingDashboardPolicy;
use App\Policies\RadiologyPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Register policies
        Gate::policy(Backup::class, BackupPolicy::class);
        Gate::policy(Charge::class, BillingPolicy::class);
        Gate::policy(ClaimBatch::class, ClaimBatchPolicy::class);
        Gate::policy(GdrgTariff::class, GdrgTariffPolicy::class);
        Gate::policy(MinorProcedure::class, MinorProcedurePolicy::class);
        Gate::policy(NhisItemMapping::class, NhisMappingPolicy::class);
        Gate::policy(NhisTariff::class, NhisTariffPolicy::class);
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(PatientCheckin::class, PatientCheckinPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        // Register string-based policies for non-model resources
        $pricingPolicy = new PricingDashboardPolicy;
        Gate::define('viewAny-pricing-dashboard', fn (User $user) => $pricingPolicy->viewAny($user));
        Gate::define('updateCashPrice-pricing-dashboard', fn (User $user) => $pricingPolicy->updateCashPrice($user));
        Gate::define('updateInsuranceCopay-pricing-dashboard', fn (User $user) => $pricingPolicy->updateInsuranceCopay($user));
        Gate::define('updateInsuranceCoverage-pricing-dashboard', fn (User $user) => $pricingPolicy->updateInsuranceCoverage($user));
        Gate::define('bulkUpdate-pricing-dashboard', fn (User $user) => $pricingPolicy->bulkUpdate($user));
        Gate::define('export-pricing-dashboard', fn (User $user) => $pricingPolicy->export($user));
        Gate::define('import-pricing-dashboard', fn (User $user) => $pricingPolicy->import($user));

        // Register radiology policy gates
        $radiologyPolicy = new RadiologyPolicy;
        Gate::define('viewWorklist-radiology', fn (User $user) => $radiologyPolicy->viewWorklist($user));
        Gate::define('uploadImages-radiology', fn (User $user, ?\App\Models\LabOrder $labOrder = null) => $radiologyPolicy->uploadImages($user, $labOrder));
        Gate::define('enterReport-radiology', fn (User $user, ?\App\Models\LabOrder $labOrder = null) => $radiologyPolicy->enterReport($user, $labOrder));

        // Register observers
        Charge::observe(ChargeObserver::class);
        Drug::observe(DrugObserver::class);
        \App\Models\LabOrder::observe(LabOrderObserver::class);
        LabService::observe(LabServiceObserver::class);
        Prescription::observe(PrescriptionObserver::class);
        VitalSign::observe(VitalSignObserver::class);
    }
}
