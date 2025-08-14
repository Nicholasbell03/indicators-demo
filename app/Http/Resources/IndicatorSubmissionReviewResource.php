<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndicatorSubmissionReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reviewer = $this->reviewer;

        return [
            'id' => $this->id,
            'submission_id' => $this->indicator_submission_id,
            'reviewer_name' => $reviewer?->name ?? null,
            'comment' => $this->comment,
            'approved' => $this->approved,
            'verifier_level' => $this->verifier_level,
            'reviewed_at' => $this->reviewed_at,
        ];
    }
}
