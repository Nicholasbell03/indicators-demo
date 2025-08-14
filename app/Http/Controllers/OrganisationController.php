<?php

namespace App\Http\Controllers;

use App\Actions\CurrentOrganisationHelper;
use App\Enums\ObjectiveStatus;
use App\Enums\OrganisationPermissions;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Facades\IndicatorEntrepreneurDashboardFacadeFactory;
use App\Models\EngaugeSession;
use App\Models\FieldProgress;
use App\Models\Organisation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class OrganisationController extends Controller
{
    public function __construct(
        private IndicatorEntrepreneurDashboardFacadeFactory $indicatorEntrepreneurDashboardFacadeFactory
    ) {}

    public function home(Organisation $organisation)
    {
        if (! $organisation->active) {
            return Redirect::route('organisation.inactive', ['organisation' => $organisation->id]);
        }

        $set = CurrentOrganisationHelper::getInstance()->set($organisation->id);
        if ($set) {
            /** @var User $user */
            $user = auth()->user();
            if ($user->isOwner($organisation) || $user->isGuide() || $user->isFlowcoder()) {
                return Redirect::route('organisation.overview', [
                    'organisation' => $organisation,

                ]);
            } else {
                return Redirect::route('organisation.priorities', [
                    'organisation' => $organisation,

                ]);
            }
        } else {
            return Redirect::route('filament.admin.pages.dashboard');
        }
    }

    public function set(Organisation $organisation)
    {
        $set = CurrentOrganisationHelper::getInstance()->set($organisation->id);

        if ($set) {
            return Redirect::route('organisation.home', ['organisation' => $organisation->id]);
        } else {
            return Redirect::route('dashboard.view');
        }
    }

    public function overview(Request $request, Organisation $organisation)
    {
        $commonData = $this->setupDashboardData($organisation);
        $tabData = $this->getOverviewData($request, $organisation, $commonData['user']);

        return Inertia::render('App/Home', array_merge($commonData, $tabData, ['activeTab' => 'overview']));
    }

    public function priorities(Request $request, Organisation $organisation)
    {
        $commonData = $this->setupDashboardData($organisation);
        $tabData = $this->getPrioritiesData($request, $organisation, $commonData['user']);

        return Inertia::render('App/Home', array_merge($commonData, $tabData, ['activeTab' => 'priorities']));
    }

    public function calendar(Request $request, Organisation $organisation)
    {
        $commonData = $this->setupDashboardData($organisation);
        $tabData = $this->getCalendarData($request, $organisation, $commonData['user']);

        return Inertia::render('App/Home', array_merge($commonData, $tabData, ['activeTab' => 'calendar']));
    }

    public function indicators(Request $request, Organisation $organisation)
    {
        $commonData = $this->setupDashboardData($organisation);
        $tabData = $this->getIndicatorsData($request, $organisation, $commonData['user']);

        return Inertia::render('App/Home', array_merge($commonData, $tabData, ['activeTab' => 'indicators']));
    }

    public function inactive(Organisation $organisation)
    {
        $tenant = app('currentTenant');

        return Inertia::render('App/InactiveOrganisation', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Setup common dashboard data and perform authorization checks.
     */
    private function setupDashboardData(Organisation $organisation): array
    {
        /** @var User $user */
        $user = auth()->user();

        // Check if user has Engauge sessions
        $hasEvents = $this->checkUserHasSessions($user);

        $programmeSeat = $user->currentProgrammeSeat($organisation);
        if ($programmeSeat) {
            $dashboardInterface = $this->indicatorEntrepreneurDashboardFacadeFactory->create($programmeSeat);
            $hasIndicators = $dashboardInterface->seatHasIndicatorTasks();
        } else {
            $hasIndicators = false;
        }

        return [
            'organisation' => $organisation,
            'user' => $user,
            'hasEvents' => $hasEvents,
            'hasIndicators' => $hasIndicators,
        ];
    }

    /**
     * Check if user has Engauge sessions.
     */
    private function checkUserHasSessions(User $user): bool
    {
        try {
            return EngaugeSession::ofEngaugeUser($user->id)->exists();
        } catch (\Throwable $th) {
            Log::error('Error checking user has sessions', [
                'user_id' => $user->id,
                'error' => $th->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get Engauge sessions for user if they have any.
     */
    private function getEngaugeSessions(User $user): array
    {
        try {
            return EngaugeSession::ofEngaugeUser($user->id)->orderBy('start_datetime', 'asc')->get()->toArray();
        } catch (\Throwable $th) {
            Log::error('Error getting Engauge sessions', [
                'user_id' => $user->id,
                'error' => $th->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Calculate financial year dates based on organisation settings.
     */
    private function calculateFinancialYear(Request $request, Organisation $organisation): array
    {
        $year = $request->route('year') ?? Carbon::now()->year;
        $date = Carbon::createFromFormat('Y', $year);
        $from = $date->copy()->setMonth($organisation->financial_year_start_month)->firstOfMonth();

        if (! $request->route('year') && Carbon::now() < $from) {
            $from->subYear();
        }

        $to = $from->copy()->addMonths(12)->subSecond();

        return [
            'year' => $year,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Build user-scoped objectives query.
     */
    private function buildUserScopedObjectives(Organisation $organisation, User $user, Request $request)
    {
        return $organisation->objectives()
            ->searchOrSort($request)
            ->filter($request, [
                'department_id' => ['departments', 'id'],
                'division_id' => ['divisions', 'id'],
            ])
            ->where(function ($query) use ($user, $organisation) {
                if (! $user->hasOrganisationPermissions($organisation, OrganisationPermissions::MANAGE_PROJECTS->value)
                    && ! $user->isGuide()
                    && ! $user->isFlowcoder()) {
                    $query->where('owner_id', $user->id);
                }
            });
    }

    /**
     * Build user-scoped projects query.
     */
    private function buildUserScopedProjects(Organisation $organisation, User $user, Request $request)
    {
        return $organisation->projects()
            ->searchOrSort($request)
            ->filter($request, [
                'department_id' => ['departments', 'id'],
                'division_id' => ['divisions', 'id'],
                'source',
                'status',
                'owner_id',
            ])
            ->where(function ($query) use ($user, $organisation) {
                if (! $user->hasOrganisationPermissions($organisation, OrganisationPermissions::MANAGE_PROJECTS->value)
                    && ! $user->isGuide()
                    && ! $user->isFlowcoder()) {
                    $query->where('owner_id', $user->id);
                }
            });
    }

    /**
     * Build user-scoped tasks query.
     */
    private function buildUserScopedTasks(Organisation $organisation, User $user, Request $request)
    {
        return $organisation->tasks()
            ->searchOrSort($request, ['name'])
            ->filter($request, [
                'department_id' => ['departments', 'id'],
                'division_id' => ['divisions', 'id'],
                'source',
                'status',
                'owner_id',
                'opal_task',
            ])
            ->where(function ($query) use ($user, $organisation) {
                if (! $user->hasOrganisationPermissions($organisation, OrganisationPermissions::MANAGE_TASKS->value)
                    && ! $user->isGuide()
                    && ! $user->isFlowcoder()) {
                    $query->where('owner_id', $user->id)->orWhere('creator_id', $user->id);
                }
            });
    }

    /**
     * Get overview tab specific data.
     */
    private function getOverviewData(Request $request, Organisation $organisation, User $user): array
    {
        $financialYear = $this->calculateFinancialYear($request, $organisation);

        $objectives_count = $organisation->objectives()
            ->searchOrSort($request)
            ->filter($request, [
                'department_id' => ['departments', 'id'],
                'division_id' => ['divisions', 'id'],
            ])
            ->startingOrEndingIn($financialYear['from']->format('Y'))
            ->count();

        $projects_count = $this->buildUserScopedProjects($organisation, $user, $request)
            ->overlappingWithin($financialYear['from'], $financialYear['to'])
            ->count();

        $tasks_count = $this->buildUserScopedTasks($organisation, $user, $request)
            ->dueBetween($financialYear['from'], $financialYear['to'])
            ->count();

        $targets = $user->targetFavourites()
            ->searchOrSort($request)
            ->filter($request, [
                'department_id' => ['departments', 'id'],
                'division_id' => ['divisions', 'id'],
            ])
            ->with(['departments', 'owner'])
            ->dueBetween($financialYear['from'], $financialYear['to']);

        if ($targets->count() === 0) {
            $targets = $organisation->targets()
                ->searchOrSort($request)
                ->filter($request, [
                    'department_id' => ['departments', 'id'],
                    'division_id' => ['divisions', 'id'],
                ])
                ->with(['departments', 'owner'])
                ->dueBetween($financialYear['from'], $financialYear['to'])
                ->take(3);
        }

        return [
            'objectives_count' => $objectives_count,
            'projects_count' => $projects_count,
            'tasks_count' => $tasks_count,
            'targets' => $targets->get(),
            'year' => $financialYear['year'],
            'financial_year_starts' => $financialYear['from'],
            'financial_year_ends' => $financialYear['to'],
            'roadmap' => $user->unreadRoadmap(),
            'last_field_progress' => FieldProgress::where('user_id', $user->id)
                ->whereHas('element', function ($query) use ($user, $organisation) {
                    $query->whereIn('id', $user->elements($organisation)->pluck('id'));
                })
                ->latest()
                ->first(),
        ];
    }

    /**
     * Get priorities tab specific data.
     */
    private function getPrioritiesData(Request $request, Organisation $organisation, User $user): array
    {
        $projects = $this->buildUserScopedProjects($organisation, $user, $request);
        $objectives = $this->buildUserScopedObjectives($organisation, $user, $request);
        $tasks = $this->buildUserScopedTasks($organisation, $user, $request);

        // OPTs are COMPLETE but not yet approved (i.e. require approval)
        $approval_count = $objectives
            ->clone()
            ->where('status', ObjectiveStatus::COMPLETE)
            ->where('completion_approved', false)
            ->count();

        $overdue_count = array_sum([
            $objectives->clone()->where('status', ObjectiveStatus::OVERDUE)->count(),
            $projects->clone()->where('status', ProjectStatus::OVERDUE)->count(),
            $tasks->clone()->where('status', TaskStatus::OVERDUE)->count(),
        ]);

        return [
            'approval_count' => $approval_count,
            'overdue_count' => $overdue_count,
            'graph_objectives' => [
                'total' => $objectives->clone()->count(),
                'not-started' => $objectives->clone()->where('status', '=', ObjectiveStatus::NOT_STARTED)->count(),
                'on-track' => $objectives->clone()->where('status', '=', ObjectiveStatus::ON_TRACK)->count(),
                'behind' => $objectives->clone()->where('status', '=', ObjectiveStatus::BEHIND)->count(),
                'overdue' => $objectives->clone()->where('status', '=', ObjectiveStatus::OVERDUE)->count(),
                'complete' => $objectives->clone()->where('status', '=', ObjectiveStatus::COMPLETE)->count(),
            ],
            'graph_projects' => [
                'total' => $projects->clone()->count(),
                'not-started' => $projects->clone()->where('status', '=', ProjectStatus::NOT_STARTED)->count(),
                'on-track' => $projects->clone()->where('status', '=', ProjectStatus::ON_TRACK)->count(),
                'behind' => $projects->clone()->where('status', '=', ProjectStatus::BEHIND)->count(),
                'overdue' => $projects->clone()->where('status', '=', ProjectStatus::OVERDUE)->count(),
                'complete' => $projects->clone()->where('status', '=', ProjectStatus::COMPLETE)->count(),
            ],
            'graph_tasks' => [
                'total' => $tasks->clone()->count(),
                'not-started' => $tasks->clone()->where('status', '=', TaskStatus::NOT_STARTED)->count(),
                'on-track' => $tasks->clone()->where('status', '=', TaskStatus::ON_TRACK)->count(),
                'behind' => $tasks->clone()->where('status', '=', TaskStatus::BEHIND)->count(),
                'overdue' => $tasks->clone()->where('status', '=', TaskStatus::OVERDUE)->count(),
                'complete' => $tasks->clone()->where('status', '=', TaskStatus::COMPLETE)->count(),
            ],
            'roadmap' => $user->unreadRoadmap(),
        ];
    }

    /**
     * Get calendar tab specific data.
     */
    private function getCalendarData(Request $request, Organisation $organisation, User $user): array
    {
        $sessions = $this->getEngaugeSessions($user);

        return [
            'events' => $sessions,
        ];
    }

    /**
     * Get indicators tab specific data.
     */
    private function getIndicatorsData(Request $request, Organisation $organisation, User $user): array
    {
        $beforeDate = Carbon::now()->endOfMonth();
        $programmeSeat = $user->currentProgrammeSeat($organisation);

        if (! $programmeSeat) {
            Log::warning('No programme seat found for user', [
                'user_id' => $user->id,
                'organisation_id' => $organisation->id,
            ]);

            return $this->fallbackIndicatorData();
        }

        try {
            $dashboardInterface = $this->indicatorEntrepreneurDashboardFacadeFactory->create($programmeSeat);

            $eagerData = [
                'successIndicators' => $dashboardInterface->getSuccessIndicatorList($beforeDate),
                'complianceIndicators' => $dashboardInterface->getComplianceIndicatorList($beforeDate),
                'programmeStartDate' => $programmeSeat?->contract_start_date ?? '',
            ];

            $deferredData = [
                'successIndicatorsDashboard' => Inertia::defer(fn () => $dashboardInterface->getSuccessIndicatorSummaryTableData(), 'indicator_dashboards'),
                'complianceIndicatorsDashboard' => Inertia::defer(fn () => $dashboardInterface->getComplianceIndicatorSummaryTableData(), 'indicator_dashboards'),
                'learningAttendance' => Inertia::defer(fn () => $dashboardInterface->getLearningAttendanceStats(), 'attendance_stats'),
                'mentoringAttendance' => Inertia::defer(fn () => $dashboardInterface->getMentoringAttendanceStats(), 'attendance_stats'),
                'elementProgressStats' => Inertia::defer(fn () => $dashboardInterface->getElementProgressStats(), 'element_progress_stats'),
            ];

            return array_merge($eagerData, $deferredData);
        } catch (\Exception $e) {
            Log::error('Error fetching indicator data for tab view', [
                'user_id' => $user->id,
                'organisation_id' => $organisation->id,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackIndicatorData();
        }
    }

    /**
     * Fallback data for indicators tab when no programme seat is found.
     */
    private function fallbackIndicatorData(): array
    {
        return [
            'successIndicators' => [
                'open' => [],
                'verifying' => [],
                'complete' => [],
            ],
            'complianceIndicators' => [
                'open' => [],
                'verifying' => [],
                'complete' => [],
            ],
            'programmeStartDate' => '',
            'successIndicatorsDashboard' => [
                'indicators' => [],
                'programmeMonths' => [],
                'currentMonth' => null,
                'programmeDuration' => 0,
            ],
            'complianceIndicatorsDashboard' => [
                'indicators' => [],
                'programmeMonths' => [],
                'currentMonth' => null,
                'programmeDuration' => 0,
            ],
            'learningAttendance' => null,
            'mentoringAttendance' => null,
            'elementProgressStats' => null,
        ];
    }
}
