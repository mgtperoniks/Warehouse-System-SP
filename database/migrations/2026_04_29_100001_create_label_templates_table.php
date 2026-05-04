<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_code')->unique();
            $table->string('template_name');
            $table->string('template_type')->default('SPAREPART');
            
            // Mendukung TSPL (TSC), ZPL (Zebra), EPL, dan PDF
            $table->enum('printer_language', ['TSPL', 'ZPL', 'EPL', 'PDF'])->default('TSPL');
            
            $table->unsignedInteger('width_mm');
            $table->unsignedInteger('height_mm');
            $table->unsignedInteger('dpi')->default(203); // Default untuk TSC TE200
            
            // Isi perintah printer (raw commands)
            $table->longText('template_body');
            
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            
            $table->timestamps();

            $table->index(['template_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
