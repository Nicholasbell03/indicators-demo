<?php

namespace App\Observers\Traits;

use Illuminate\Database\Eloquent\Model;

trait DeletesRelatedIndicatorTasks
{
    /**
     * Handle the "deleting" event for the model.
     * This will soft-delete all related indicator tasks.
     */
    public function deleting(Model $model): void
    {
        /**
         * Not IndicatorTask has soft deletes, so this will soft delete all related tasks.
         */
        $model->tasks()->delete();
    }
}
