<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * #392 — a cancelled appointment releases its slot.
 *
 * `AppointmentRepository::findConflicts` has always excluded `cancelled` from
 * the overlap check, while `unique_user_time_slot` and `unique_chair_time_slot`
 * covered every row, cancellation included. Cancelling an appointment and
 * booking the slot it freed therefore passed the pre-check and died on the
 * unique index with SQLSTATE 23000, which the reschedule door answered as a
 * 500.
 *
 * `slot_reservation` is a stored generated column: NULL for a cancelled row, 1
 * for every other status. Both unique indexes are rebuilt over it, and
 * MySQL/MariaDB treat NULLs in a unique index as distinct, so a cancelled row
 * frees its professional slot and its chair slot while every other status
 * keeps it reserved. The engine computes the column, so no PHP writer can make
 * the index and the pre-check disagree again.
 *
 * SQLite cannot build this schema at all (decision #79), so the guard keeps
 * the local runner out of a statement it does not speak.
 */
return new class extends Migration
{
    /**
     * The rule, in the engine's own words. It reads the status column only, so
     * it is deterministic and safe for a stored generated column.
     */
    private const SLOT_RELEASE_EXPRESSION = "if(`status` = 'cancelled', null, 1)";

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedTinyInteger('slot_reservation')
                ->nullable()
                ->storedAs(self::SLOT_RELEASE_EXPRESSION)
                ->after('status');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('unique_user_time_slot');
            $table->dropUnique('unique_chair_time_slot');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'scheduled_at', 'ends_at', 'slot_reservation'],
                'unique_user_time_slot'
            );
            $table->unique(
                ['dental_chair_id', 'scheduled_at', 'ends_at', 'slot_reservation'],
                'unique_chair_time_slot'
            );
        });
    }

    /**
     * Restore the two indexes of `2025_09_20_082341_create_appointments_table`
     * and drop the generated column.
     *
     * The historical indexes reserve a slot for every row, so they cannot be
     * rebuilt once a booking took a slot a cancellation had freed: the rollback
     * would have to delete one of the two rows. That state is detected before
     * the schema is touched and the rollback stops with the reason instead of
     * leaving the table without its uniqueness guarantee.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $sharedSlot = DB::table('appointments')
            ->select('user_id', 'scheduled_at', 'ends_at')
            ->groupBy('user_id', 'scheduled_at', 'ends_at')
            ->havingRaw('count(*) > 1')
            ->exists()
            || DB::table('appointments')
                ->select('dental_chair_id', 'scheduled_at', 'ends_at')
                ->groupBy('dental_chair_id', 'scheduled_at', 'ends_at')
                ->havingRaw('count(*) > 1')
                ->exists();

        if ($sharedSlot) {
            throw new RuntimeException(
                'Rollback refused: a cancellation already shares its slot with a live appointment. '
                .'Restoring unique_user_time_slot / unique_chair_time_slot would require deleting one of '
                .'the two appointments, so the schema stays as it is (see issue #392).'
            );
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('unique_user_time_slot');
            $table->dropUnique('unique_chair_time_slot');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unique(['user_id', 'scheduled_at', 'ends_at'], 'unique_user_time_slot');
            $table->unique(['dental_chair_id', 'scheduled_at', 'ends_at'], 'unique_chair_time_slot');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('slot_reservation');
        });
    }
};
