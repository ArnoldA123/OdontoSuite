<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ui-premium-microdetail-2026-08 / PR3 — Index `patients.created_at`.
 *
 * Backed by the dashboard period-comparisons slice: the comparison for
 * `total_patients` counts NEW registrations grouped by month via `created_at`,
 * so the column needs an index for the date-range query to stay cheap once the
 * patient table grows past a few thousand rows.
 *
 * appointments.scheduled_at already has an index (migration 2025_09_20_082341);
 * this migration adds only the missing counterpart for Patient.created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
