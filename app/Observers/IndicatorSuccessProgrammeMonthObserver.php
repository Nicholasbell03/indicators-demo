<?php

namespace App\Observers;

use App\Models\IndicatorSuccessProgrammeMonth;
use App\Observers\Traits\DeletesRelatedIndicatorTasks;

class IndicatorSuccessProgrammeMonthObserver
{
    use DeletesRelatedIndicatorTasks;

    public function saving(IndicatorSuccessProgrammeMonth $indicatorSuccessProgrammeMonth)
    {
        $this->checkMonthIsValid($indicatorSuccessProgrammeMonth);
    }

    private function checkMonthIsValid(IndicatorSuccessProgrammeMonth $indicatorSuccessProgrammeMonth)
    {
        $programme = $indicatorSuccessProgrammeMonth->indicatorSuccessProgramme->programme;

        if (! $programme) {
            throw new \InvalidArgumentException('Programme not found');
        }

        if ($indicatorSuccessProgrammeMonth->programme_month < 1) {
            throw new \InvalidArgumentException('Month is not valid, it must be greater than 0');
        }

        if ($indicatorSuccessProgrammeMonth->programme_month > $programme->duration) {
            throw new \InvalidArgumentException('Month is not valid, it must be less than or equal to the programme duration, which is '.$programme->duration.' months');
        }
    }
}
