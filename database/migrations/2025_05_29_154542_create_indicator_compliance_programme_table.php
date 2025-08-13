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
        Schema::create('indicator_compliance_programme', function (Blueprint $table) {
            $table->id();

            $table->foreignId('indicator_compliance_id')
                ->constrained('indicator_compliances')
                ->onDelete('cascade');

            $table->foreignId('programme_id')
                ->constrained('programmes')
                ->onDelete('cascade');

            $table->string('status')->default(IndicatorProgrammeStatusEnum::PENDING->value);

            $table->timestamps();

            $table->unique(['indicator_compliance_id', 'programme_id'], 'icp_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_compliance_programme');
    }
};
