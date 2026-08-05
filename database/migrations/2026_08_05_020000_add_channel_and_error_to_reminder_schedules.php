<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 03 (T-03.6): add `channel` column to reminder_schedules so the
 * whitelist validation has a real column to persist into. Also adds
 * `error_message` so failed deliveries can carry diagnostics.
 *
 * Both columns are nullable to remain backwards-compatible with
 * existing rows that pre-date the BF-001 implementation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_schedules', function (Blueprint $table) {
            $table->string('channel', 20)->nullable()->after('scheduled_at');
            $table->text('error_message')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('reminder_schedules', function (Blueprint $table) {
            $table->dropColumn(['channel', 'error_message']);
        });
    }
};
