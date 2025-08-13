<?php

namespace App\Console\Commands;

use App\Models\IndicatorReviewTask;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class SeedIndicatorReviewTaskCommand extends Command
{
    protected $signature = 'seed:indicator-review-tasks
                            {--user-id= : Specific user ID to create review tasks for}
                            {--role-id= : Specific role ID for the verifier}
                            {--count=10 : Number of review tasks to create}
                            {--level=1 : Verifier level (1 or 2)}
                            {--completed : Create completed tasks}
                            {--overdue : Create overdue tasks}';

    protected $description = 'Seed indicator review tasks with optional parameters';

    public function handle(): int
    {
        // Production environment failsafe
        if (app()->environment('production')) {
            $this->error('This command cannot be run in production environment.');

            return self::FAILURE;
        }

        $userId = $this->option('user-id');
        $roleId = $this->option('role-id');
        $count = (int) $this->option('count');
        $level = (int) $this->option('level');
        $completed = $this->option('completed');
        $overdue = $this->option('overdue');

        // Interactive prompts for missing required parameters
        $userId = $this->promptForUserId($userId);
        if (! $userId) {
            return self::FAILURE;
        }

        $roleId = $this->promptForRoleId($roleId);
        if (! $roleId) {
            $user = User::find($userId);
            $roleId = $user->roles->first()->id;
            if (! $roleId) {
                $this->error('No role found for user');

                return self::FAILURE;
            }
        }

        // Validate level
        if (! in_array($level, [1, 2])) {
            $this->error('Level must be 1 or 2');

            return self::FAILURE;
        }

        $this->info("Creating {$count} indicator review tasks...");

        // Create the tasks
        try {
            // Start building the factory
            $tasks = IndicatorReviewTask::factory([
                'verifier_user_id' => $userId,
                'verifier_role_id' => $roleId,
                'verifier_level' => $level,
                'completed_at' => $completed ? now() : null,
                'due_date' => $overdue ? now()->subDays(7) : now()->addDays(7),
            ])->count($count)->create();

            $this->info("✅ Successfully created {$count} indicator review tasks");

            if ($this->output->isVerbose()) {
                $this->table(
                    ['ID', 'Verifier User', 'Role', 'Level', 'Due Date', 'Status'],
                    $tasks->map(fn ($task) => [
                        $task->id,
                        $task->verifierUser->name ?? 'N/A',
                        $task->verifierRole->name ?? 'N/A',
                        $task->verifier_level,
                        $task->due_date->format('Y-m-d'),
                        $task->completed_at ? 'Completed' : ($task->due_date->isPast() ? 'Overdue' : 'Pending'),
                    ])
                );
            }
        } catch (\Exception $e) {
            $this->error("Failed to create indicator review tasks: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function promptForUserId(?string $userId): ?int
    {
        if ($userId) {
            // Validate the provided user ID
            $user = User::find($userId);
            if (! $user) {
                $this->error("User with ID {$userId} not found.");

                return null;
            }

            return (int) $userId;
        }

        // No user ID provided, prompt for one
        $this->warn('No user ID provided.');
        $inputUserId = $this->ask('Enter user ID (or press enter to use random users)');

        if (! $inputUserId) {
            $this->info('Using random user assignment.');

            return null;
        }

        $user = User::find($inputUserId);
        if (! $user) {
            $this->error("User with ID {$inputUserId} not found.");

            return null;
        }

        return (int) $inputUserId;
    }

    private function promptForRoleId(?string $roleId): ?int
    {
        if ($roleId) {
            // Validate the provided role ID
            $role = Role::find($roleId);
            if (! $role) {
                $this->error("Role with ID {$roleId} not found.");

                return null;
            }

            return (int) $roleId;
        }

        // Role ID is optional, ask if they want to specify one
        if ($this->confirm('Would you like to specify a role ID for the verifier?', false)) {
            $inputRoleId = $this->ask('Enter role ID (or press enter to skip)');

            if ($inputRoleId) {
                $role = Role::find($inputRoleId);
                if (! $role) {
                    $this->error("Role with ID {$inputRoleId} not found.");

                    return null;
                }

                return (int) $inputRoleId;
            }
        }

        return null;
    }
}
