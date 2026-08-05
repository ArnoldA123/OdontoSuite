<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 03 (T-03.8): idempotency tracking for ReminderProvider hourly run.
 * Single-row table (singleton) — `runs_count` and `last_run_at` allow the
 * provider to skip overlapping ticks if the previous run took > 60s.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_provider_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('runs_count')->default(0);
            $table->unsignedInteger('last_processed')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_provider_runs');
    }
};
