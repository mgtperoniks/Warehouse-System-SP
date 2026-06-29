<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->string('erp_transfer_status')->default('NOT_STARTED');
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transferred_at')->nullable();
            
            $table->index(['stock_in_receipt_id', 'erp_transfer_status'], 'idx_sii_receipt_erp_status');
        });

        // Historical backfill:
        // - Parent status COMPLETED -> Child items mapped to COMPLETED with parent audit metadata
        // - Every other existing parent value (NOT_STARTED, PENDING, IN_PROGRESS, NULL, etc.) -> Child items mapped to NOT_STARTED (default)
        DB::statement("
            UPDATE stock_in_items
            JOIN stock_in_receipts ON stock_in_items.stock_in_receipt_id = stock_in_receipts.id
            SET stock_in_items.erp_transfer_status = 'COMPLETED',
                stock_in_items.transferred_by = stock_in_receipts.transferred_by,
                stock_in_items.transferred_at = stock_in_receipts.transferred_at
            WHERE stock_in_receipts.erp_transfer_status = 'COMPLETED'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->dropForeign(['transferred_by']);
            $table->dropIndex('idx_sii_receipt_erp_status');
            $table->dropColumn(['erp_transfer_status', 'transferred_by', 'transferred_at']);
        });
    }
};
