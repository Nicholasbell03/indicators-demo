<?php

use App\Events\Indicator\IndicatorSubmissionRejected;
use App\Listeners\Indicator\IndicatorSubmissionRejectedListener;
use App\Models\IndicatorSubmissionReview;
use App\Services\Indicators\IndicatorVerificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('handles rejection events by calling the appropriate verification service method', function () {
    $review = IndicatorSubmissionReview::factory()->rejected()->verifierLevel(2)->create([
        'comment' => 'Needs improvement',
    ]);

    $mockService = Mockery::mock(IndicatorVerificationService::class);
    $mockService->shouldReceive('handleRejectedReview')
        ->once()
        ->with($review);

    $listener = new IndicatorSubmissionRejectedListener($mockService);
    $event = new IndicatorSubmissionRejected($review);

    $listener->handle($event);
});
