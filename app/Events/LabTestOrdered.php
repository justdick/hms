<?php

namespace App\Events;

use App\Models\LabOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LabTestOrdered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LabOrder $labOrder
    ) {}
}
