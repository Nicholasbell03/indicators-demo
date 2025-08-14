<?php

declare(strict_types=1);

use App\Enums\IndicatorComplianceTypeEnum;
use App\Enums\IndicatorDisplayStatus;
use App\Enums\IndicatorTaskStatusEnum;
use App\Exceptions\InvalidIndicatorDataException;
use App\Models\IndicatorCompliance;
use App\Models\IndicatorComplianceProgramme;
use App\Models\IndicatorComplianceProgrammeMonth;
use App\Models\IndicatorSuccess;
use App\Models\IndicatorSuccessProgramme;
use App\Models\IndicatorSuccessProgrammeMonth;
use App\Models\IndicatorTask;
use App\Models\Organisation;
use App\Models\OrganisationProgrammeSeat;
use App\Models\Programme;
use App\Models\User;
use App\Services\Indicators\IndicatorDashboardGridService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->organisation = Organisation::factory()->create();

    $this->programme = Programme::factory()->create([
        'period' => 12,
    ]);

    // Set up programme seat with contract start date 3 months ago to simulate month 3
    $this->seat = OrganisationProgrammeSeat::factory()->create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'programme_id' => $this->programme->id,
        'is_active' => true,
        'contract_start_date' => Carbon::now()->subMonths(2)->startOfDay(), // 3 months total (current month = 3)
    ]);

    $this->service = new IndicatorDashboardGridService($this->seat);
});

