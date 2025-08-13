<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('indicator_successes', function (Blueprint $table) {
            $table->string('description')->nullable()->change();
        });

        Schema::table('indicator_compliances', function (Blueprint $table) {
            $table->string('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('indicator_successes')
            ->whereNull('description')
            ->update(['description' => '']);

        DB::table('indicator_compliances')
            ->whereNull('description')
            ->update(['description' => '']);

        Schema::table('indicator_successes', function (Blueprint $table) {
            $table->string('description')->nullable(false)->change();
        });

        Schema::table('indicator_compliances', function (Blueprint $table) {
            $table->string('description')->nullable(false)->change();
        });
    }
};
