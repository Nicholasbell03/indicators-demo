<?php

namespace App\Listeners\Indicator;

use App\Events\Indicator\IndicatorSubmissionRejected;
use App\Services\Indicators\IndicatorVerificationService;

class IndicatorSubmissionRejectedListener
{
    public function __construct(private IndicatorVerificationService $service)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(IndicatorSubmissionRejected $event): void
    {
        $review = $event->indicatorSubmissionReview;
        $this->service->handleRejectedReview($review);
        // TODO: Send notification to the original submitter with $review->comment
    }
}
