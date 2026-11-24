<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * citas-privacidad (T1) — announcing the patient leaves a trace.
 *
 * Pressing "Anunciar" notified the professional and kept no record: the
 * appointment stayed `arrived` (correct, S2 of #281) and nothing said when the
 * announcement happened or who made it. Both facts get their own columns, so
 * the agenda and Mi día answer without reading the custody log.
 *
 * `announced_by` is nullable and `nullOnDelete` because the announcement
 * survives the disappearance of the user who made it, like `checked_in_by`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('announced_at')->nullable()->after('checked_in_by');
            $table->foreignId('announced_by')
                ->nullable()
                ->after('announced_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('announced_by');
            $table->dropColumn('announced_at');
        });
    }
};
