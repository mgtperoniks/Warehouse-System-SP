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
        Schema::create('stock_transactions', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type')->default('OUT');
            $table->enum('status', ['DRAFT', 'CONFIRMED', 'CANCELLED'])->default('CONFIRMED');
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // PIC
            $table->string('reference')->nullable();
            $table->decimal('total_price', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
