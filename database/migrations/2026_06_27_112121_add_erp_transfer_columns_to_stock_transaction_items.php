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
        Schema::table('stock_transaction_items', function (Blueprint $table) {
            $table->string('erp_transfer_status')->default('NOT_STARTED');
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transferred_at')->nullable();
            
            $table->index(['stock_transaction_id', 'erp_transfer_status'], 'idx_sti_tx_erp_status');
        });

        // Historical backfill:
        // - Parent status COMPLETED -> Child items mapped to COMPLETED with parent audit metadata
        // - Every other existing parent value (NOT_STARTED, PENDING, IN_PROGRESS, NULL, etc.) -> Child items mapped to NOT_STARTED (default)
        DB::statement("
            UPDATE stock_transaction_items
            JOIN stock_transactions ON stock_transaction_items.stock_transaction_id = stock_transactions.id
            SET stock_transaction_items.erp_transfer_status = 'COMPLETED',
                stock_transaction_items.transferred_by = stock_transactions.transferred_by,
                stock_transaction_items.transferred_at = stock_transactions.transferred_at
            WHERE stock_transactions.erp_transfer_status = 'COMPLETED'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transaction_items', function (Blueprint $table) {
            $table->dropForeign(['transferred_by']);
            $table->dropIndex('idx_sti_tx_erp_status');
            $table->dropColumn(['erp_transfer_status', 'transferred_by', 'transferred_at']);
        });
    }
};
