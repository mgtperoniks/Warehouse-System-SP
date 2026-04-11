<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_barcode_counters', function (Blueprint $table) {
            $table->id();
            $table->string('prefix')->unique(); // 'INT'
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_barcode_counters');
    }
};
