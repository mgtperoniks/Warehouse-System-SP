<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_variant_id')->constrained()->onDelete('cascade');
            $table->string('barcode')->unique();
            $table->string('type')->default('SUPPLIER'); // e.g., SUPPLIER, INTERNAL
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_barcodes');
    }
};
