<?php

use App\Events\Indicator\IndicatorSubmissionApproved;
use App\Listeners\Indicator\IndicatorSubmissionApprovedListener;
use App\Models\IndicatorReviewTask;
use App\Models\IndicatorSubmissionReview;
use App\Services\Indicators\IndicatorVerificationService;

it('handles approval events by calling the appropriate verification service method', function () {
    $reviewTask = IndicatorReviewTask::factory()->level1()->completed()->create();
    $reviewTask->load('indicatorTask');
    $task = $reviewTask->indicatorTask;
    $task->load('indicatable');
    // Avoid level 2 logic to keep this focused on the listener delegation
    $task->indicatable->update(['verifier_2_role_id' => null]);

    $review = IndicatorSubmissionReview::factory()
        ->approved()
        ->verifierLevel(1)
        ->create([
            'indicator_review_task_id' => $reviewTask->id,
            'indicator_submission_id' => $reviewTask->indicator_submission_id,
        ]);

    $mockService = Mockery::mock(IndicatorVerificationService::class);
    $mockService->shouldReceive('handleApprovedReview')
        ->once()
        ->with($review);

    $listener = new IndicatorSubmissionApprovedListener($mockService);
    $event = new IndicatorSubmissionApproved($review);

    $listener->handle($event);
});
