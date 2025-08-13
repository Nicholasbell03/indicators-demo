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
        Schema::create('indicator_compliance_programme_months', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('indicator_compliance_programme_id');
            $table->foreign('indicator_compliance_programme_id', 'icpm_indicator_compliance_programme_id_foreign')
                ->references('id')
                ->on('indicator_compliance_programme')
                ->onDelete('cascade');

            $table->unsignedTinyInteger('programme_month');
            $table->string('target_value');

            $table->timestamps();

            $table->unique(['indicator_compliance_programme_id', 'programme_month'], 'icpm_months_unique');
            $table->index(['programme_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_compliance_programme_months');
    }
};
