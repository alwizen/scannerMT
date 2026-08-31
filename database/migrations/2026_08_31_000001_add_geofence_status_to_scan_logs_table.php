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
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->boolean('is_inside_geofence')->nullable()->after('longitude');
            $table->foreignId('parking_location_id')
                ->nullable()
                ->after('is_inside_geofence')
                ->constrained('parking_locations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropForeign(['parking_location_id']);
            $table->dropColumn(['is_inside_geofence', 'parking_location_id']);
        });
    }
};
