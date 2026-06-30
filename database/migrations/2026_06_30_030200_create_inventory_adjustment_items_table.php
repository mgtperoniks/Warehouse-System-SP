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
        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_variant_id')->constrained('item_variants')->cascadeOnDelete();
            $table->integer('system_qty');
            $table->integer('physical_qty');
            $table->integer('variance');
            $table->string('reason_code');
            $table->text('notes')->nullable();
            $table->string('status')->default('WAITING');
            
            // Approval tracking fields
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            
            // Rejection tracking fields
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('reject_reason')->nullable();

            // Snapshots for audit trails
            $table->string('item_name_snapshot');
            $table->string('erp_code_snapshot')->nullable();
            $table->string('bin_code_snapshot')->nullable();
            $table->string('unit_snapshot')->nullable();
            $table->string('warehouse_name_snapshot');
            $table->string('operator_name_snapshot');

            $table->timestamps();

            // Indexes for fast filtering and status aggregation
            $table->index('status');
            $table->index('inventory_adjustment_id');
            $table->index('item_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_items');
    }
};
