<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_sessions', function (Blueprint $table) {
            $table->text('action_note')->nullable()->after('completed_at');
            $table->timestamp('action_handled_at')->nullable()->after('action_note');
            $table->foreignId('action_handled_by')
                ->nullable()
                ->after('action_handled_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scan_sessions', function (Blueprint $table) {
            $table->dropForeign(['action_handled_by']);
            $table->dropColumn(['action_note', 'action_handled_at', 'action_handled_by']);
        });
    }
};
