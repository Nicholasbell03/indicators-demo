<?php

namespace App\Observers;

use App\Models\IndicatorTask;
use Illuminate\Support\Facades\Log;

class IndicatorTaskObserver
{
    /**
     * If an IndicatorTask has no submissions it can be force deleted, if it has a submission it can be soft deleted.
     * This provides the best of both worlds, it prevents an unnecessary build up of soft deleted tasks, but ensures that submissions are always available and the task is still available for reporting.
     */
    public function deleting(IndicatorTask $task)
    {
        if ($task->submissions()->exists()) {
            $task->delete();
        } else {
            Log::channel('indicators')->info('Force deleting IndicatorTask, no submissions yet', $task->toArray());
            $task->forceDelete();
        }
    }
}
