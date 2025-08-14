<?php

namespace App\Listeners\Indicator;

use App\Events\Indicator\IndicatorSubmissionAwaitingVerification;

class IndicatorSubmissionAwaitingVerificationListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(IndicatorSubmissionAwaitingVerification $event): void
    {
        // TODO: Send a notification to the verifier ($event->verifier) to inform them
        // that a submission ($event->indicatorSubmission) is awaiting their review and they are review level ($event->verifier_level)
    }
}
