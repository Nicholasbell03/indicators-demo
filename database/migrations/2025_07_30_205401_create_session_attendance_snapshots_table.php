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
        Schema::create('session_attendance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('programme_id')->constrained('programmes');
            $table->unsignedBigInteger('organisation_programme_seat_id')->nullable();
            $table->foreign('organisation_programme_seat_id', 'prog_seat_foreign')->references('id')->on('organisation_programme_seats')->nullOnDelete();
            $table->date('contract_start_date')->nullable();
            $table->date('ignition_date')->nullable();
            $table->float('attendance_percentage');
            $table->smallInteger('sessions_attended');
            $table->smallInteger('sessions_missed');
            $table->smallInteger('sessions_not_marked');
            $table->smallInteger('sessions_total');
            $table->json('meta')->nullable()->comment('JSON array of session details');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_attendance_snapshots');
    }
};
