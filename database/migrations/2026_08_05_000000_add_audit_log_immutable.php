<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 01 / T-01.10 — add nullable boolean `is_immutable` column on audit_logs.
 *
 * Future-proof hook (slice 02 will harden it). The flag tells downstream code
 * whether a given audit log row is write-protected (cannot be updated/deleted).
 * Additive-only migration: no FK, no destructive change, safe on rollback.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->boolean('is_immutable')->nullable()->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('is_immutable');
        });
    }
};
