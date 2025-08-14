<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorSubmissionReview extends Model
{
    use HasFactory;

    protected $table = 'indicator_submission_reviews';

    protected $guarded = [];

    protected $casts = [
        'approved' => 'boolean',
    ];

    public function reviewTask(): BelongsTo
    {
        return $this->belongsTo(IndicatorReviewTask::class, 'indicator_review_task_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(IndicatorSubmission::class, 'indicator_submission_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
