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
        Schema::create('outstanding_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->string('supplier_name_snapshot');
            $table->string('supplier_code_snapshot')->nullable();
            $table->string('po_number');
            $table->string('document_reference')->nullable();
            $table->date('po_date');
            $table->date('expected_date')->nullable();
            $table->integer('status')->default(1); // 1 = Pending, 2 = Partial, 3 = Closed
            $table->boolean('is_archived')->default(false);
            $table->string('source')->default('ERP_IMPORT');
            $table->text('remarks')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outstanding_purchase_orders');
    }
};