describe('getSuccessIndicatorsDashboardData', function () {
    it('returns a fully populated grid with correct structure and statuses', function () {
        // Create indicator success with programme association
        $indicator = IndicatorSuccess::factory()->create(['title' => 'Test Success Indicator']);
        $indicatorProgramme = IndicatorSuccessProgramme::factory()->create([
            'indicator_success_id' => $indicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);

        // Create months 1, 2, 3 for this indicator
        $month1 = IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $indicatorProgramme->id,
            'programme_month' => 1,
        ]);
        $month2 = IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $indicatorProgramme->id,
            'programme_month' => 2,
        ]);
        $month3 = IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $indicatorProgramme->id,
            'programme_month' => 3,
        ]);

        // Create tasks with different statuses
        $completedAchievedTask = IndicatorTask::factory()->forSuccess($month1)->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'status' => IndicatorTaskStatusEnum::COMPLETED,
            'is_achieved' => true,
            'due_date' => Carbon::now()->addDays(5),
        ]);

        $completedNotAchievedTask = IndicatorTask::factory()->forSuccess($month2)->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'status' => IndicatorTaskStatusEnum::COMPLETED,
            'is_achieved' => false,
            'due_date' => Carbon::now()->addDays(10),
        ]);

        $submittedTask = IndicatorTask::factory()->forSuccess($month3)->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'status' => IndicatorTaskStatusEnum::SUBMITTED,
            'due_date' => Carbon::now()->addDays(15),
        ]);

        $result = $this->service->getSuccessIndicatorsDashboardData();

        // Assert top-level structure
        expect($result)->toHaveKeys(['indicators', 'programmeMonths', 'currentMonth', 'programmeDuration', 'type']);
        expect($result['type'])->toBe('indicatorSuccess');
        expect($result['currentMonth'])->toBe(3);
        expect($result['programmeDuration'])->toBe(12);
        expect($result['programmeMonths'])->toBe(range(1, 12));

        // Assert indicators structure
        expect($result['indicators'])->toHaveCount(1);
        $indicatorData = $result['indicators'][0];
        expect($indicatorData)->toHaveKeys(['id', 'name', 'months']);
        expect($indicatorData['id'])->toBe($indicator->id);
        expect($indicatorData['name'])->toBe('Test Success Indicator');

        // Assert months data and statuses
        $monthsData = $indicatorData['months'];
        expect($monthsData[1]['status'])->toBe(IndicatorDisplayStatus::ACHIEVED->label());
        expect($monthsData[1]['task_id'])->toBe($completedAchievedTask->id);
        expect($monthsData[1]['due_date'])->toBe($completedAchievedTask->due_date->toISOString());

        expect($monthsData[2]['status'])->toBe(IndicatorDisplayStatus::NOT_ACHIEVED->label());
        expect($monthsData[2]['task_id'])->toBe($completedNotAchievedTask->id);

        expect($monthsData[3]['status'])->toBe(IndicatorDisplayStatus::VERIFYING->label());
        expect($monthsData[3]['task_id'])->toBe($submittedTask->id);

        // Months without tasks should have appropriate status
        expect($monthsData[4]['status'])->toBe(IndicatorDisplayStatus::NOT_APPLICABLE->label());
        expect($monthsData[4]['task_id'])->toBeNull();
    });

    it('returns an empty dashboard structure when no indicators are published for the programme', function () {
        // Don't create any IndicatorSuccessProgramme records

        $result = $this->service->getSuccessIndicatorsDashboardData();

        expect($result)->toEqual([
            'indicators' => [],
            'programmeMonths' => [],
            'currentMonth' => null,
            'programmeDuration' => 0,
        ]);
    });

    it('correctly handles indicators with no tasks created yet', function () {
        Log::shouldReceive('debug')
            ->times(3) // Should log for months 1, 2, 3 (current and past months without tasks)
            ->with(Mockery::pattern('/Task not found for month/'), Mockery::any());

        // Create indicator but no tasks
        $indicator = IndicatorSuccess::factory()->create();
        $indicatorProgramme = IndicatorSuccessProgramme::factory()->create([
            'indicator_success_id' => $indicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);

        // Create months for the indicator
        IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $indicatorProgramme->id,
            'programme_month' => 1,
        ]);
        IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $indicatorProgramme->id,
            'programme_month' => 2,
        ]);
        IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $indicatorProgramme->id,
            'programme_month' => 3,
        ]);
        IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $indicatorProgramme->id,
            'programme_month' => 5,
        ]);

        $result = $this->service->getSuccessIndicatorsDashboardData();

        $monthsData = $result['indicators'][0]['months'];

        // Past/current months (1, 2, 3) with no tasks should be NOT_APPLICABLE
        expect($monthsData[1]['status'])->toBe(IndicatorDisplayStatus::NOT_APPLICABLE->label());
        expect($monthsData[2]['status'])->toBe(IndicatorDisplayStatus::NOT_APPLICABLE->label());
        expect($monthsData[3]['status'])->toBe(IndicatorDisplayStatus::NOT_APPLICABLE->label());

        // Future months with indicators should be NOT_YET_DUE
        expect($monthsData[5]['status'])->toBe(IndicatorDisplayStatus::NOT_YET_DUE->label());

        // Month 4 has no indicator month, so should be NOT_APPLICABLE
        expect($monthsData[4]['status'])->toBe(IndicatorDisplayStatus::NOT_APPLICABLE->label());
    });

    it('filters out unpublished indicators', function () {
        // Create published indicator
        $publishedIndicator = IndicatorSuccess::factory()->create(['title' => 'Published Indicator']);
        IndicatorSuccessProgramme::factory()->create([
            'indicator_success_id' => $publishedIndicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);

        // Create unpublished indicator
        $unpublishedIndicator = IndicatorSuccess::factory()->create(['title' => 'Unpublished Indicator']);
        IndicatorSuccessProgramme::factory()->create([
            'indicator_success_id' => $unpublishedIndicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'pending',
        ]);

        $result = $this->service->getSuccessIndicatorsDashboardData();

        expect($result['indicators'])->toHaveCount(1);
        expect($result['indicators'][0]['name'])->toBe('Published Indicator');
    });

    it('throws exception for invalid user or organisation in constructor', function () {
        // Create a user with invalid ID to trigger validation
        $invalidUser = User::factory()->make(['id' => 0]); // Invalid ID
        $invalidSeat = OrganisationProgrammeSeat::factory()->make([
            'user_id' => $invalidUser->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
        ]);
        $invalidSeat->user = $invalidUser;
        $invalidSeat->organisation = $this->organisation;
        $invalidSeat->programme = $this->programme;

        $invalidService = new IndicatorDashboardGridService($invalidSeat);

        expect(fn () => $invalidService->getSuccessIndicatorsDashboardData())
            ->toThrow(InvalidIndicatorDataException::class);
    });

    it('uses cache on subsequent calls', function () {
        // Create a simple indicator setup
        $indicator = IndicatorSuccess::factory()->create();
        IndicatorSuccessProgramme::factory()->create([
            'indicator_success_id' => $indicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);

        // Mock the cache to verify behavior
        Cache::shouldReceive('remember')
            ->once()
            ->with(
                "indicators_dashboard_success_user_{$this->user->id}_org_{$this->organisation->id}_programme_{$this->programme->id}",
                300,
                Mockery::type('Closure')
            )
            ->andReturnUsing(function ($key, $ttl, $closure) {
                return $closure();
            });

        // First call should hit the database
        $firstResult = $this->service->getSuccessIndicatorsDashboardData();

        // Reset expectations for second call
        Cache::shouldReceive('remember')
            ->once()
            ->with(
                "indicators_dashboard_success_user_{$this->user->id}_org_{$this->organisation->id}_programme_{$this->programme->id}",
                300,
                Mockery::type('Closure')
            )
            ->andReturn($firstResult); // Return cached result without calling closure

        // Second call should use cache
        $secondResult = $this->service->getSuccessIndicatorsDashboardData();

        expect($secondResult)->toEqual($firstResult);
    });
});

