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
        Schema::create('indicator_compliances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_portfolio_id')
                ->nullable()
                ->constrained('tenant_portfolios')
                ->nullOnDelete();

            $table->foreignId('tenant_cluster_id')
                ->nullable()
                ->constrained('tenant_clusters')
                ->nullOnDelete();

            $table->string('level');

            $table->string('type');

            $table->foreignId('responsible_role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();

            $table->string('title');

            $table->string('slug');

            $table->string('description');

            $table->text('additional_instruction')->nullable();

            $table->string('response_format');

            $table->string('currency', 3)->nullable();

            $table->string('target_value')->nullable();

            $table->string('acceptance_value')->nullable();

            $table->text('supporting_documentation')->nullable();

            $table->foreignId('verifier_1_role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();

            $table->foreignId('verifier_2_role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'tenant_portfolio_id', 'tenant_cluster_id'], 'indicator_compliance_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_compliances');
    }
};
