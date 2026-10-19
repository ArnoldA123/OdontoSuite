<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appointment types confirm by default (decision D1, 2026-10-19).
 *
 * The column was created with `default(false)` while every seeded catalog row
 * sets `true`: the seeder expressed the product intent and the schema
 * contradicted it. The schema is the single source of truth for callers that
 * do not speak the form (API clients, imports, tinker), so the default moves to
 * `true`; the create form inherits the same value.
 *
 * No backfill. Rows created before this change keep their stored value, since
 * rewriting stored data would turn reminders on for clinics that may have
 * configured otherwise outside the application. The edit modal now exposes the
 * flag, so an operator can flip any type deliberately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->boolean('requires_confirmation')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_types', function (Blueprint $table) {
            $table->boolean('requires_confirmation')->default(false)->change();
        });
    }
};
