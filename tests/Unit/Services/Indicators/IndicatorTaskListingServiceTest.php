<?php

use App\Enums\IndicatorTaskStatusEnum;
use App\Models\IndicatorTask;
use App\Models\Organisation;
use App\Models\OrganisationProgrammeSeat;
use App\Models\Programme;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Indicators\IndicatorTaskListingService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->organisation = Organisation::factory()->create();
    $this->organisation->tenants()->attach($this->tenant);
    $this->user = User::factory()->create();
    $this->programme = Programme::factory()->create();
    $this->programme->tenants()->attach($this->tenant);
    $this->organisationProgrammeSeat = OrganisationProgrammeSeat::factory()
        ->organisation($this->organisation)
        ->programme($this->programme)
        ->user($this->user)
        ->ignitionDate(now()->subWeek())
        // Must have a contract start date in the past
        ->contractStartDate(now()->subWeek())
        ->create();

    $this->indicatorService = new IndicatorTaskListingService(
        $this->organisationProgrammeSeat
    );
});

describe('hasIndicatorTasks', function () {
    it('returns true when user has indicator tasks for the organisation', function () {
        // Create indicator task with proper responsible_user_id
        IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
        ]);

        $result = $this->indicatorService->hasIndicatorTasks();

        expect($result)->toBeTrue();
    });

    it('returns false when user has no indicator tasks for the organisation', function () {
        $result = $this->indicatorService->hasIndicatorTasks();

        expect($result)->toBeFalse();
    });

    it('returns false when user has indicator tasks for different organisation', function () {
        $anotherOrganisation = Organisation::factory()->create();
        $anotherOrganisation->tenants()->attach($this->tenant);

        // Create indicator task for different organisation
        IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $anotherOrganisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
        ]);

        $result = $this->indicatorService->hasIndicatorTasks();

        expect($result)->toBeFalse();
    });

    it('returns false when different user has indicator tasks for the organisation', function () {
        $anotherUser = User::factory()->create();

        // Create indicator task for different user
        IndicatorTask::factory()->create([
            'entrepreneur_id' => $anotherUser->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $anotherUser->id,
        ]);

        $result = $this->indicatorService->hasIndicatorTasks();

        expect($result)->toBeFalse();
    });

    it('returns false if the Tasks are for a different programme', function () {
        $anotherProgramme = Programme::factory()->create();
        $anotherProgramme->tenants()->attach($this->tenant);

        // Create indicator task for different programme
        IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $anotherProgramme->id,
            'responsible_user_id' => $this->user->id,
        ]);

        $result = $this->indicatorService->hasIndicatorTasks();

        expect($result)->toBeFalse();
    });
});

