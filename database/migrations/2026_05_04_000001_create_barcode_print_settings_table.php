<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barcode_print_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('default_printer_type', ['TSC', 'EPSON'])->default('EPSON');
            $table->string('default_printer_ip')->nullable();
            $table->enum('default_label_type', ['ITEM_LABEL', 'BIN_LABEL'])->default('ITEM_LABEL');
            $table->unsignedTinyInteger('default_copies')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcode_print_settings');
    }
};
