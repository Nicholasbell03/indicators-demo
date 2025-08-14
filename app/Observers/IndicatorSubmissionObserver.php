<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\IndicatorSubmissionStatusEnum;
use App\Enums\IndicatorTaskStatusEnum;
use App\Models\IndicatorSubmission;
use Illuminate\Support\Facades\Log;

class IndicatorSubmissionObserver
{
    /**
     * Handle the IndicatorSubmission "updated" event.
     */
    public function updated(IndicatorSubmission $indicatorSubmission): void
    {
        if ($indicatorSubmission->isDirty('status')) {
            switch ($indicatorSubmission->status) {
                case IndicatorSubmissionStatusEnum::APPROVED:
                    $this->handleChangeToApproved($indicatorSubmission);
                    break;
                case IndicatorSubmissionStatusEnum::REJECTED:
                    $this->handleChangeToRejected($indicatorSubmission);
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Handle the change to approved status by setting the task status to completed
     */
    private function handleChangeToApproved(IndicatorSubmission $indicatorSubmission): void
    {
        $task = $indicatorSubmission->task;
        if (! $task) {
            Log::error('IndicatorSubmissionObserver: Task not found for submission', [
                'submission_id' => $indicatorSubmission->id,
            ]);

            return;
        }
        $task->status = IndicatorTaskStatusEnum::COMPLETED;
        $task->is_achieved = $indicatorSubmission->is_achieved;
        $task->save();
    }

    /**
     * Handle the change to rejected status by setting the task status to needs revision
     */
    private function handleChangeToRejected(IndicatorSubmission $indicatorSubmission): void
    {
        $task = $indicatorSubmission->task;
        if (! $task) {
            Log::error('IndicatorSubmissionObserver: Task not found for submission', [
                'submission_id' => $indicatorSubmission->id,
            ]);

            return;
        }
        $task->status = IndicatorTaskStatusEnum::NEEDS_REVISION;
        $task->save();
    }
}
