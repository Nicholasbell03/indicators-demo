<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndicatorSubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing([
            'latestReview',
            'attachments',
        ]);

        return [
            'id' => $this->id,
            'task_id' => $this->indicator_task_id,
            'value' => $this->value,
            'comment' => $this->comment,
            'is_achieved' => $this->is_achieved,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
            'attachments' => IndicatorSubmissionAttachmentResource::collection($this->attachments),
            'latest_review' => $this->latestReview ? new IndicatorSubmissionReviewResource($this->latestReview) : null,
        ];
    }
}
