<?php

namespace App\Providers;

use App\Models\Charge;
use App\Models\Drug;
use App\Models\Prescription;
use App\Observers\ChargeObserver;
use App\Observers\DrugObserver;
use App\Observers\PrescriptionObserver;
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

        Charge::observe(ChargeObserver::class);
        Drug::observe(DrugObserver::class);
        Prescription::observe(PrescriptionObserver::class);
    }
}
