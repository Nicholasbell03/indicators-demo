<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IndicatorReviewTask extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function indicatorSubmission(): BelongsTo
    {
        return $this->belongsTo(IndicatorSubmission::class);
    }

    public function indicatorTask(): BelongsTo
    {
        return $this->belongsTo(IndicatorTask::class);
    }

    public function verifierUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_user_id');
    }

    public function verifierRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'verifier_role_id');
    }

    public function indicatorSubmissionReview(): HasOne
    {
        return $this->hasOne(IndicatorSubmissionReview::class, 'indicator_review_task_id');
    }

    public function scopeComplete(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now());
    }

    public function scopeOrphaned(Builder $query): Builder
    {
        return $query->whereNull('verifier_user_id');
    }

    public function scopeNotOrphaned(Builder $query): Builder
    {
        return $query->whereNotNull('verifier_user_id');
    }
}
