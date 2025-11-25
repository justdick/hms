<?php

namespace App\Events;

use App\Models\WardRoundProcedure;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WardRoundProcedurePerformed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WardRoundProcedure $procedure
    ) {}
}
