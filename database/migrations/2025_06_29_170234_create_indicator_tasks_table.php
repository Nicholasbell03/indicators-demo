<?php

use App\Enums\IndicatorTaskStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrepreneur_id')->comment('The entrepreneur this task is for.')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('programme_id')->comment('The programme context for this task.')->constrained('programmes')->cascadeOnDelete();
            $table->morphs('indicatable_month', 'indicatable_month_index');

            $table->enum('responsible_type', ['user', 'system'])->index();
            $table->foreignId('responsible_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('due_date');
            $table->enum('status', array_column(IndicatorTaskStatusEnum::cases(), 'value'))->default(IndicatorTaskStatusEnum::PENDING->value)->index();
            $table->boolean('is_achieved')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_tasks');
    }
};
