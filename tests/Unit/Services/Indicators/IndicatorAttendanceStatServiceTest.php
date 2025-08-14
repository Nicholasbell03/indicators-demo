<?php

use App\Enums\IndicatorComplianceTypeEnum;
use App\Enums\SessionCategoryType;
use App\Models\IndicatorCompliance;
use App\Models\IndicatorComplianceProgramme;
use App\Models\IndicatorComplianceProgrammeMonth;
use App\Models\Organisation;
use App\Models\OrganisationProgrammeSeat;
use App\Models\Programme;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Indicators\IndicatorAttendanceStatService;
use App\Services\ProgrammeSessionAttendanceService;
use App\Services\ProgrammeSessionAttendanceServiceFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->organisation = Organisation::factory()->create();
    $this->organisation->tenants()->attach($this->tenant);
    $this->user = User::factory()->create();
    $this->programme = Programme::factory()->create(['period' => 12]); // 12 month programme
    $this->programme->tenants()->attach($this->tenant);

    // Mock the factory
    $this->mockFactory = Mockery::mock(ProgrammeSessionAttendanceServiceFactory::class);
    $this->mockAttendanceService = Mockery::mock(ProgrammeSessionAttendanceService::class);

    // Helper Functions as closures
    $this->createProgrammeSeatForMonth = function (int $month, ?Programme $programme = null, ?User $user = null): OrganisationProgrammeSeat {
        $programme = $programme ?? $this->programme;
        $user = $user ?? $this->user;

        $contractDate = match ($month) {
            0 => now()->addMonth(), // Future date for month 0 (not started)
            1 => now()->startOfMonth(),
            default => now()->subMonths($month - 1)
        };

        return OrganisationProgrammeSeat::factory()
            ->organisation($this->organisation)
            ->programme($programme)
            ->user($user)
            ->contractStartDate($contractDate)
            ->create();
    };

    $this->setupIndicatorComplianceData = function (int $month, string $targetValue, IndicatorComplianceTypeEnum $type = IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING, ?Programme $programme = null): void {
        $programme = $programme ?? $this->programme;

        $indicatorCompliance = IndicatorCompliance::factory()
            ->forType($type)
            ->create();

        $indicatorComplianceProgramme = IndicatorComplianceProgramme::factory()
            ->create([
                'indicator_compliance_id' => $indicatorCompliance->id,
                'programme_id' => $programme->id,
            ]);

        IndicatorComplianceProgrammeMonth::factory()
            ->create([
                'indicator_compliance_programme_id' => $indicatorComplianceProgramme->id,
                'programme_month' => $month,
                'target_value' => $targetValue,
            ]);
    };

    $this->setupMockAttendanceService = function (array $attendanceStats, ?OrganisationProgrammeSeat $seat = null): void {
        $this->mockFactory
            ->shouldReceive('create')
            ->with($seat ?? Mockery::any())
            ->andReturn($this->mockAttendanceService);

        $this->mockAttendanceService
            ->shouldReceive('getAttendanceStats')
            ->with(Mockery::any(), false)
            ->andReturn($attendanceStats);
    };

    $this->getDefaultAttendanceStats = function (): array {
        return [
            'attended' => 8,
            'missed' => 2,
            'notMarked' => 0,
            'total' => 10,
            'percentage' => 80.0,
            'meta' => [],
        ];
    };
});

afterEach(function () {
    Cache::flush();
    Mockery::close();
});

