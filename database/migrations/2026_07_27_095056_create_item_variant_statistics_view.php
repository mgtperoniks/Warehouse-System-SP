<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement("
                CREATE VIEW item_variant_statistics AS
                SELECT 
                    iv.id AS item_variant_id,
                    iv.erp_code,
                    COALESCE((
                        SELECT SUM(sm.qty) 
                        FROM stock_movements sm 
                        WHERE sm.item_variant_id = iv.id 
                          AND sm.type = 'OUT' 
                          AND sm.created_at >= datetime('now', '-28 days')
                    ), 0) / 4.0 AS weekly_average,
                    COALESCE((
                        SELECT SUM(sm.qty) 
                        FROM stock_movements sm 
                        WHERE sm.item_variant_id = iv.id 
                          AND sm.type = 'OUT' 
                          AND sm.created_at >= datetime('now', '-90 days')
                    ), 0) / 3.0 AS monthly_average,
                    COALESCE((
                        SELECT SUM(sm.qty) 
                        FROM stock_movements sm 
                        WHERE sm.item_variant_id = iv.id 
                          AND sm.type = 'OUT' 
                          AND sm.created_at >= datetime('now', '-180 days')
                    ), 0) / 6.0 AS six_month_average,
                    CASE 
                        WHEN (COALESCE((
                            SELECT SUM(sm.qty) 
                            FROM stock_movements sm 
                            WHERE sm.item_variant_id = iv.id 
                              AND sm.type = 'OUT' 
                              AND sm.created_at >= datetime('now', '-28 days')
                        ), 0) / 4.0) >= (COALESCE((
                            SELECT SUM(sm.qty) 
                            FROM stock_movements sm 
                            WHERE sm.item_variant_id = iv.id 
                              AND sm.type = 'OUT' 
                              AND sm.created_at >= datetime('now', '-90 days')
                        ), 0) / 3.0) * 1.20 THEN 'Increasing'
                        WHEN (COALESCE((
                            SELECT SUM(sm.qty) 
                            FROM stock_movements sm 
                            WHERE sm.item_variant_id = iv.id 
                              AND sm.type = 'OUT' 
                              AND sm.created_at >= datetime('now', '-28 days')
                        ), 0) / 4.0) <= (COALESCE((
                            SELECT SUM(sm.qty) 
                            FROM stock_movements sm 
                            WHERE sm.item_variant_id = iv.id 
                              AND sm.type = 'OUT' 
                              AND sm.created_at >= datetime('now', '-90 days')
                        ), 0) / 3.0) * 0.80 THEN 'Decreasing'
                        ELSE 'Stable'
                    END AS trend
                FROM item_variants iv;
            ");
        } else {
            DB::statement("
                CREATE OR REPLACE VIEW item_variant_statistics AS
                SELECT 
                    iv.id AS item_variant_id,
                    iv.erp_code,
                    COALESCE((
                        SELECT SUM(sm.qty) 
                        FROM stock_movements sm 
                        WHERE sm.item_variant_id = iv.id 
                          AND sm.type = 'OUT' 
                          AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 28 DAY)
                    ), 0) / 4.0 AS weekly_average,
                    COALESCE((
                        SELECT SUM(sm.qty) 
                        FROM stock_movements sm 
                        WHERE sm.item_variant_id = iv.id 
                          AND sm.type = 'OUT' 
                          AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    ), 0) / 3.0 AS monthly_average,
                    COALESCE((
                        SELECT SUM(sm.qty) 
                        FROM stock_movements sm 
                        WHERE sm.item_variant_id = iv.id 
                          AND sm.type = 'OUT' 
                          AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
                    ), 0) / 6.0 AS six_month_average,
                    CASE 
                        WHEN (COALESCE((
                            SELECT SUM(sm.qty) 
                            FROM stock_movements sm 
                            WHERE sm.item_variant_id = iv.id 
                              AND sm.type = 'OUT' 
                              AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 28 DAY)
                        ), 0) / 4.0) >= (COALESCE((
                            SELECT SUM(sm.qty) 
                            FROM stock_movements sm 
                            WHERE sm.item_variant_id = iv.id 
                              AND sm.type = 'OUT' 
                              AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                        ), 0) / 3.0) * 1.20 THEN 'Increasing'
                        WHEN (COALESCE((
                            SELECT SUM(sm.qty) 
                            FROM stock_movements sm 
                            WHERE sm.item_variant_id = iv.id 
                              AND sm.type = 'OUT' 
                              AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 28 DAY)
                        ), 0) / 4.0) <= (COALESCE((
                            SELECT SUM(sm.qty) 
                            FROM stock_movements sm 
                            WHERE sm.item_variant_id = iv.id 
                              AND sm.type = 'OUT' 
                              AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                        ), 0) / 3.0) * 0.80 THEN 'Decreasing'
                        ELSE 'Stable'
                    END AS trend
                FROM item_variants iv;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS item_variant_statistics;");
    }
};

