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
        Schema::create('indicator_submission_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_review_task_id')->constrained('indicator_review_tasks')->cascadeOnDelete();
            $table->foreignId('indicator_submission_id')->constrained('indicator_submissions')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('approved')->comment('Whether the submission was approved.')->index();
            $table->unsignedTinyInteger('verifier_level')->comment('Which verifier this was (1 or 2).')->index();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_submission_reviews');
    }
};
