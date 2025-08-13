<?php

use App\Enums\IndicatorProgrammeStatusEnum;
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
        Schema::create('indicator_success_programme', function (Blueprint $table) {
            $table->id();

            $table->foreignId('indicator_success_id')
                ->constrained('indicator_successes')
                ->onDelete('cascade');

            $table->foreignId('programme_id')
                ->constrained('programmes')
                ->onDelete('cascade');

            $table->string('status')->default(IndicatorProgrammeStatusEnum::PENDING->value);

            $table->timestamps();

            $table->unique(['indicator_success_id', 'programme_id'], 'isp_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_success_programme');
    }
};
