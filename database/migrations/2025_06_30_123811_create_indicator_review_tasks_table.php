<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('indicator_review_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_submission_id')->constrained('indicator_submissions')->cascadeOnDelete();
            $table->foreignId('indicator_task_id')->constrained('indicator_tasks')->cascadeOnDelete();

            $table->foreignId('verifier_user_id')->comment('The user assigned to this verification.')->constrained('users')->cascadeOnDelete();
            $table->foreignId('verifier_role_id')->comment('The role that qualified the user.')->constrained('roles')->cascadeOnDelete();

            $table->unsignedTinyInteger('verifier_level')->index();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_review_tasks');
    }
};
