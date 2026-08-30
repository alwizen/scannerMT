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
        Schema::table('tanker_compartments', function (Blueprint $table) {
            $table->string('type', 20)->default('rfid')->after('compartment_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tanker_compartments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
