<?php

namespace App\Events;

use App\Models\PatientCheckin;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientAdmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PatientCheckin $checkin,
        public string $wardType,
        public ?string $bedNumber = null
    ) {}
}
