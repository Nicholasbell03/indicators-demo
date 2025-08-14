<?php

use App\Enums\IndicatorTaskStatusEnum;
use App\Events\Indicator\IndicatorSubmissionSubmitted;
use App\Listeners\Indicator\IndicatorSubmissionSubmittedListener;
use App\Models\IndicatorSubmission;
use App\Services\Indicators\IndicatorVerificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('handles submission events by calling the appropriate verification service method and updating the task status', function () {
    $submission = IndicatorSubmission::factory()->create();
    $task = $submission->task;
    $originalStatus = $task->status;

    // Ensure no verification is required to avoid invoking complex service logic
    $task->load('indicatable');
    $task->indicatable->update([
        'verifier_1_role_id' => null,
        'verifier_2_role_id' => null,
    ]);

    $mockService = Mockery::mock(IndicatorVerificationService::class);
    $mockService->shouldReceive('processSubmissionForVerification')
        ->once()
        ->with($submission);

    $listener = new IndicatorSubmissionSubmittedListener($mockService);
    $event = new IndicatorSubmissionSubmitted($submission);

    $listener->handle($event);

    expect($task->status)->toBe(IndicatorTaskStatusEnum::SUBMITTED);
});
