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
        // Minor procedure charge
        'App\Events\MinorProcedurePerformed' => [
            'App\Listeners\CreateMinorProcedureCharge',
        ],
        // Consultation procedure charge
        'App\Events\ConsultationProcedurePerformed' => [
            'App\Listeners\CreateConsultationProcedureCharge',
        ],
        // Ward round procedure charge
        'App\Events\WardRoundProcedurePerformed' => [
            'App\Listeners\CreateWardRoundProcedureCharge',
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
