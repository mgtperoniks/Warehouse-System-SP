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
            if (!Schema::hasColumn('receiving_sessions', 'completed_by')) {
                $table->foreignId('completed_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receiving_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('receiving_sessions', 'completed_by')) {
                $table->dropForeign(['completed_by']);
                $table->dropColumn(['completed_by']);
            }
        });
    }
};
