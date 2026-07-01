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
        Schema::table('item_variants', function (Blueprint $table) {
            $table->enum('procurement_type', ['LOCAL', 'IMPORT'])->default('LOCAL')->index();
            $table->enum('inventory_class', ['CONSUMABLE', 'SPAREPART'])->default('CONSUMABLE')->index();
            $table->unsignedInteger('lead_time_days')->default(30);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_variants', function (Blueprint $table) {
            $table->dropIndex(['procurement_type']);
            $table->dropIndex(['inventory_class']);
            $table->dropColumn(['procurement_type', 'inventory_class', 'lead_time_days']);
        });
    }
};
