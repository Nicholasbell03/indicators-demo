<?php

namespace Database\Factories;

use App\Models\IndicatorSubmission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IndicatorReviewTask>
 */
class IndicatorReviewTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $submission = IndicatorSubmission::factory()->create();
        $submission->load('task');
        $task = $submission->task;
        $task->load('indicatable');

        $level = $this->faker->randomElement([1, 2]);
        if ($level === 1) {
            $verifierRole = $task->indicatable->verifier1Role;
        } else {
            $verifierRole = $task->indicatable->verifier2Role;
        }

        $verifierUser = User::factory()->withRole($verifierRole)->create();

        return [
            'indicator_submission_id' => $submission->id,
            'indicator_task_id' => $task->id,
            'verifier_user_id' => $verifierUser->id,
            'verifier_role_id' => $verifierRole->id,
            'verifier_level' => $level,
            // Use finalized design config key
            'due_date' => now()->addDays(config('success-compliance-indicators.review_task_days'))->format('Y-m-d'),
            'completed_at' => null,
        ];
    }

    public function forSubmission(IndicatorSubmission $submission)
    {
        return $this->state(function (array $attributes) use ($submission) {
            return [
                'indicator_submission_id' => $submission->id,
                'indicator_task_id' => $submission->task->id,
            ];
        });
    }

    public function level1()
    {
        return $this->state(function (array $attributes) {
            $submission = IndicatorSubmission::factory()->create();
            $submission->load('task');
            $task = $submission->task;
            $task->load('indicatable');

            $verifier1Role = $task->indicatable->verifier1Role;
            $verifier1User = User::factory()->withRole($verifier1Role)->create();

            return [
                'verifier_level' => 1,
                'verifier_user_id' => $verifier1User->id,
                'verifier_role_id' => $verifier1Role->id,
            ];
        });
    }

    public function level2()
    {
        return $this->state(function (array $attributes) {
            $submission = IndicatorSubmission::factory()->create();
            $submission->load('task');
            $task = $submission->task;
            $task->load('indicatable');

            $verifier2Role = $task->indicatable->verifier2Role;
            $verifier2User = User::factory()->withRole($verifier2Role)->create();

            return [
                'verifier_level' => 2,
                'verifier_user_id' => $verifier2User->id,
                'verifier_role_id' => $verifier2Role->id,
            ];
        });
    }

    public function completed()
    {
        return $this->state(function (array $attributes) {
            return [
                'completed_at' => now(),
            ];
        });
    }

    public function overdue()
    {
        return $this->state(function (array $attributes) {
            return [
                'due_date' => now()->subDays(7)->format('Y-m-d'),
            ];
        });
    }

    public function forUser(int $userId)
    {
        return $this->state(function (array $attributes) use ($userId) {
            $user = User::find($userId);
            if (! $user) {
                throw new \Exception("User with ID {$userId} not found");
            }

            return [
                'verifier_user_id' => $userId,
            ];
        });
    }

    public function forRole(int $roleId)
    {
        return $this->state(function (array $attributes) use ($roleId) {
            $role = Role::find($roleId);
            if (! $role) {
                throw new \Exception("Role with ID {$roleId} not found");
            }

            return [
                'verifier_role_id' => $roleId,
            ];
        });
    }

    public function forLevel(int $level)
    {
        return $this->state(function (array $attributes) use ($level) {
            if ($level !== 1 && $level !== 2) {
                throw new \Exception("Invalid level {$level}");
            }

            return [
                'verifier_level' => $level,
            ];
        });
    }
}
