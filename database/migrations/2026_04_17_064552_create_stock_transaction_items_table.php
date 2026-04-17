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
        Schema::create('stock_transaction_items', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transaction_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_variant_id')->constrained()->onDelete('cascade');
            $table->foreignId('bin_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('qty');
            
            // Snapshots for auditing
            $table->string('item_name_snapshot');
            $table->string('erp_code_snapshot')->nullable();
            $table->string('unit_snapshot')->nullable();
            $table->decimal('price_snapshot', 15, 2)->default(0);
            $table->decimal('total_price_snapshot', 15, 2)->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transaction_items');
    }
};
