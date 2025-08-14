<?php

namespace App\Listeners\Indicator;

use App\Events\Indicator\IndicatorSubmissionApproved;
use App\Services\Indicators\IndicatorVerificationService;

class IndicatorSubmissionApprovedListener
{
    public function __construct(private IndicatorVerificationService $service)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(IndicatorSubmissionApproved $event): void
    {
        $review = $event->indicatorSubmissionReview;
        $this->service->handleApprovedReview($review);
        // Note: No notification is sent here, as it may only be the first level of verification (so approval is not final)
        // We send a notifiction if the task is completed (meaning all verifications are complete)
    }
}
