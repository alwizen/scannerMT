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
        Schema::create('tanker_compartments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tanker_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedInteger('compartment_no');
            $table->decimal('capacity_kl', 8, 2);
            $table->string('rfid_uid')->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tanker_id', 'compartment_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tanker_compartments');
    }
};