describe('getAttendanceStats', function () {
    it('returns attendance stats with target percentage when all data is available', function () {
        $organisationProgrammeSeat = ($this->createProgrammeSeatForMonth)(3);
        $service = new IndicatorAttendanceStatService($organisationProgrammeSeat, $this->mockFactory);

        ($this->setupIndicatorComplianceData)(3, '85');
        ($this->setupMockAttendanceService)(($this->getDefaultAttendanceStats)(), $organisationProgrammeSeat);

        $result = $service->getAttendanceStats(SessionCategoryType::LEARNING);

        expect($result)->toHaveKeys([
            'attended', 'missed', 'notMarked', 'total', 'percentage', 'meta', 'target_percentage',
        ]);
        expect($result['attended'])->toBe(8);
        expect($result['missed'])->toBe(2);
        expect($result['total'])->toBe(10);
        expect($result['percentage'])->toBe(80.0);
        expect($result['target_percentage'])->toBe('85');
    });

    it('returns attendance stats for mentoring sessions', function () {
        $organisationProgrammeSeat = ($this->createProgrammeSeatForMonth)(2);
        $service = new IndicatorAttendanceStatService($organisationProgrammeSeat, $this->mockFactory);

        ($this->setupIndicatorComplianceData)(2, '90', IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING);

        $mentoringStats = [
            'attended' => 5,
            'missed' => 1,
            'notMarked' => 0,
            'total' => 6,
            'percentage' => 83.33,
            'meta' => [],
        ];
        ($this->setupMockAttendanceService)($mentoringStats, $organisationProgrammeSeat);

        $result = $service->getAttendanceStats(SessionCategoryType::MENTORING);

        expect($result['target_percentage'])->toBe('90');
        expect($result['attended'])->toBe(5);
        expect($result['total'])->toBe(6);
    });

    it('returns null and logs warning when user has no current programme month', function () {
        $organisationProgrammeSeat = ($this->createProgrammeSeatForMonth)(0); // Month 0 = future date
        $service = new IndicatorAttendanceStatService($organisationProgrammeSeat, $this->mockFactory);

        Log::shouldReceive('warning')
            ->once()
            ->with('Could not determine current programme month for entrepreneur.', Mockery::any());

        $result = $service->getAttendanceStats(SessionCategoryType::LEARNING);

        expect($result)->toBeNull();
    });

    it('returns null and logs debug when no indicator compliance programme exists', function () {
        $organisationProgrammeSeat = ($this->createProgrammeSeatForMonth)(5);
        $service = new IndicatorAttendanceStatService($organisationProgrammeSeat, $this->mockFactory);

        Log::shouldReceive('debug')
            ->once()
            ->with('No indicator compliance programme record found for the following criteria', Mockery::any());

        // Don't create any indicator compliance programme data

        $result = $service->getAttendanceStats(SessionCategoryType::LEARNING);

        expect($result)->toBeNull();
    });

    it('returns null and logs warning when no month setting exists for current programme month', function () {
        $organisationProgrammeSeat = ($this->createProgrammeSeatForMonth)(6);
        $service = new IndicatorAttendanceStatService($organisationProgrammeSeat, $this->mockFactory);

        Log::shouldReceive('warning')
            ->once()
            ->with('No attendance target setting found for current programme month', Mockery::any());

        // Create compliance data for a different month (3) when we need month 6
        ($this->setupIndicatorComplianceData)(3, '85');

        $result = $service->getAttendanceStats(SessionCategoryType::LEARNING);

        expect($result)->toBeNull();
    });

    it('rigorously tests query by ignoring records from other programmes and types', function () {
        $organisationProgrammeSeat = ($this->createProgrammeSeatForMonth)(2);
        $service = new IndicatorAttendanceStatService($organisationProgrammeSeat, $this->mockFactory);

        // Correct compliance data that should be found
        ($this->setupIndicatorComplianceData)(2, '90');

        // Data for a different programme that should be ignored
        $anotherProgramme = Programme::factory()->create();
        ($this->setupIndicatorComplianceData)(2, '80', IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING, $anotherProgramme);

        // Data for a different type that should be ignored
        ($this->setupIndicatorComplianceData)(2, '70', IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING);

        ($this->setupMockAttendanceService)(($this->getDefaultAttendanceStats)(), $organisationProgrammeSeat);

        $result = $service->getAttendanceStats(SessionCategoryType::LEARNING);

        // Should find the correct target of '90' and not the others
        expect($result['target_percentage'])->toBe('90');
    });

    it('uses cache for repeated calls', function () {
        $organisationProgrammeSeat = ($this->createProgrammeSeatForMonth)(1);
        $service = new IndicatorAttendanceStatService($organisationProgrammeSeat, $this->mockFactory);

        ($this->setupIndicatorComplianceData)(1, '80');

        $cacheStats = [
            'attended' => 4,
            'missed' => 1,
            'notMarked' => 0,
            'total' => 5,
            'percentage' => 80.0,
            'meta' => [],
        ];

        // Mock should only be called once due to caching
        $this->mockFactory
            ->shouldReceive('create')
            ->once()
            ->with($organisationProgrammeSeat)
            ->andReturn($this->mockAttendanceService);

        $this->mockAttendanceService
            ->shouldReceive('getAttendanceStats')
            ->once()
            ->with(SessionCategoryType::LEARNING, false)
            ->andReturn($cacheStats);

        // First call
        $result1 = $service->getAttendanceStats(SessionCategoryType::LEARNING);

        // Second call should use cache
        $result2 = $service->getAttendanceStats(SessionCategoryType::LEARNING);

        expect($result1['target_percentage'])->toBe('80');
        expect($result2['target_percentage'])->toBe('80');
        expect($result1['attended'])->toBe(4);
        expect($result2['attended'])->toBe(4);
    });

    it('handles OTHER session category type and throws exception', function () {
        $organisationProgrammeSeat = ($this->createProgrammeSeatForMonth)(2);
        $service = new IndicatorAttendanceStatService($organisationProgrammeSeat, $this->mockFactory);

        expect(fn () => $service->getAttendanceStats(SessionCategoryType::OTHER))
            ->toThrow(\Exception::class, 'No corresponding indicator compliance type found for session category type');
    });

    it('creates correct cache key for different users and organisations', function () {
        $anotherUser = User::factory()->create();
        $anotherOrganisation = Organisation::factory()->create();

        $organisationProgrammeSeat1 = ($this->createProgrammeSeatForMonth)(1);
        $organisationProgrammeSeat2 = ($this->createProgrammeSeatForMonth)(1, $this->programme, $anotherUser);
        $organisationProgrammeSeat2->organisation()->associate($anotherOrganisation)->save();

        $service1 = new IndicatorAttendanceStatService($organisationProgrammeSeat1, $this->mockFactory);
        $service2 = new IndicatorAttendanceStatService($organisationProgrammeSeat2, $this->mockFactory);

        ($this->setupIndicatorComplianceData)(1, '85');

        $isolatedCacheStats = [
            'attended' => 3,
            'missed' => 2,
            'notMarked' => 0,
            'total' => 5,
            'percentage' => 60.0,
            'meta' => [],
        ];

        // Both services should call the factory (different cache keys)
        $this->mockFactory
            ->shouldReceive('create')
            ->twice()
            ->andReturn($this->mockAttendanceService);

        $this->mockAttendanceService
            ->shouldReceive('getAttendanceStats')
            ->twice()
            ->with(SessionCategoryType::LEARNING, false)
            ->andReturn($isolatedCacheStats);

        $result1 = $service1->getAttendanceStats(SessionCategoryType::LEARNING);
        $result2 = $service2->getAttendanceStats(SessionCategoryType::LEARNING);

        // Both should have the same structure but independent cache
        expect($result1)->toHaveKey('target_percentage');
        expect($result2)->toHaveKey('target_percentage');
        expect($result1['attended'])->toBe(3);
        expect($result2['attended'])->toBe(3);
    });

    it('cache expires after the defined TTL', function () {
        $organisationProgrammeSeat = ($this->createProgrammeSeatForMonth)(1);
        $service = new IndicatorAttendanceStatService($organisationProgrammeSeat, $this->mockFactory);

        ($this->setupIndicatorComplianceData)(1, '80');

        $stats = ($this->getDefaultAttendanceStats)();

        // Mock should be called twice since we're clearing cache
        $this->mockFactory
            ->shouldReceive('create')
            ->twice()
            ->with($organisationProgrammeSeat)
            ->andReturn($this->mockAttendanceService);

        $this->mockAttendanceService
            ->shouldReceive('getAttendanceStats')
            ->twice()
            ->with(SessionCategoryType::LEARNING, false)
            ->andReturn($stats);

        // First call - populates cache
        $result1 = $service->getAttendanceStats(SessionCategoryType::LEARNING);
        expect($result1['target_percentage'])->toBe('80');

        // Travel 6 minutes into the future, beyond the 5-minute TTL
        $this->travel(6)->minutes();

        // Second call - should re-fetch from service
        $result2 = $service->getAttendanceStats(SessionCategoryType::LEARNING);
        expect($result2['target_percentage'])->toBe('80');
    });
});
