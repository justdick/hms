<?php

namespace App\Providers;

use App\Events\BedAssigned;
use App\Events\PatientAdmitted;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Ward charges require custom method names
        PatientAdmitted::class => [
            'App\Listeners\CreateWardCharge@handleAdmission',
        ],
        BedAssigned::class => [
            'App\Listeners\CreateWardCharge@handleBedAssignment',
        ],
        // Other listeners are auto-discovered via Illuminate\Foundation\Support\Providers\EventServiceProvider
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
