<?php

namespace App\Filament\Admin\Clusters;

use App\Enums\UserPermissions;
use App\Models\IndicatorReviewTask;
use App\Models\IndicatorTask;
use App\Models\User;
use Filament\Clusters\Cluster;
use Filament\Pages\SubNavigationPosition;

class Indicators extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 1;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function shouldRegisterNavigation(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Check if user has any IndicatorTasks assigned to them
        $hasIndicatorTasks = IndicatorTask::where('responsible_user_id', $user->id)->exists();

        // Check if user has any IndicatorReviewTasks assigned to them
        $hasReviewTasks = IndicatorReviewTask::where('verifier_user_id', $user->id)->exists();

        $userHasPermission = $user->hasPermission(UserPermissions::MANAGE_INDICATOR_REVIEW_TASKS->value);

        return $hasIndicatorTasks || $hasReviewTasks || $userHasPermission;
    }
}
