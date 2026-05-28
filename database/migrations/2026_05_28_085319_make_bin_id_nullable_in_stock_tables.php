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
        Schema::disableForeignKeyConstraints();
        
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE stock_in_items MODIFY COLUMN bin_id BIGINT UNSIGNED NULL');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE stock_movements MODIFY COLUMN bin_id BIGINT UNSIGNED NULL');
        
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE stock_in_items MODIFY COLUMN bin_id BIGINT UNSIGNED NOT NULL');
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE stock_movements MODIFY COLUMN bin_id BIGINT UNSIGNED NOT NULL');
        
        Schema::enableForeignKeyConstraints();
    }
};
