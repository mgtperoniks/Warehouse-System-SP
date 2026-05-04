<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_uuid')->unique();
            $table->foreignId('item_variant_id')->constrained('item_variants')->cascadeOnDelete();
            $table->foreignId('printer_id')->constrained('printers');
            $table->foreignId('template_id')->constrained('label_templates');
            $table->string('barcode_value');
            // Full serialized ZPL/EPL payload sent to printer
            $table->longText('payload_json')->nullable();
            $table->unsignedSmallInteger('copies')->default(1);
            // pending, processing, completed, failed, cancelled
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])
                  ->default('pending');
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['item_variant_id', 'status']);
            $table->index('job_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
