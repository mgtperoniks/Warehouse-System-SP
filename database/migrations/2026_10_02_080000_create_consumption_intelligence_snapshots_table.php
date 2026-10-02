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
        Schema::create('consumption_intelligence_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_variant_id')->constrained('item_variants')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('cascade');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedTinyInteger('valid_weeks_count')->default(8);
            $table->unsignedTinyInteger('active_weeks_count')->default(0);
            $table->unsignedInteger('total_transactions')->default(0);
            $table->unsignedInteger('total_consumption_qty')->default(0);
            $table->unsignedInteger('prior_period_qty')->default(0);
            $table->unsignedInteger('recent_period_qty')->default(0);
            $table->decimal('prior_avg', 10, 2)->default(0.00);
            $table->decimal('recent_avg', 10, 2)->default(0.00);
            $table->decimal('ols_slope', 10, 3)->default(0.000);
            $table->decimal('normalized_slope_pct', 8, 2)->default(0.00);
            $table->decimal('recent_growth_pct', 8, 2)->default(0.00);
            $table->decimal('volatility_cv', 8, 4)->default(0.0000);
            $table->decimal('stability_score', 6, 4)->default(1.0000);
            $table->decimal('confidence_score', 6, 4)->default(0.0000);
            $table->decimal('trend_signal', 6, 2)->default(50.00);
            $table->decimal('trend_index', 6, 2)->default(0.00);
            $table->string('classification', 50)->default('DORMANT');
            $table->string('primary_department_driver')->nullable();
            $table->string('top_requester_driver')->nullable();
            $table->json('weekly_series_json')->nullable();
            $table->json('attribution_json')->nullable();
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();

            // High performance analytical indexes
            $table->index('trend_index', 'idx_ci_trend_index');
            $table->index('classification', 'idx_ci_classification');
            $table->index('total_consumption_qty', 'idx_ci_total_qty');
            $table->index('recent_growth_pct', 'idx_ci_recent_growth');
            $table->index(['period_start', 'period_end', 'item_variant_id'], 'idx_ci_period_variant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumption_intelligence_snapshots');
    }
};
