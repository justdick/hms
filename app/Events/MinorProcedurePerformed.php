<?php

namespace App\Events;

use App\Models\MinorProcedure;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MinorProcedurePerformed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public MinorProcedure $minorProcedure)
    {
        //
    }
}
