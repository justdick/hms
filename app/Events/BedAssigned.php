<?php

namespace App\Events;

use App\Models\PatientCheckin;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BedAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PatientCheckin $checkin,
        public string $bedNumber,
        public string $wardType
    ) {}
}
