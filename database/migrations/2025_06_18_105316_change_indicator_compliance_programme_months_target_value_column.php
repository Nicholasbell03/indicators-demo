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
        Schema::table('indicator_compliance_programme_months', function (Blueprint $table) {
            $table->string('target_value')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indicator_compliance_programme_months', function (Blueprint $table) {
            DB::table('indicator_compliance_programme_months')
                ->whereNull('target_value')
                ->update(['target_value' => '']);

            $table->string('target_value')->nullable(false)->change();
        });
    }
};
