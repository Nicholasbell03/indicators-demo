<?php

namespace App\Console\Commands;

use App\Enums\IndicatorComplianceTypeEnum;
use App\Enums\IndicatorProgrammeStatusEnum;
use App\Enums\IndicatorSubmissionStatusEnum;
use App\Enums\IndicatorTaskStatusEnum;
use App\Models\IndicatorCompliance;
use App\Models\IndicatorComplianceProgramme;
use App\Models\IndicatorComplianceProgrammeMonth;
use App\Models\IndicatorReviewTask;
use App\Models\IndicatorSubmissionReview;
use App\Models\IndicatorSuccess;
use App\Models\IndicatorSuccessProgramme;
use App\Models\IndicatorSuccessProgrammeMonth;
use App\Models\IndicatorTask;
use App\Models\Organisation;
use App\Models\OrganisationProgrammeSeat;
use App\Models\Programme;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class SeedIndicatorData extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:indicator-data
                            {--tenant=1 : Specific tenant ID to use}
                            {--user-id= : Specific user ID to create indicators for}
                            {--organisation-id= : Specific organisation ID to use}
                            {--programme-id= : Specific programme ID to use}
                            {--success-count=10 : Number of success indicator tasks to create}
                            {--compliance-count=10 : Number of compliance indicator tasks to create}
                            {--mixed-statuses : Create tasks with mixed statuses (pending, submitted, approved)}
                            {--dry-run : Show what would be created without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed indicator data for testing the indicators tab UI';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Production environment failsafe
        if (! $this->checkProductionSafety()) {
            return 1;
        }

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting indicator data seeding...');

        try {
            DB::beginTransaction();

            // Get or create user
            $user = $this->getUser();
            if (! $user) {
                $this->error('No user found. Please ensure you have at least one user in the system.');

                return 1;
            }

            $this->info("Using user: {$user->name} (ID: {$user->id})");

            // Get or create organisation
            $organisation = $this->getOrganisation();
            if (! $organisation) {
                $this->error('No organisation found. Please ensure you have at least one organisation in the system.');

                return 1;
            }

            $this->info("Using organisation: {$organisation->name} (ID: {$organisation->id})");

            // Ensure user is member of organisation
            if (! $user->organisations()->where('organisation_id', $organisation->id)->exists()) {
                if (! $dryRun) {
                    $user->organisations()->attach($organisation);
                }
                $this->info('Attached user to organisation');
            }

            // Get or create programme
            $programme = $this->getProgramme();
            if (! $programme) {
                $this->error('No programme found. Please ensure you have at least one programme in the system.');

                return 1;
            }

            $this->info("Using programme: {$programme->title} (ID: {$programme->id})");

            // Create programme seat for user (this is crucial for currentProgramme() to work)
            $programmeSeat = $this->createProgrammeSeat($user, $organisation, $programme, $dryRun);
            $this->info('Created programme seat for user');

            // Get current tenant
            $tenant = app('currentTenant') ?? Tenant::first();
            if (! $tenant) {
                $this->error('No tenant found. Please ensure you have at least one tenant in the system.');

                return 1;
            }
            $clusterId = $tenant->tenant_cluster_id;

            if (! $clusterId) {
                $this->error('No cluster ID found. Please ensure you use a tenant that has a cluster.');

                return 1;
            }

            // Create success indicator tasks
            $successCount = (int) $this->option('success-count');
            $successTasks = $this->createSuccessIndicatorTasks($user, $organisation, $programme, $tenant, $successCount, $dryRun);
            $this->info("Created {$successCount} success indicator tasks");

            // Create compliance indicator tasks
            $complianceCount = (int) $this->option('compliance-count');
            $complianceTasks = $this->createComplianceIndicatorTasks($user, $organisation, $programme, $tenant, $complianceCount, $dryRun);
            $this->info("Created {$complianceCount} compliance indicator tasks");

            $this->seedSubmissions(array_merge($successTasks, $complianceTasks), $dryRun, $user);
            $this->info('Created indicator submissions based on task statuses');

            if (! $dryRun) {
                DB::commit();
                $this->info('✅ Indicator data seeding completed successfully!');
                $this->line('');
                $this->line('You can now:');
                $this->line("1. Log in as user: {$user->email}");
                $this->line("2. Navigate to organisation: {$organisation->name}");
                $this->line('3. View the indicators tab in the dashboard');
            } else {
                DB::rollBack();
                $this->info('✅ Dry run completed - no changes made');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error seeding indicator data: {$e->getMessage()}");
            if (config('app.debug')) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        }

        return 0;
    }

    /**
     * Check if it's safe to run in production and handle accordingly
     */
    private function checkProductionSafety(): bool
    {
        if (app()->environment('production')) {
            $this->error('⚠️  WARNING: You are about to run a seeding command in PRODUCTION!');
            $this->error('This command creates test data and should typically only be used in development/testing environments.');

            return false;
        }

        return true;
    }

    private function getUser(): ?User
    {
        $userId = $this->option('user-id');

        // If no user ID provided, prompt for it
        if (! $userId) {
            $userId = $this->ask('Enter the user ID you want to use (or press Enter to create a new user)');
        }

        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User with ID {$userId} not found");

                return null;
            }

            return $user;
        }

        return null;
    }

    private function getOrganisation(): ?Organisation
    {
        $organisationId = $this->option('organisation-id');

        // If no organisation ID provided, prompt for it
        if (! $organisationId) {
            $organisationId = $this->ask('Enter the organisation ID you want to use (or press Enter to create a new organisation)');
        }

        if ($organisationId) {
            $organisation = Organisation::find($organisationId);
            if (! $organisation) {
                $this->error("Organisation with ID {$organisationId} not found");

                return null;
            }

            return $organisation;
        }

        return null;
    }

    private function getProgramme(): ?Programme
    {
        $programmeId = $this->option('programme-id');

        // If no programme ID provided, prompt for it
        if (! $programmeId) {
            $programmeId = $this->ask('Enter the programme ID you want to use (or press Enter to create a new programme)');
        }

        if ($programmeId) {
            $programme = Programme::find($programmeId);
            if (! $programme) {
                $this->error("Programme with ID {$programmeId} not found");

                return null;
            }

            return $programme;
        }

        return null;
    }

    private function createProgrammeSeat(User $user, Organisation $organisation, Programme $programme, bool $dryRun): ?OrganisationProgrammeSeat
    {
        if ($dryRun) {
            return null;
        }

        $tenant = app('currentTenant') ?? Tenant::first();

        return OrganisationProgrammeSeat::updateOrCreate([
            'user_id' => $user->id,
            'organisation_id' => $organisation->id,
            'programme_id' => $programme->id,
            'tenant_id' => $tenant->id,
        ], [
            'is_active' => true,
            'contract_start_date' => now()->subMonths(2),
            'ignition_date' => now()->subMonths(1),
        ]);
    }

    private function createSuccessIndicatorTasks(User $user, Organisation $organisation, Programme $programme, Tenant $tenant, int $count, bool $dryRun): array
    {
        $tasks = [];

        for ($i = 1; $i <= $count; $i++) {
            if ($dryRun) {
                $this->line("Would create success indicator task {$i}");

                continue;
            }

            // Create indicator success
            $indicatorSuccess = IndicatorSuccess::factory()->create([
                'title' => "Success Indicator {$i}",
                'description' => "Test success indicator {$i} for dashboard",
                'tenant_portfolio_id' => null,
                'tenant_cluster_id' => $tenant->tenant_cluster_id,
            ]);

            // Create the programme relationship
            $indicatorSuccessProgramme = IndicatorSuccessProgramme::firstOrCreate([
                'indicator_success_id' => $indicatorSuccess->id,
                'programme_id' => $programme->id,
                'status' => IndicatorProgrammeStatusEnum::PUBLISHED->value,
            ]);

            // Create programme month
            $indicatorSuccessProgrammeMonth = IndicatorSuccessProgrammeMonth::firstOrCreate([
                'indicator_success_programme_id' => $indicatorSuccessProgramme->id,
                'programme_month' => $i, // Different month for each task
            ]);

            // Create indicator task
            $status = $this->getTaskStatus($i);
            $task = IndicatorTask::firstOrCreate([
                'entrepreneur_id' => $user->id,
                'organisation_id' => $organisation->id,
                'programme_id' => $programme->id,
                'indicatable_month_id' => $indicatorSuccessProgrammeMonth->id,
                'indicatable_month_type' => $indicatorSuccessProgrammeMonth->getMorphClass(),
                'indicatable_type' => $indicatorSuccessProgrammeMonth->indicator->getMorphClass(),
                'indicatable_id' => $indicatorSuccessProgrammeMonth->indicator->id,
                'responsible_type' => 'user',
                'responsible_role_id' => $this->getEntrepreneurRole()->id,
                'responsible_user_id' => $user->id,
                'due_date' => now()->addWeeks($i),
                'status' => $status,
            ]);

            $tasks[] = $task;
        }

        return $tasks;
    }

    private function createComplianceIndicatorTasks(User $user, Organisation $organisation, Programme $programme, Tenant $tenant, int $count, bool $dryRun): array
    {
        $tasks = [];

        for ($i = 1; $i <= $count; $i++) {
            if ($dryRun) {
                $this->line("Would create compliance indicator task {$i}");

                continue;
            }

            // Create indicator compliance
            $indicatorCompliance = IndicatorCompliance::factory()->create([
                'title' => "Compliance Indicator {$i}",
                'description' => "Test compliance indicator {$i} for dashboard",
                'tenant_portfolio_id' => null,
                'tenant_cluster_id' => $tenant->tenant_cluster_id,
                'responsible_role_id' => $this->getEntrepreneurRole()->id,
            ]);

            // Create the programme relationship
            $indicatorComplianceProgramme = IndicatorComplianceProgramme::firstOrCreate([
                'indicator_compliance_id' => $indicatorCompliance->id,
                'programme_id' => $programme->id,
                'status' => IndicatorProgrammeStatusEnum::PUBLISHED->value,
            ]);

            $programmeDuration = $programme->period;

            if ($indicatorCompliance->type === IndicatorComplianceTypeEnum::ATTENDANCE_LEARNING || $indicatorCompliance->type === IndicatorComplianceTypeEnum::ATTENDANCE_MENTORING) {
                $programmeMonths = range(1, $programmeDuration);
            } else {
                $programmeMonths = [$i];
            }

            foreach ($programmeMonths as $month) {
                // Create programme month
                $indicatorComplianceProgrammeMonth = IndicatorComplianceProgrammeMonth::firstOrCreate([
                    'indicator_compliance_programme_id' => $indicatorComplianceProgramme->id,
                    'programme_month' => $month, // Different month for each task
                    'target_value' => '85', // Example target
                ]);
            }

            // Create indicator task
            $status = $this->getTaskStatus($i);
            $task = IndicatorTask::firstOrCreate([
                'entrepreneur_id' => $user->id,
                'organisation_id' => $organisation->id,
                'programme_id' => $programme->id,
                'indicatable_month_id' => $indicatorComplianceProgrammeMonth->id,
                'indicatable_month_type' => $indicatorComplianceProgrammeMonth->getMorphClass(),
                'indicatable_type' => $indicatorComplianceProgrammeMonth->indicator->getMorphClass(),
                'indicatable_id' => $indicatorComplianceProgrammeMonth->indicator->id,
                'responsible_type' => 'user',
                'responsible_role_id' => $this->getEntrepreneurRole()->id,
                'responsible_user_id' => $user->id,
                'due_date' => now()->addWeeks($i + 2),
                'status' => $status,
            ]);

            $tasks[] = $task;
        }

        return $tasks;
    }

    private function getTaskStatus(int $index): IndicatorTaskStatusEnum
    {
        if (! $this->option('mixed-statuses')) {
            return IndicatorTaskStatusEnum::PENDING;
        }

        // Create a mix of statuses
        $statuses = [
            IndicatorTaskStatusEnum::PENDING,
            IndicatorTaskStatusEnum::SUBMITTED,
            IndicatorTaskStatusEnum::COMPLETED,
            IndicatorTaskStatusEnum::NEEDS_REVISION,
        ];

        return $statuses[$index % count($statuses)];
    }

    private function seedSubmissions(array $tasks, bool $dryRun, User $user): void
    {
        if ($dryRun) {
            $this->line('Would create submissions and reviews for tasks based on their statuses');

            return;
        }

        foreach ($tasks as $task) {
            if ($task->status === IndicatorTaskStatusEnum::PENDING) {
                continue;
            }

            $submission = $task->submissions()->create([
                'value' => fake()->numberBetween(1000, 10000),
                'comment' => 'Test submission created by seeder',
                'is_achieved' => fake()->boolean(),
                'submitter_id' => $task->entrepreneur_id,
                'submitted_at' => now()->subDays(rand(1, 30)),
            ]);

            if ($task->status === IndicatorTaskStatusEnum::SUBMITTED) {
                $submission->status = IndicatorSubmissionStatusEnum::PENDING_VERIFICATION_1;
            } elseif ($task->status === IndicatorTaskStatusEnum::COMPLETED) {
                $submission->status = IndicatorSubmissionStatusEnum::APPROVED;
                $reviewTask = IndicatorReviewTask::create([
                    'indicator_submission_id' => $submission->id,
                    'indicator_task_id' => $task->id,
                    'verifier_user_id' => $user->id,
                    'verifier_role_id' => $this->getEntrepreneurRole()->id,
                    'verifier_level' => 1,
                    'due_date' => now()->addDays(config('success-compliance-indicators.review_task_days')),
                ]);

                IndicatorSubmissionReview::create([
                    'indicator_submission_id' => $submission->id,
                    'indicator_review_task_id' => $reviewTask->id,
                    'reviewer_id' => $user->id,
                    'approved' => true,
                    'verifier_level' => 1,
                    'comment' => 'Test approval comment',
                ]);
            } elseif ($task->status === IndicatorTaskStatusEnum::NEEDS_REVISION) {
                $submission->status = IndicatorSubmissionStatusEnum::REJECTED;
                $reviewTask = IndicatorReviewTask::create([
                    'indicator_submission_id' => $submission->id,
                    'indicator_task_id' => $task->id,
                    'verifier_user_id' => $user->id,
                    'verifier_role_id' => $this->getEntrepreneurRole()->id,
                    'verifier_level' => 1,
                    'due_date' => now()->addDays(config('success-compliance-indicators.review_task_days')),
                ]);
                IndicatorSubmissionReview::create([
                    'indicator_submission_id' => $submission->id,
                    'indicator_review_task_id' => $reviewTask->id,
                    'reviewer_id' => $user->id,
                    'approved' => false,
                    'verifier_level' => 1,
                    'comment' => 'Test rejection comment, please revise',
                ]);
            }

            $submission->save();
        }
    }

    private function getEntrepreneurRole(): Role
    {
        return Role::where('name', 'Entrepreneur')->first() ?? Role::first();
    }
}
