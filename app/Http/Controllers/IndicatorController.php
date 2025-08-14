<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndicatorSubmissionRequest;
use App\Http\Resources\IndicatorTaskResource;
use App\Models\IndicatorTask;
use App\Services\Indicators\IndicatorSubmissionService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class IndicatorController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(
        IndicatorSubmissionRequest $request,
        IndicatorSubmissionService $indicatorSubmissionService
    ) {
        try {
            $indicatorSubmissionService->createSubmission(
                $request->validated(),
                $request->user()
            );

            // Return Inertia response with success message and close modal
            return Redirect::back()->with('message', 'Indicator submission created successfully!')->with('type', 'success');
        } catch (Exception $e) {
            Log::error('Indicator submission failed: '.$e->getMessage());

            // Return back with validation errors for Inertia to handle
            return back()->withErrors(['submission' => 'There was a problem submitting your indicator. Please try again.']);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(IndicatorTask $indicatorTask)
    {
        $this->authorize('view', $indicatorTask);

        $indicatorTask->load(['latestSubmission', 'latestSubmission.latestReview', 'latestSubmission.attachments', 'indicatable']);

        return Inertia::modal('Indicators/IndicatorSubmissionModal', [
            'indicatorTask' => new IndicatorTaskResource($indicatorTask),
        ])->baseRoute('organisation.indicators', [
            'organisation' => $indicatorTask->organisation,
        ]);
    }
}
