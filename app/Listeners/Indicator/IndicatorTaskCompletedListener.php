<?php

namespace App\Listeners\Indicator;

use App\Events\Indicator\IndicatorTaskCompleted;
use App\Services\Indicators\IndicatorVerificationService;

class IndicatorTaskCompletedListener
{
    /**
     * Create the event listener.
     */
    public function __construct(private IndicatorVerificationService $service)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(IndicatorTaskCompleted $event): void
    {
        $submission = $event->indicatorSubmission;
        $didComplete = $this->service->completeTaskAndSubmission($submission);

        if ($didComplete) {
            // Dispatch notification to entrepreneur etc.
        }

        // TODO: This is a critical point in the workflow.
        // Other potential actions to consider here:
        // - Send a final "completed" notification to the entrepreneur. (probably need an IndicatorNotificationService)
        // - Create a summary/activity log entry for the completed task? (not sure if this is needed)
        // - Trigger any subsequent reporting or data aggregation jobs (not sure if this is needed either)
    }
}
