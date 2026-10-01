<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->index(['driver_id', 'scanned_at'], 'scan_logs_driver_id_scanned_at_index');
            $table->index('scan_session_id', 'scan_logs_scan_session_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropIndex('scan_logs_driver_id_scanned_at_index');
            $table->dropIndex('scan_logs_scan_session_id_index');
        });
    }
};
