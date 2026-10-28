<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #394 — cancelling an appointment documents why.
 *
 * The motive lived in `notes`, the generic free-text field another edit can
 * overwrite, so the record of a cancellation was not the appointment's own.
 * It gets its own nullable column: the status door and the PUT door require it
 * when the resulting status is `cancelled`, and the rule clears it whenever the
 * appointment leaves that state (the audit log keeps the trace of the reason,
 * not the row).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('cancellation_reason', 255)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('cancellation_reason');
        });
    }
};
