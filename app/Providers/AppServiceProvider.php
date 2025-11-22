<?php

namespace App\Providers;

use App\Models\Charge;
use App\Models\Drug;
use App\Models\LabService;
use App\Models\MinorProcedure;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\VitalSign;
use App\Observers\ChargeObserver;
use App\Observers\DrugObserver;
use App\Observers\LabServiceObserver;
use App\Observers\PrescriptionObserver;
use App\Observers\VitalSignObserver;
use App\Policies\BillingPolicy;
use App\Policies\MinorProcedurePolicy;
use App\Policies\PatientCheckinPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(Charge::class, BillingPolicy::class);
        Gate::policy(MinorProcedure::class, MinorProcedurePolicy::class);
        Gate::policy(PatientCheckin::class, PatientCheckinPolicy::class);

        // Register observers
        Charge::observe(ChargeObserver::class);
        Drug::observe(DrugObserver::class);
        LabService::observe(LabServiceObserver::class);
        Prescription::observe(PrescriptionObserver::class);
        VitalSign::observe(VitalSignObserver::class);
    }
}
