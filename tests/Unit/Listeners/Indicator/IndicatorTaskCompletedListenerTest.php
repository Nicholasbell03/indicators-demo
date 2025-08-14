<?php

use App\Events\Indicator\IndicatorTaskCompleted;
use App\Listeners\Indicator\IndicatorTaskCompletedListener;
use App\Models\IndicatorSubmission;
use App\Services\Indicators\IndicatorVerificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('handles task completion events by calling the appropriate verification service method', function () {
    $submission = IndicatorSubmission::factory()->create();

    $mockService = Mockery::mock(IndicatorVerificationService::class);
    $mockService->shouldReceive('completeTaskAndSubmission')
        ->once()
        ->with($submission)
        ->andReturn(true);

    $listener = new IndicatorTaskCompletedListener($mockService);
    $event = new IndicatorTaskCompleted($submission);

    $listener->handle($event);
});
