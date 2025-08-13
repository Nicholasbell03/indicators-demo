<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicator_review_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('verifier_user_id')->nullable()->constrained('users')->nullOnDelete()->change();
            $table->unsignedBigInteger('verifier_role_id')->nullable()->constrained('roles')->nullOnDelete()->change();

            // Ensure uniqueness of submission + level
            $table->unique(['indicator_submission_id', 'verifier_level'], 'unique_submission_review_task');
        });
    }

    public function down(): void
    {
        Schema::table('indicator_review_tasks', function (Blueprint $table) {
            $table->dropUnique('unique_submission_review_task');
            $table->unsignedBigInteger('verifier_user_id')->nullable(false)->change();
            $table->unsignedBigInteger('verifier_role_id')->nullable(false)->change();
        });
    }
};
