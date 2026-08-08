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
        Schema::table('receiving_sessions', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('started_at');
            $table->string('pdf_path')->nullable()->after('remarks');
        });

        Schema::create('receiving_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_session_id')->constrained('receiving_sessions')->onDelete('cascade');
            $table->string('role'); // DISERAHKAN_OLEH, DITERIMA_OLEH, BAG_GUDANG
            $table->string('signature_path');
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at');
            $table->timestamps();
            
            // Avoid duplicate signatures for same session and role
            $table->unique(['receiving_session_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiving_signatures');
        
        Schema::table('receiving_sessions', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['reviewed_by', 'reviewed_at', 'pdf_path']);
        });
    }
};