describe('getComplianceIndicatorsDashboardData', function () {
    it('returns a fully populated grid with correct structure and statuses', function () {
        // Create compliance indicator with OTHER type
        $indicator = IndicatorCompliance::factory()->create([
            'title' => 'Test Compliance Indicator',
            'type' => IndicatorComplianceTypeEnum::OTHER,
        ]);
        $indicatorProgramme = IndicatorComplianceProgramme::factory()->create([
            'indicator_compliance_id' => $indicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);

        // Create months and tasks with various statuses
        $month1 = IndicatorComplianceProgrammeMonth::factory()->create([
            'indicator_compliance_programme_id' => $indicatorProgramme->id,
            'programme_month' => 1,
        ]);

        $pendingTask = IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'indicatable_month_id' => $month1->id,
            'indicatable_type' => IndicatorCompliance::class,
            'status' => IndicatorTaskStatusEnum::PENDING,
            'due_date' => Carbon::now()->addDays(5),
        ]);

        $result = $this->service->getComplianceIndicatorsDashboardData();

        // Assert top-level structure
        expect($result)->toHaveKeys(['indicators', 'programmeMonths', 'currentMonth', 'programmeDuration', 'type']);
        expect($result['type'])->toBe('indicatorCompliance');
        expect($result['currentMonth'])->toBe(3);

        // Assert indicators structure
        expect($result['indicators'])->toHaveCount(1);
        $indicatorData = $result['indicators'][0];
        expect($indicatorData['name'])->toBe('Test Compliance Indicator');

        // Assert status calculation
        $monthsData = $indicatorData['months'];
        expect($monthsData[1]['status'])->toBe(IndicatorDisplayStatus::NOT_SUBMITTED->label());
        expect($monthsData[1]['task_id'])->toBe($pendingTask->id);
    });

    it('returns an empty dashboard structure when no indicators are published for the programme', function () {
        $result = $this->service->getComplianceIndicatorsDashboardData();

        expect($result)->toEqual([
            'indicators' => [],
            'programmeMonths' => [],
            'currentMonth' => null,
            'programmeDuration' => 0,
        ]);
    });

    it('filters out non other compliance indicators', function () {
        // Create OTHER type indicator (should be included)
        $otherIndicator = IndicatorCompliance::factory()->create([
            'title' => 'Other Compliance Indicator',
            'type' => IndicatorComplianceTypeEnum::OTHER,
        ]);
        IndicatorComplianceProgramme::factory()->create([
            'indicator_compliance_id' => $otherIndicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);

        // Create ATTENDANCE_LEARNING type indicator (should be excluded)
        $attendanceIndicator = IndicatorCompliance::factory()->create([
            'title' => 'Attendance Learning Indicator',
            'type' => IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING,
        ]);
        IndicatorComplianceProgramme::factory()->create([
            'indicator_compliance_id' => $attendanceIndicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);

        $result = $this->service->getComplianceIndicatorsDashboardData();

        expect($result['indicators'])->toHaveCount(1);
        expect($result['indicators'][0]['name'])->toBe('Other Compliance Indicator');
    });

    it('uses separate cache keys for success and compliance grids', function () {
        // Create indicators for both types
        $successIndicator = IndicatorSuccess::factory()->create();
        IndicatorSuccessProgramme::factory()->create([
            'indicator_success_id' => $successIndicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);

        $complianceIndicator = IndicatorCompliance::factory()->create([
            'type' => IndicatorComplianceTypeEnum::OTHER,
        ]);
        IndicatorComplianceProgramme::factory()->create([
            'indicator_compliance_id' => $complianceIndicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);

        // Mock cache for success indicators
        Cache::shouldReceive('remember')
            ->once()
            ->with(
                "indicators_dashboard_success_user_{$this->user->id}_org_{$this->organisation->id}_programme_{$this->programme->id}",
                300,
                Mockery::type('Closure')
            )
            ->andReturnUsing(function ($key, $ttl, $closure) {
                return $closure();
            });

        // Mock cache for compliance indicators
        Cache::shouldReceive('remember')
            ->once()
            ->with(
                "indicators_dashboard_compliance_user_{$this->user->id}_org_{$this->organisation->id}_programme_{$this->programme->id}",
                300,
                Mockery::type('Closure')
            )
            ->andReturnUsing(function ($key, $ttl, $closure) {
                return $closure();
            });

        // Both calls should hit their respective cache keys
        $this->service->getSuccessIndicatorsDashboardData();
        $this->service->getComplianceIndicatorsDashboardData();
    });
});

describe('calculateDisplayStatus behavior', function () {
    beforeEach(function () {
        $this->indicator = IndicatorSuccess::factory()->create();
        $this->indicatorProgramme = IndicatorSuccessProgramme::factory()->create([
            'indicator_success_id' => $this->indicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);
    });

    it('returns status NOT_APPLICABLE for months without a due date', function () {
        // Create month 5 but don't create an IndicatorSuccessProgrammeMonth for it
        // Month 5 should be NOT_APPLICABLE since it has no due date

        $result = $this->service->getSuccessIndicatorsDashboardData();
        $monthsData = $result['indicators'][0]['months'];

        expect($monthsData[5]['status'])->toBe(IndicatorDisplayStatus::NOT_APPLICABLE->label());
    });

    it('returns status NOT_YET_DUE for future months with no task', function () {
        // Create month 6 (future month, user is on month 3)
        IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $this->indicatorProgramme->id,
            'programme_month' => 6,
        ]);

        $result = $this->service->getSuccessIndicatorsDashboardData();
        $monthsData = $result['indicators'][0]['months'];

        expect($monthsData[6]['status'])->toBe(IndicatorDisplayStatus::NOT_YET_DUE->label());
    });

    it('returns status ACHIEVED for completed and achieved tasks', function () {
        $month = IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $this->indicatorProgramme->id,
            'programme_month' => 1,
        ]);

        IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'indicatable_month_id' => $month->id,
            'indicatable_type' => IndicatorSuccess::class,
            'status' => IndicatorTaskStatusEnum::COMPLETED,
            'is_achieved' => true,
        ]);

        $result = $this->service->getSuccessIndicatorsDashboardData();
        $monthsData = $result['indicators'][0]['months'];

        expect($monthsData[1]['status'])->toBe(IndicatorDisplayStatus::ACHIEVED->label());
    });

    it('returns status NOT_ACHIEVED for completed and not achieved tasks', function () {
        $month = IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $this->indicatorProgramme->id,
            'programme_month' => 1,
        ]);

        IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'indicatable_month_id' => $month->id,
            'indicatable_type' => IndicatorSuccess::class,
            'status' => IndicatorTaskStatusEnum::COMPLETED,
            'is_achieved' => false,
        ]);

        $result = $this->service->getSuccessIndicatorsDashboardData();
        $monthsData = $result['indicators'][0]['months'];

        expect($monthsData[1]['status'])->toBe(IndicatorDisplayStatus::NOT_ACHIEVED->label());
    });

    it('returns status VERIFYING for submitted tasks', function () {
        $month = IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $this->indicatorProgramme->id,
            'programme_month' => 1,
        ]);

        IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'indicatable_month_id' => $month->id,
            'indicatable_type' => IndicatorSuccess::class,
            'status' => IndicatorTaskStatusEnum::SUBMITTED,
        ]);

        $result = $this->service->getSuccessIndicatorsDashboardData();
        $monthsData = $result['indicators'][0]['months'];

        expect($monthsData[1]['status'])->toBe(IndicatorDisplayStatus::VERIFYING->label());
    });

    it('returns status NOT_SUBMITTED for pending tasks', function () {
        $month = IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $this->indicatorProgramme->id,
            'programme_month' => 1,
        ]);

        IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'indicatable_month_id' => $month->id,
            'indicatable_type' => IndicatorSuccess::class,
            'status' => IndicatorTaskStatusEnum::PENDING,
        ]);

        $result = $this->service->getSuccessIndicatorsDashboardData();
        $monthsData = $result['indicators'][0]['months'];

        expect($monthsData[1]['status'])->toBe(IndicatorDisplayStatus::NOT_SUBMITTED->label());
    });

    it('returns status NOT_SUBMITTED for overdue tasks', function () {
        $month = IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $this->indicatorProgramme->id,
            'programme_month' => 1,
        ]);

        IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'indicatable_month_id' => $month->id,
            'indicatable_type' => IndicatorSuccess::class,
            'status' => IndicatorTaskStatusEnum::OVERDUE,
        ]);

        $result = $this->service->getSuccessIndicatorsDashboardData();
        $monthsData = $result['indicators'][0]['months'];

        expect($monthsData[1]['status'])->toBe(IndicatorDisplayStatus::NOT_SUBMITTED->label());
    });

    it('returns status NEEDS_REVISION for needs_revision tasks', function () {
        $month = IndicatorSuccessProgrammeMonth::factory()->create([
            'indicator_success_programme_id' => $this->indicatorProgramme->id,
            'programme_month' => 1,
        ]);

        IndicatorTask::factory()->create([
            'entrepreneur_id' => $this->user->id,
            'organisation_id' => $this->organisation->id,
            'programme_id' => $this->programme->id,
            'indicatable_month_id' => $month->id,
            'indicatable_type' => IndicatorSuccess::class,
            'status' => IndicatorTaskStatusEnum::NEEDS_REVISION,
        ]);

        $result = $this->service->getSuccessIndicatorsDashboardData();
        $monthsData = $result['indicators'][0]['months'];

        expect($monthsData[1]['status'])->toBe(IndicatorDisplayStatus::NEEDS_REVISION->label());
    });
});

