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
        Schema::create('outstanding_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outstanding_purchase_order_id')
                  ->constrained('outstanding_purchase_orders', 'id', 'items_po_id_foreign')
                  ->onDelete('cascade');
            $table->foreignId('item_variant_id')
                  ->nullable()
                  ->constrained('item_variants', 'id', 'items_variant_id_foreign')
                  ->nullOnDelete();
            $table->string('erp_code');
            $table->string('item_name_snapshot');
            $table->integer('ordered_qty');
            $table->integer('received_qty')->default(0);
            $table->string('unit')->default('PCS');
            $table->integer('line_number')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outstanding_purchase_order_items');
    }
};
