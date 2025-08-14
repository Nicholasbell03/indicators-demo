<?php

namespace App\Models;

use App\Enums\IndicatorTaskStatusEnum;
use App\Observers\IndicatorTaskObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndicatorTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'status' => IndicatorTaskStatusEnum::class,
        'is_achieved' => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();
        self::observe(IndicatorTaskObserver::class);
    }

    public function entrepreneur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entrepreneur_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    public function indicatableMonth(): MorphTo
    {
        return $this->morphTo();
    }

    public function indicatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isSystemTask(): bool
    {
        return $this->responsible_type === 'system';
    }

    public function isUserTask(): bool
    {
        return $this->responsible_type === 'user';
    }

    /**
     * Get the Role of the user who is the responsible for the submission.
     * This will be null for system-generated submissions.
     */
    public function responsibleRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'responsible_role_id');
    }

    /**
     * Get the User who is the responsible for the submission.
     * This will be null for system-generated submissions.
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(IndicatorSubmission::class, 'indicator_task_id')->with('reviews', 'attachments');
    }

    public function latestSubmission(): HasOne
    {
        return $this->hasOne(IndicatorSubmission::class, 'indicator_task_id')->latestOfMany();
    }

    public function indicatorType(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->indicatable?->getMorphClass();
            }
        );
    }

    public function getActionTypeAttribute(): string
    {
        return in_array($this->status, IndicatorTaskStatusEnum::submittableTypes()) ? 'submit' : 'view';
    }

    public function requiresVerification(): bool
    {
        return $this->indicatable?->requiresVerification() ?? true;
    }

    public function isCompleted(): bool
    {
        return $this->status === IndicatorTaskStatusEnum::COMPLETED;
    }

    /**
     * Get the dynamic display status, accounting for overdue tasks.
     * Returns enum objects for programmatic use.
     */
    public function displayStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->due_date < now() && $this->status === IndicatorTaskStatusEnum::PENDING) {
                    return IndicatorTaskStatusEnum::OVERDUE;
                }

                return $this->status;
            }
        );
    }

    /**
     * Get user-friendly display labels for the UI.
     * Returns strings optimized for display purposes.
     */
    public function getDisplayLabelAttribute(): string
    {
        return $this->displayStatus->label();
    }

    public function isOrphaned(): bool
    {
        return $this->indicatableMonth()->doesntExist() || $this->indicatable()->doesntExist();
    }

    public function scopeOrphaned(Builder $query): Builder
    {
        return $query->where(function ($query) {
            $query->whereDoesntHave('indicatableMonth')
                ->orWhereDoesntHave('indicatable');
        });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', IndicatorTaskStatusEnum::pendingTypes());
    }

    public function scopeInVerification(Builder $query): Builder
    {
        return $query->where('status', IndicatorTaskStatusEnum::SUBMITTED);
    }

    public function scopeComplete(Builder $query): Builder
    {
        return $query->where('status', IndicatorTaskStatusEnum::COMPLETED);
    }
}