describe('flushCache', function () {
    it('clears both success and compliance cache keys', function () {
        // Create indicators to populate cache
        $successIndicator = IndicatorSuccess::factory()->create();
        IndicatorSuccessProgramme::factory()->create([
            'indicator_success_id' => $successIndicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);

        $complianceIndicator = IndicatorCompliance::factory()->create([
            'type' => IndicatorComplianceTypeEnum::OTHER,
        ]);
        IndicatorComplianceProgramme::factory()->create([
            'indicator_compliance_id' => $complianceIndicator->id,
            'programme_id' => $this->programme->id,
            'status' => 'published',
        ]);

        // Mock cache operations
        $successCacheKey = "indicators_dashboard_success_user_{$this->user->id}_org_{$this->organisation->id}_programme_{$this->programme->id}";
        $complianceCacheKey = "indicators_dashboard_compliance_user_{$this->user->id}_org_{$this->organisation->id}_programme_{$this->programme->id}";

        // Prime cache
        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(function ($key, $ttl, $closure) {
                return $closure();
            });

        $this->service->getSuccessIndicatorsDashboardData();
        $this->service->getComplianceIndicatorsDashboardData();

        // Test cache flush
        Cache::shouldReceive('forget')
            ->once()
            ->with($successCacheKey);

        Cache::shouldReceive('forget')
            ->once()
            ->with($complianceCacheKey);

        $this->service->flushCache();

        // Verify cache keys were forgotten by expecting new cache calls
        Cache::shouldReceive('remember')
            ->twice()
            ->andReturnUsing(function ($key, $ttl, $closure) {
                return $closure();
            });

        // These calls should hit the database again since cache was cleared
        $this->service->getSuccessIndicatorsDashboardData();
        $this->service->getComplianceIndicatorsDashboardData();
    });
});
