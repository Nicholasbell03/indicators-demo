<?php

namespace App\Events\Indicator;

use App\Models\IndicatorSubmissionReview;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IndicatorSubmissionRejected implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public IndicatorSubmissionReview $indicatorSubmissionReview)
    {
        //
    }
}
