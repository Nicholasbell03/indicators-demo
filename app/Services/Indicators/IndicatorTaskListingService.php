<?php

declare(strict_types=1);

namespace App\Services\Indicators;

use App\Enums\IndicatorTaskStatusEnum;
use App\Models\IndicatorComplianceProgrammeMonth;
use App\Models\IndicatorSuccessProgrammeMonth;
use App\Models\IndicatorTask;
use App\Models\Organisation;
use App\Models\OrganisationProgrammeSeat;
use App\Models\Programme;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IndicatorTaskListingService
{
    private User $entrepreneur;

    private Organisation $organisation;

    private Programme $programme;

    public function __construct(
        private OrganisationProgrammeSeat $seat
    ) {
        $this->entrepreneur = $seat->user;
        $this->organisation = $seat->organisation;
        $this->programme = $seat->programme;
    }

    /**
     * Get indicators for a user grouped by status and formatted for display.
     */
    public function getFormattedIndicatorsForUser(?Carbon $dueBefore = null): array
    {
        $indicators = $this->getIndicators(dueBefore: $dueBefore);

        return $this->formatAndGroupIndicators($indicators);
    }

    /**
     * Get success indicators for a user grouped by status and formatted for display.
     */
    public function getFormattedSuccessIndicatorsForUser(?Carbon $dueBefore = null): array
    {
        $indicators = $this->getIndicators(IndicatorSuccessProgrammeMonth::class, dueBefore: $dueBefore);

        return $this->formatAndGroupIndicators($indicators);
    }

    /**
     * Get compliance indicators for a user grouped by status and formatted for display.
     */
    public function getFormattedComplianceIndicatorsForUser(?Carbon $dueBefore = null): array
    {
        $indicators = $this->getIndicators(IndicatorComplianceProgrammeMonth::class, dueBefore: $dueBefore);

        return $this->formatAndGroupIndicators($indicators);
    }

    /**
     * Check if a user has indicator tasks for the given organisation.
     */
    public function hasIndicatorTasks(): bool
    {
        return $this->getIndicators()->isNotEmpty();
    }

    /**
     * A shared private method to fetch indicators, optionally filtering by type.
     *
     * @param  string|null  $indicatableMonthType  (optional, defaults to null)
     * @param  string|null  $responsibleType  (optional, defaults to "user". Can be "user", "system", "all" or null. "All"/null will return both user and system indicators)
     * @param  User|null  $responsibleUser  (optional, defaults to entrepreneur)
     * @param  Carbon|null  $dueBefore  (optional, defaults to end of current month)
     */
    private function getIndicators(?string $indicatableMonthType = null, ?string $responsibleType = 'user', ?User $responsibleUser = null, ?Carbon $dueBefore = null): Collection
    {
        if (! $responsibleUser) {
            $responsibleUser = $this->entrepreneur;
        }

        if (! $this->programme) {
            $this->programme = $this->entrepreneur->currentProgramme();
        }

        try {
            $query = IndicatorTask::where('entrepreneur_id', $this->entrepreneur->id)
                ->where('organisation_id', $this->organisation->id)
                ->where('programme_id', $this->programme->id);

            if ($dueBefore) {
                $query->where('due_date', '<=', $dueBefore);
            }

            if ($indicatableMonthType) {
                $query->where('indicatable_month_type', $indicatableMonthType);
            }

            if ($responsibleType === 'user') {
                $query->where('responsible_type', $responsibleType)
                    ->where('responsible_user_id', $responsibleUser->id);
            } elseif ($responsibleType === 'system') {
                $query->where('responsible_type', $responsibleType);
            } elseif ($responsibleType === null || $responsibleType === 'all') {
                // do nothing
            } else {
                throw new \InvalidArgumentException('Invalid responsible type');
            }

            return $query->with([
                'programme',
                'entrepreneur',
                'organisation',
                'indicatable', // Load the actual indicator (IndicatorSuccess/IndicatorCompliance)
                'indicatableMonth', // Load the month record
                'responsibleUser',
                'responsibleRole',
                'submissions' => function ($query) {
                    $query->with(['reviews', 'attachments', 'submitter']);
                },
                'latestSubmission' => function ($query) {
                    $query->with(['reviews', 'attachments', 'submitter']);
                },
            ])
                ->orderBy('due_date')
                ->get();

        } catch (\Exception $e) {
            Log::error('Error getting indicators for user', [
                'entrepreneur_id' => $this->entrepreneur->id,
                'organisation_id' => $this->organisation->id,
                'indicatable_month_type' => $indicatableMonthType,
                'responsible_type' => $responsibleType,
                'responsible_user_id' => $responsibleUser->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * A shared private method to group and format a collection of indicators.
     */
    private function formatAndGroupIndicators(Collection $indicators): array
    {
        $groupedIndicators = $this->groupByStatus($indicators);

        $formattedIndicators = [
            'open' => [],
            'verifying' => [],
            'complete' => [],
        ];

        foreach ($groupedIndicators as $status => $tasks) {
            $formattedIndicators[$status] = collect($tasks)->map(function (IndicatorTask $task) {
                return [
                    'id' => $task->id,
                    'name' => $task->indicatable?->title ?? 'Unknown Indicator',
                    'status' => $task->displayStatus,
                    'due_date' => $task->due_date->toISOString(),
                    'action_type' => $task->action_type,
                ];
            })->toArray();
        }

        return $formattedIndicators;
    }

    /**
     * Group indicator tasks by their status.
     *
     * @param  Collection  $indicators  of IndicatorTask
     *
     * @throws \InvalidArgumentException
     */
    public function groupByStatus(Collection $indicators): array
    {
        $groups = [
            'open' => [],
            'verifying' => [],
            'complete' => [],
        ];

        // Group indicators by their status
        foreach ($indicators as $indicator) {
            // Validate each indicator is an IndicatorTask instance
            if (! $indicator instanceof IndicatorTask) {
                throw new \InvalidArgumentException('Collection must contain only IndicatorTask instances');
            }

            switch ($indicator->displayStatus) {
                case IndicatorTaskStatusEnum::OVERDUE:
                case IndicatorTaskStatusEnum::PENDING:
                case IndicatorTaskStatusEnum::NEEDS_REVISION:
                    $groups['open'][] = $indicator;
                    break;
                case IndicatorTaskStatusEnum::SUBMITTED:
                    $groups['verifying'][] = $indicator;
                    break;
                case IndicatorTaskStatusEnum::COMPLETED:
                    $groups['complete'][] = $indicator;
                    break;
                default:
                    Log::error('Invalid status: '.$indicator->displayStatus);
                    throw new \InvalidArgumentException('Invalid status: '.$indicator->displayStatus);
            }
        }

        return $groups;
    }
}
