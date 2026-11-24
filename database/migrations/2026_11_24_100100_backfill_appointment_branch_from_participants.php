<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * citas-privacidad (T1) — every appointment inherits the sede of its people.
 *
 * `ScopesToBranch` stamps a new appointment with the sede of the user who
 * creates it, and group scope (reception or administration without a sede)
 * leaves it null. The index of a professional with a sede filters
 * `branch_id = their sede`, so an appointment booked by group reception for
 * that professional was invisible to them: the row existed, the agenda did not
 * show it.
 *
 * The writer now derives the sede from the professional (then from the chair),
 * and this migration backfills the rows already stored with the same cascade
 * and the same precedence. Both statements are guarded by `branch_id IS NULL`,
 * so running it twice changes nothing and a sede set by hand is never
 * overwritten.
 *
 * Only MySQL/MariaDB is supported for schema work in this project (decision
 * #79), and the cascade is expressed as the engine's own UPDATE ... JOIN so
 * the whole population is resolved in two statements instead of row by row.
 * `down()` deliberately does not revert data: the sede was missing, not wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) The professional's sede wins.
        DB::statement(
            'UPDATE appointments a '
            .'JOIN users u ON u.id = a.user_id '
            .'SET a.branch_id = u.branch_id '
            .'WHERE a.branch_id IS NULL AND u.branch_id IS NOT NULL'
        );

        // 2) Only the rows whose professional has no sede fall back to the chair.
        DB::statement(
            'UPDATE appointments a '
            .'JOIN dental_chairs c ON c.id = a.dental_chair_id '
            .'SET a.branch_id = c.branch_id '
            .'WHERE a.branch_id IS NULL AND c.branch_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        // A filled sede is not an error: it is the one the appointment always
        // belonged to. Reverting it would make the row invisible again.
    }
};
