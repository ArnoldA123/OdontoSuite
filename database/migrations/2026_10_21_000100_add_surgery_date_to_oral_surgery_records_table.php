<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Issue #246: `oral_surgery_records` had no date column, so the listing dated
 * the surgical act by `created_at` — the capture time — and the status enum
 * could not be checked against a real date.
 *
 * Existing rows are dated in the same migration: the linked appointment's
 * schedule is the closest evidence of the surgical act, and the capture day is
 * the only available fallback otherwise.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('oral_surgery_records', function (Blueprint $table) {
            $table->date('surgery_date')->nullable()->after('surgery_site');
            $table->index('surgery_date');
        });

        $this->backfill();
    }

    /**
     * Date the rows that predate this migration.
     *
     * Public so OralSurgeryDateBackfillTest can pin the data rule without
     * replaying the column DDL in a transaction.
     */
    public function backfill(): void
    {
        DB::table('oral_surgery_records')
            ->whereNull('surgery_date')
            ->update([
                'surgery_date' => DB::raw(
                    'COALESCE('
                    .'(SELECT DATE(scheduled_at) FROM appointments '
                    .'WHERE appointments.id = oral_surgery_records.appointment_id), '
                    .'DATE(created_at))'
                ),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oral_surgery_records', function (Blueprint $table) {
            $table->dropIndex(['surgery_date']);
            $table->dropColumn('surgery_date');
        });
    }
};
