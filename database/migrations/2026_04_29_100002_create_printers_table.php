<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('printer_code')->unique();
            $table->string('printer_name');
            
            // Abstraksi driver komunikasi
            $table->enum('communication_type', ['TCP', 'USB', 'SPOOL'])->default('TCP');
            $table->enum('printer_language', ['TSPL', 'ZPL', 'EPL', 'PDF'])->default('TSPL');
            
            $table->string('printer_ip')->nullable();
            $table->integer('printer_port')->default(9100);
            $table->integer('dpi')->default(203);
            
            // Kapabilitas hardware
            $table->boolean('supports_direct_thermal')->default(true);
            $table->boolean('supports_thermal_transfer')->default(true);
            $table->unsignedInteger('max_label_width_mm')->default(108); // Standar 4 inch
            
            $table->string('location')->nullable();
            
            // Status lifecycle terperinci
            $table->enum('status', [
                'online', 'offline', 'busy', 'error', 'paused', 'maintenance'
            ])->default('offline');
            
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();

            $table->index(['printer_code', 'status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
