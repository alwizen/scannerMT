<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->foreignId('scan_session_id')
                ->nullable()
                ->after('id')
                ->constrained('scan_sessions')
                ->cascadeOnDelete();
            $table->unique(['scan_session_id', 'tanker_compartment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropUnique(['scan_session_id', 'tanker_compartment_id']);
            $table->dropForeign(['scan_session_id']);
            $table->dropColumn('scan_session_id');
        });
    }
};