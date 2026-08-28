<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NiposTrackingUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $trackingItems;

    /**
     * Create a new event instance.
     *
     * @param array $trackingItems Array of updated tracking items
     */
    public function __construct(array $trackingItems)
    {
        $this->trackingItems = $trackingItems;
    }
}
