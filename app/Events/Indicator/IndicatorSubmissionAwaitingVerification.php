<?php

namespace App\Events\Indicator;

use App\Models\IndicatorSubmission;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IndicatorSubmissionAwaitingVerification implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public IndicatorSubmission $indicatorSubmission,
        public int $verifier_level,
        public User $verifier
    ) {
        //
    }
}
