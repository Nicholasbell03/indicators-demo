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
        Schema::create('indicator_success_programme_months', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('indicator_success_programme_id');
            $table->foreign('indicator_success_programme_id', 'isp_indicator_success_programme_id_foreign')
                ->references('id')
                ->on('indicator_success_programme')
                ->onDelete('cascade');

            $table->unsignedTinyInteger('programme_month');

            $table->timestamps();

            $table->unique(['indicator_success_programme_id', 'programme_month'], 'isp_months_unique');
            $table->index(['programme_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_success_programme_months');
    }
};
