<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #295 (S5) — the stock movement points at the act that consumed it, and a
 * movement without a sede is possible.
 *
 * Traceability: `reference` was a free 50-char string; the ledger now carries
 * the encounter, so a consumption can be read from the act and the act from the
 * ledger without parsing text.
 *
 * Sede: `branch_id` was NOT NULL, but a user with group scope (ADR-0010) has
 * `users.branch_id = null` and must still be able to receive or adjust stock.
 * The column becomes nullable for the same reason `accounting_locks` keeps a
 * global row: the sede is a dimension when it exists, not a requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('clinical_encounter_id')
                ->nullable()
                ->after('product_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_encounter_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable(false)->change();
        });
    }
};
