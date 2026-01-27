<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * All events are auto-discovered by Laravel's base EventServiceProvider.
     * We disable discovery here to prevent duplicate registrations.
     */
    protected $listen = [
        // Empty - all events are auto-discovered by Laravel
    ];

    public function boot(): void
    {
        //
    }

    /**
     * Disable event discovery in this provider to prevent duplicates.
     * Laravel's base EventServiceProvider handles discovery.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
