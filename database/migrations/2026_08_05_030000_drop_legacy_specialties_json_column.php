<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * bugfix-2026-08 slice 05 — remove the legacy `users.specialties` JSON
     * column (BF-023 source-of-truth cleanup).
     *
     * The column was added by 2025_10_24_202936_add_multi_sede_fields_to_existing_tables.php
     * but never populated by application code. The User model has `specialties`
     * excluded from `$fillable` since Sprint 2 (DM-6 fix), and the source-of-truth
     * is now the `user_specialties` pivot (ADR-0007).
     *
     * The legacy `users.specialty` (string) column is RETAINED — it powers the
     * frontend UserController compatibility path.
     *
     * Guard exemption: filename starts with `drop_legacy_`, which SddCheckMigrationsTest
     * recognises as the only carve-out from the additive-only policy.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'specialties')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('specialties');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'specialties')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('specialties')->nullable()->after('professional_license');
            });
        }
    }
};