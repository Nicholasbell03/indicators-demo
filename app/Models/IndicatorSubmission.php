<?php

namespace App\Models;

use App\Enums\IndicatorSubmissionStatusEnum;
use App\Observers\IndicatorSubmissionObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IndicatorSubmission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'is_achieved' => 'boolean',
        'status' => IndicatorSubmissionStatusEnum::class,
    ];

    protected static function boot(): void
    {
        parent::boot();
        self::observe(IndicatorSubmissionObserver::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(IndicatorTask::class, 'indicator_task_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IndicatorSubmissionAttachment::class, 'indicator_submission_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(IndicatorSubmissionReview::class, 'indicator_submission_id');
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(IndicatorSubmissionReview::class, 'indicator_submission_id')->latestOfMany();
    }
}
