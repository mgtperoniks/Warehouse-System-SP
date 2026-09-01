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
        // 1. Update outstanding_purchase_orders
        Schema::table('outstanding_purchase_orders', function (Blueprint $table) {
            $table->string('department_name')->nullable()->after('supplier_name_snapshot');
            $table->string('erp_sync_status')->default('IN_SYNC')->after('status');
        });

        // 2. Update outstanding_purchase_order_items
        Schema::table('outstanding_purchase_order_items', function (Blueprint $table) {
            $table->decimal('ordered_qty', 12, 3)->change();
            $table->decimal('received_qty', 12, 3)->default(0)->change();
            $table->string('department_name')->nullable()->after('item_name_snapshot');
            $table->decimal('erp_ordered_qty', 12, 3)->nullable()->after('received_qty');
            $table->decimal('erp_received_qty', 12, 3)->default(0)->after('erp_ordered_qty');
            $table->decimal('erp_outstanding_qty', 12, 3)->nullable()->after('erp_received_qty');
            $table->string('erp_sync_status')->default('IN_SYNC')->after('erp_outstanding_qty');
            $table->timestamp('erp_snapshot_at')->nullable()->after('erp_sync_status');
        });

        // 3. Update receiving_session_items
        Schema::table('receiving_session_items', function (Blueprint $table) {
            $table->decimal('expected_qty', 12, 3)->change();
            $table->decimal('received_qty', 12, 3)->default(0)->change();
            $table->decimal('qty_datang', 12, 3)->nullable()->after('expected_qty');
            $table->string('check_result')->nullable()->after('verification_status');
            $table->text('check_notes')->nullable()->after('check_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receiving_session_items', function (Blueprint $table) {
            $table->dropColumn(['qty_datang', 'check_result', 'check_notes']);
            $table->integer('expected_qty')->change();
            $table->integer('received_qty')->default(0)->change();
        });

        Schema::table('outstanding_purchase_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'department_name',
                'erp_ordered_qty',
                'erp_received_qty',
                'erp_outstanding_qty',
                'erp_sync_status',
                'erp_snapshot_at',
            ]);
            $table->integer('ordered_qty')->change();
            $table->integer('received_qty')->default(0)->change();
        });

        Schema::table('outstanding_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['department_name', 'erp_sync_status']);
        });
    }
};
