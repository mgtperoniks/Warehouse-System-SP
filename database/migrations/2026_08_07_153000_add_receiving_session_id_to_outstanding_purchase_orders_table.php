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
        Schema::table('outstanding_purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('receiving_session_id')->nullable()->after('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outstanding_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('receiving_session_id');
        });
    }
};