describe('groupByStatus', function () {
    it('groups indicator tasks by status correctly', function () {
        $pendingTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
        ]);

        $submittedTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::SUBMITTED,
        ]);

        $completedTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::COMPLETED,
        ]);

        $indicators = collect([$pendingTask, $submittedTask, $completedTask]);
        $result = $this->indicatorService->groupByStatus($indicators);

        expect($result)->toHaveKeys(['open', 'verifying', 'complete']);
        expect($result['open'])->toHaveCount(1);
        expect($result['verifying'])->toHaveCount(1);
        expect($result['complete'])->toHaveCount(1);
        expect($result['open'][0]->id)->toBe($pendingTask->id);
        expect($result['verifying'][0]->id)->toBe($submittedTask->id);
        expect($result['complete'][0]->id)->toBe($completedTask->id);
    });

    it('maps pending and needs_revision statuses to open group', function () {
        $pendingTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
        ]);

        $needsRevisionTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::NEEDS_REVISION,
        ]);

        $indicators = collect([$pendingTask, $needsRevisionTask]);
        $result = $this->indicatorService->groupByStatus($indicators);

        expect($result['open'])->toHaveCount(2);
        expect($result['verifying'])->toHaveCount(0);
        expect($result['complete'])->toHaveCount(0);
    });

    it('maps submitted status to verifying group', function () {
        $submittedTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::SUBMITTED,
        ]);

        $indicators = collect([$submittedTask]);
        $result = $this->indicatorService->groupByStatus($indicators);

        expect($result['open'])->toHaveCount(0);
        expect($result['verifying'])->toHaveCount(1);
        expect($result['complete'])->toHaveCount(0);
        expect($result['verifying'][0]->id)->toBe($submittedTask->id);
    });

    it('maps approved status to complete group', function () {
        $completedTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::COMPLETED,
        ]);

        $indicators = collect([$completedTask]);
        $result = $this->indicatorService->groupByStatus($indicators);

        expect($result['open'])->toHaveCount(0);
        expect($result['verifying'])->toHaveCount(0);
        expect($result['complete'])->toHaveCount(1);
        expect($result['complete'][0]->id)->toBe($completedTask->id);
    });

    it('returns empty groups when no tasks provided', function () {
        $indicators = collect([]);
        $result = $this->indicatorService->groupByStatus($indicators);

        expect($result)->toHaveKeys(['open', 'verifying', 'complete']);
        expect($result['open'])->toHaveCount(0);
        expect($result['verifying'])->toHaveCount(0);
        expect($result['complete'])->toHaveCount(0);
    });

    it('handles multiple tasks of the same status', function () {
        $task1 = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
        ]);

        $task2 = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
        ]);

        $indicators = collect([$task1, $task2]);
        $result = $this->indicatorService->groupByStatus($indicators);

        expect($result['open'])->toHaveCount(2);
        expect($result['verifying'])->toHaveCount(0);
        expect($result['complete'])->toHaveCount(0);
    });

    it('maps overdue status to open group', function () {
        $overdueTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'due_date' => now()->subDay(),
        ]);

        $indicators = collect([$overdueTask]);
        $result = $this->indicatorService->groupByStatus($indicators);

        expect($result['open'])->toHaveCount(1);
        expect($result['open'][0]->id)->toBe($overdueTask->id);
    });

    it('throws exception for collection with non-IndicatorTask instances', function () {
        $invalidCollection = collect(['not an indicator task']);

        expect(fn () => $this->indicatorService->groupByStatus($invalidCollection))
            ->toThrow(InvalidArgumentException::class, 'Collection must contain only IndicatorTask instances');
    });
});

describe('getFormattedIndicatorsForUser', function () {
    it('returns formatted indicator data grouped by status', function () {
        $pendingTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
        ]);

        $submittedTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::SUBMITTED,
        ]);

        $result = $this->indicatorService->getFormattedIndicatorsForUser();

        expect($result)->toHaveKeys(['open', 'verifying', 'complete']);
        expect($result['open'])->toHaveCount(1);
        expect($result['verifying'])->toHaveCount(1);
        expect($result['complete'])->toHaveCount(0);

        expect($result['open'][0])->toHaveKeys(['id', 'name', 'status', 'due_date', 'action_type']);
        expect($result['open'][0]['id'])->toBe($pendingTask->id);
    });

    it('returns empty groups when user has no indicator tasks', function () {
        $result = $this->indicatorService->getFormattedIndicatorsForUser();

        expect($result)->toHaveKeys(['open', 'verifying', 'complete']);
        expect($result['open'])->toHaveCount(0);
        expect($result['verifying'])->toHaveCount(0);
        expect($result['complete'])->toHaveCount(0);
    });

    it('filters tasks by due date when dueBefore parameter is provided', function () {
        $earlyTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'due_date' => now()->addDays(1),
        ]);

        $lateTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'due_date' => now()->addDays(5),
        ]);

        $result = $this->indicatorService->getFormattedIndicatorsForUser(now()->addDays(2));

        expect($result['open'])->toHaveCount(1);
        expect($result['open'][0]['id'])->toBe($earlyTask->id);
    });

    it('sets action_type to submit for submittable statuses', function () {
        $pendingTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
        ]);

        $result = $this->indicatorService->getFormattedIndicatorsForUser();

        expect($result['open'][0]['action_type'])->toBe('submit');
    });

    it('sets action_type to view for non-submittable statuses', function () {
        $completedTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::COMPLETED,
        ]);

        $result = $this->indicatorService->getFormattedIndicatorsForUser();

        expect($result['complete'][0]['action_type'])->toBe('view');
    });

    it('groups overdue tasks in the open group', function () {
        $overdueTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'due_date' => now()->subDay(),
        ]);

        $result = $this->indicatorService->getFormattedIndicatorsForUser();

        expect($result['open'])->toHaveCount(1);
        expect($result['open'][0]['id'])->toBe($overdueTask->id);
    });
});

