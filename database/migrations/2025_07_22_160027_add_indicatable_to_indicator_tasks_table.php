<?php

use App\Models\IndicatorTask;
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
        Schema::table('indicator_tasks', function (Blueprint $table) {
            $table->morphs('indicatable', 'indicatable_index');
        });

        IndicatorTask::with(['indicatableMonth.indicator'])
            ->chunkById(100, function ($tasks) {
                foreach ($tasks as $task) {
                    if ($task->indicatableMonth?->indicator) {
                        $task->update([
                            'indicatable_type' => $task->indicatableMonth->indicator->getMorphClass(),
                            'indicatable_id' => $task->indicatableMonth->indicator->id,
                        ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indicator_tasks', function (Blueprint $table) {
            $table->dropMorphs('indicatable', 'indicatable_index');
        });
    }
};
