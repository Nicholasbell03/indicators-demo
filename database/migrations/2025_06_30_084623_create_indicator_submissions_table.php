<?php

use App\Enums\IndicatorSubmissionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_task_id')->constrained('indicator_tasks')->cascadeOnDelete();
            $table->text('value');
            $table->text('comment')->nullable();
            $table->boolean('is_achieved')->nullable()->index();
            $table->enum('status', array_column(IndicatorSubmissionStatusEnum::cases(), 'value'))->index();
            $table->foreignId('submitter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_submissions');
    }
};