describe('getFormattedSuccessIndicatorsForUser', function () {
    it('returns formatted success indicator data grouped by status', function () {
        $successTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'indicatable_month_type' => 'App\Models\IndicatorSuccessProgrammeMonth',
        ]);

        $complianceTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'indicatable_month_type' => 'App\Models\IndicatorComplianceProgrammeMonth',
        ]);

        $result = $this->indicatorService->getFormattedSuccessIndicatorsForUser();

        expect($result)->toHaveKeys(['open', 'verifying', 'complete']);
        expect($result['open'])->toHaveCount(1);
        expect($result['open'][0]['id'])->toBe($successTask->id);
    });

    it('returns empty groups when user has no success indicator tasks', function () {
        $complianceTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'indicatable_month_type' => 'App\Models\IndicatorComplianceProgrammeMonth',
        ]);

        $result = $this->indicatorService->getFormattedSuccessIndicatorsForUser();

        expect($result)->toHaveKeys(['open', 'verifying', 'complete']);
        expect($result['open'])->toHaveCount(0);
        expect($result['verifying'])->toHaveCount(0);
        expect($result['complete'])->toHaveCount(0);
    });

    it('filters tasks by due date when dueBefore parameter is provided', function () {
        $earlyTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'indicatable_month_type' => 'App\Models\IndicatorSuccessProgrammeMonth',
            'due_date' => now()->addDays(1),
        ]);

        $lateTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'indicatable_month_type' => 'App\Models\IndicatorSuccessProgrammeMonth',
            'due_date' => now()->addDays(5),
        ]);

        $result = $this->indicatorService->getFormattedSuccessIndicatorsForUser(now()->addDays(2));

        expect($result['open'])->toHaveCount(1);
        expect($result['open'][0]['id'])->toBe($earlyTask->id);
    });
});

describe('getFormattedComplianceIndicatorsForUser', function () {
    it('returns formatted compliance indicator data grouped by status', function () {
        $successTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'indicatable_month_type' => 'App\Models\IndicatorSuccessProgrammeMonth',
        ]);

        $complianceTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'indicatable_month_type' => 'App\Models\IndicatorComplianceProgrammeMonth',
        ]);

        $result = $this->indicatorService->getFormattedComplianceIndicatorsForUser();

        expect($result)->toHaveKeys(['open', 'verifying', 'complete']);
        expect($result['open'])->toHaveCount(1);
        expect($result['open'][0]['id'])->toBe($complianceTask->id);
    });

    it('returns empty groups when user has no compliance indicator tasks', function () {
        $successTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'indicatable_month_type' => 'App\Models\IndicatorSuccessProgrammeMonth',
        ]);

        $result = $this->indicatorService->getFormattedComplianceIndicatorsForUser();

        expect($result)->toHaveKeys(['open', 'verifying', 'complete']);
        expect($result['open'])->toHaveCount(0);
        expect($result['verifying'])->toHaveCount(0);
        expect($result['complete'])->toHaveCount(0);
    });

    it('filters tasks by due date when dueBefore parameter is provided', function () {
        $earlyTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'indicatable_month_type' => 'App\Models\IndicatorComplianceProgrammeMonth',
            'due_date' => now()->addDays(1),
        ]);

        $lateTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'responsible_user_id' => $this->user->id,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'indicatable_month_type' => 'App\Models\IndicatorComplianceProgrammeMonth',
            'due_date' => now()->addDays(5),
        ]);

        $result = $this->indicatorService->getFormattedComplianceIndicatorsForUser(now()->addDays(2));

        expect($result['open'])->toHaveCount(1);
        expect($result['open'][0]['id'])->toBe($earlyTask->id);
    });
});
