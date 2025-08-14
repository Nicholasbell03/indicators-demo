<?php

namespace App\Listeners\Indicator;

use App\Events\Indicator\IndicatorTaskReadyForSubmission;

class IndicatorTaskReadyForSubmissionListener
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
    public function handle(IndicatorTaskReadyForSubmission $event): void
    {
        // TODO: Get the entrepreneur from the task ($event->indicatorTask->entrepreneur)
        // and send them a notification that the task is ready for submission.
    }
}
