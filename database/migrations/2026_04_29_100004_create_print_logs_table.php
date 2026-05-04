<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_job_id')->nullable()->constrained('print_jobs')->nullOnDelete();
            $table->foreignId('item_variant_id')->constrained('item_variants')->cascadeOnDelete();
            // PRINT, REPRINT, CANCEL, FAILED
            $table->enum('action_type', ['PRINT', 'REPRINT', 'CANCEL', 'FAILED']);
            // Required for REPRINT: damaged, lost, unreadable, replacement
            $table->string('action_reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // JSON blob: copies, template_code, printer_code, barcode_value, etc.
            $table->json('metadata')->nullable();
            // No updated_at — audit logs are immutable
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_variant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('action_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_logs');
    }
};
