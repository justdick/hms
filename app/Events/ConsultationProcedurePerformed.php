<?php

namespace App\Events;

use App\Models\ConsultationProcedure;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsultationProcedurePerformed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ConsultationProcedure $procedure
    ) {}
}
