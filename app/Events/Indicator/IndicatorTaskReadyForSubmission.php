<?php

namespace App\Events\Indicator;

use App\Models\IndicatorTask;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IndicatorTaskReadyForSubmission
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public IndicatorTask $indicatorTask)
    {
        //
    }
}
