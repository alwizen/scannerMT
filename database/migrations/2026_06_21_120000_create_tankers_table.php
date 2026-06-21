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
        Schema::create('tankers', function (Blueprint $table) {
            $table->id();
            $table->string('nopol')->unique();
            $table->unsignedInteger('capacity_kl');
            $table->enum('status', ['available', 'maintenance', 'afkir'])
                ->default('available');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tankers');
    }
};
