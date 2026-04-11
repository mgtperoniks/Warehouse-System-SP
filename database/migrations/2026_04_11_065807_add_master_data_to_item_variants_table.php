<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_variants', function (Blueprint $table) {
            $table->string('erp_code')->after('item_id')->unique()->nullable();
            $table->string('unit')->after('brand')->nullable();
            $table->text('description')->after('unit')->nullable();
            $table->index('erp_code');
        });

        // Migrate existing barcodes if any (though count is 0, we follow the pattern)
        $variants = DB::table('item_variants')->whereNotNull('barcode')->get();
        foreach ($variants as $variant) {
            DB::table('item_barcodes')->insert([
                'item_variant_id' => $variant->id,
                'barcode' => $variant->barcode,
                'type' => 'SUPPLIER',
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('item_variants', function (Blueprint $table) {
            $table->dropColumn('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('item_variants', function (Blueprint $table) {
            $table->string('barcode')->unique()->nullable()->after('sku');
        });

        // Optional: Re-migrate data back if needed, but usually rolling back dropping a column means data loss
        // For safe rollback, we'd need to pull from item_barcodes where is_primary = true

        Schema::table('item_variants', function (Blueprint $table) {
            $table->dropColumn(['erp_code', 'unit', 'description']);
        });
    }
};
