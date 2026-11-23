<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A soft-deleted appointment keeps reserving its instant.
 *
 * `appointments.slot_reservation` (issue #392) is a stored generated column
 * that is NULL for a cancelled row and 1 for everything else, and both unique
 * indexes include it: `unique_user_time_slot` and `unique_chair_time_slot`.
 * `SoftDeletes` sets `deleted_at` but leaves `status` alone, so a removed
 * appointment still carries 1, the index keeps its `(professional|chair,
 * scheduled_at, ends_at)` key reserved, and the pre-check — which never sees the
 * row again, because the model's global scope hides it — answers "slot is
 * free" while the engine answers "duplicate key". Rebooking the same
 * appointment therefore failed with a conflict no screen could explain.
 *
 * The expression is redefined to read `deleted_at` as well: NULL for a
 * cancelled OR soft-deleted row, 1 for every row that is still on the agenda.
 * The cancellation rule of #392 is preserved untouched.
 *
 * Order matters: both unique indexes are built over the column, so they are
 * dropped before the generated expression is altered and rebuilt with the same
 * definition afterwards. The ALTER rebuilds the table, which recomputes the
 * column for the rows already stored: a row deleted before this migration
 * releases its instant as soon as it runs, without a backfill.
 *
 * SQLite cannot build this schema at all (decision #79), so the guard keeps the
 * local runner out of a statement it does not speak.
 */
return new class extends Migration
{
    /**
     * The rule, in the engine's own words: the slot is released when the
     * appointment is cancelled or when it is no longer on the agenda. Reads
     * only immutable-for-the-expression columns, so it is deterministic and
     * safe for a stored generated column.
     */
    private const SLOT_RELEASE_EXPRESSION = "if(`status` = 'cancelled' or `deleted_at` is not null, null, 1)";

    /**
     * The expression this migration replaces, restored by `down()`.
     */
    private const PREVIOUS_SLOT_RELEASE_EXPRESSION = "if(`status` = 'cancelled', null, 1)";

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('unique_user_time_slot');
            $table->dropUnique('unique_chair_time_slot');
        });

        $this->rewriteSlotReleaseExpression(self::SLOT_RELEASE_EXPRESSION);

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
     * Restore the cancellation-only expression.
     *
     * A row deleted after this migration may share its instant with a live
     * appointment that took it: restoring `1` for the deleted row would make
     * the unique indexes collide, and the ALTER would fail mid-way with a
     * duplicate key once the indexes are back. The state is detected before the
     * schema is touched and the rollback stops with the reason instead of
     * leaving the table without its uniqueness guarantee.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $sharedSlot = $this->hasReservedSlotCollision();

        if ($sharedSlot) {
            throw new RuntimeException(
                'Rollback refused: a soft-deleted or cancelled appointment shares its slot with a live one. '
                .'Restoring the cancellation-only expression would make unique_user_time_slot / '
                .'unique_chair_time_slot collide, so the schema stays as it is.'
            );
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('unique_user_time_slot');
            $table->dropUnique('unique_chair_time_slot');
        });

        $this->rewriteSlotReleaseExpression(self::PREVIOUS_SLOT_RELEASE_EXPRESSION);

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
     * Change the generated expression in place. MariaDB and MySQL rebuild the
     * table for a stored generated column, which is exactly what recomputes the
     * rows already written.
     */
    private function rewriteSlotReleaseExpression(string $expression): void
    {
        DB::statement(sprintf(
            'ALTER TABLE `appointments` MODIFY COLUMN `slot_reservation` '
            .'TINYINT UNSIGNED GENERATED ALWAYS AS (%s) STORED',
            $expression
        ));
    }

    /**
     * Two rows of the same pair and interval that would both reserve it once
     * the expression stops honouring `deleted_at`. Cancelled rows are excluded
     * because the restored expression keeps releasing their slot.
     */
    private function hasReservedSlotCollision(): bool
    {
        foreach (['user_id', 'dental_chair_id'] as $column) {
            $collision = DB::table('appointments')
                ->select($column, 'scheduled_at', 'ends_at')
                ->where('status', '!=', 'cancelled')
                ->groupBy($column, 'scheduled_at', 'ends_at')
                ->havingRaw('count(*) > 1')
                ->exists();

            if ($collision) {
                return true;
            }
        }

        return false;
    }
};
