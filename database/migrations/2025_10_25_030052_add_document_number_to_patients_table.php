<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hotfix edit (2026-08-05) — NEW-002 portability fix.
 *
 * The original backfill used the Patient Eloquent model directly. Because
 * Patient declares SoftDeletes, Eloquent injected an extra
 * `and patients.deleted_at is null` into the WHERE clause, but the
 * `patients.deleted_at` column is not created until
 * `2026_06_11_001034_add_soft_deletes_to_patients_table` runs 8 months later
 * in the chain. The migration was provably unrunnable on every fresh MySQL
 * database (error `SQLSTATE[42S22] Column not found: 1054 Unknown column
 * 'patients.deleted_at'`). It was rewritten in place because the file had
 * never been recorded as Ran in any environment.
 *
 * The backfill now uses raw Query Builder so it is decoupled from any model's
 * trait composition, scopes, or accessors. The deterministic
 * `DOC-{8-digit padded id}` output is preserved byte-for-byte.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Idempotent guard: the dev DB has `document_number` already from a
        // previously aborted MySQL DDL run (non-transactional). Re-adding the
        // column would throw `Duplicate column name 'document_number'`.
        if (! Schema::hasColumn('patients', 'document_number')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->string('document_number', 20)->nullable()->after('last_name');
            });
        }

        // Backfill using Query Builder (NOT Eloquent) so the SoftDeletes trait
        // on the Patient model cannot inject a `deleted_at is null` clause
        // that references a column that does not exist yet at this point in
        // the chain.
        $rows = DB::table('patients')
            ->whereNull('document_number')
            ->orWhere('document_number', '')
            ->get();
        foreach ($rows as $row) {
            DB::table('patients')
                ->where('id', $row->id)
                ->update([
                    'document_number' => 'DOC-' . str_pad($row->id, 8, '0', STR_PAD_LEFT),
                ]);
        }

        // Re-apply the unique index via raw DDL to avoid pulling in
        // `doctrine/dbal` as a runtime requirement. MySQL tolerates
        // `ADD UNIQUE` on a column whose values are already unique, so the
        // re-apply is harmless against the half-applied dev DB.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE patients ADD UNIQUE patients_document_number_unique (document_number)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('document_number');
        });
    }
};
