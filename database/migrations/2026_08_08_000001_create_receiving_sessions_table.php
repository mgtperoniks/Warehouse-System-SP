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
        Schema::create('receiving_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->foreignId('outstanding_purchase_order_id')->constrained('outstanding_purchase_orders')->onDelete('cascade');
            $table->string('status')->default('DRAFT'); // DRAFT, READY_REVIEW, COMPLETED, CANCELLED
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving_session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_session_id')->constrained('receiving_sessions')->onDelete('cascade');
            $table->foreignId('outstanding_purchase_order_item_id')
                  ->constrained('outstanding_purchase_order_items', 'id', 'rs_items_po_item_id_foreign')
                  ->onDelete('cascade');
            $table->foreignId('item_variant_id')
                  ->nullable()
                  ->constrained('item_variants')
                  ->nullOnDelete();
            $table->integer('expected_qty');
            $table->integer('received_qty')->default(0);
            $table->string('verification_status')->default('PENDING'); // PENDING, VERIFIED, REMOVED
            $table->string('removed_reason')->nullable(); // WRONG WAREHOUSE, IMPORTED BY MISTAKE, CANCELLED, OTHER
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiving_session_items');
        Schema::dropIfExists('receiving_sessions');
    }
};
