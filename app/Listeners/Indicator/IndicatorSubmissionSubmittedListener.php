<?php

namespace App\Listeners\Indicator;

use App\Enums\IndicatorTaskStatusEnum;
use App\Events\Indicator\IndicatorSubmissionSubmitted;
use App\Services\Indicators\IndicatorVerificationService;

class IndicatorSubmissionSubmittedListener
{
    public function __construct(private IndicatorVerificationService $service)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(IndicatorSubmissionSubmitted $event): void
    {
        $task = $event->indicatorSubmission->task;
        $task->status = IndicatorTaskStatusEnum::SUBMITTED;
        $task->save();

        $this->service->processSubmissionForVerification($event->indicatorSubmission);
    }
}
