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
        Schema::create('baso_documents', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('inventory_adjustment_id')
                ->unique()
                ->constrained('inventory_adjustments')
                ->onDelete('restrict');
                
            $table->string('baso_number')->unique();
            
            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
                
            $table->dateTime('generated_at');
            $table->string('pdf_path');
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baso_documents');
    }
};
