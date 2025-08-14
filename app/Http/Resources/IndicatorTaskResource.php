<?php

namespace App\Http\Resources;

use App\Models\IndicatorSuccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndicatorTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing([
            'indicatable',
            'latestSubmission',
            'latestSubmission.latestReview',
            'latestSubmission.attachments',
        ]);

        $latestSubmission = $this->latestSubmission;
        $latestReview = $latestSubmission?->latestReview ?? null;

        $indicatorType = $this->indicatorType == IndicatorSuccess::class ? 'Indicator Success' : 'Indicator Compliance';

        $indicator = $this->indicatable;

        return [
            'id' => $this->id,
            'type' => $indicatorType,
            'name' => $indicator->title,
            'action_type' => $this->action_type,
            'additional_instructions' => $indicator?->additional_instruction,
            'target_value' => $indicator?->target_value,
            'acceptance_value' => $indicator?->acceptance_value,
            'response_format' => $indicator?->response_format,
            'currency' => $indicator?->currency,
            'supporting_documentation' => $indicator?->supporting_documentation,
            'task_status' => $this->displayStatus,
            'is_achieved' => $this->is_achieved,
            'latest_submission' => $latestSubmission ? new IndicatorSubmissionResource($latestSubmission) : null,
            'latest_review' => $latestReview ? new IndicatorSubmissionReviewResource($latestReview) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
