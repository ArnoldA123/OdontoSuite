<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * citas-privacidad (T3) — the demo dataset belongs to Trujillo.
 *
 * A user without a sede has group scope (ADR-0010), so every login that came
 * from the seeders saw the whole company and no `branch_id` filter had a
 * population to measure. The seeders now stamp Trujillo on the rows they
 * write; this migration repairs the rows already stored: users first (the one
 * exception is the group administrator, who must keep group scope to manage
 * the branch catalog), then the environments by their code map, the cash
 * sessions and, in cascade, the appointments.
 *
 * The appointment cascade repeats the one in `100100` on purpose: this
 * migration runs before it, so filling the professionals here lets both the
 * agenda and the later backfill see the same sede. Every statement is guarded
 * by `branch_id IS NULL`, so running it twice changes nothing and a sede set by
 * hand is never overwritten.
 *
 * On a fresh database the branch does not exist yet: the migration then does
 * nothing, because the seeders are the ones that create Trujillo. `down()`
 * deliberately does not revert data: the sede was missing, not wrong.
 *
 * Only MySQL/MariaDB is supported for schema work in this project (decision
 * #79), so the cascades are the engine's own UPDATE ... JOIN instead of one
 * query per row.
 */
return new class extends Migration
{
    private const BRANCH_CODE = 'SC-TRU-01';

    private const GROUP_SCOPE_EMAIL = 'admin+administrador@test.com';

    /**
     * Environment code => sede code, the same distribution the seeder writes.
     *
     * @var array<string, string>
     */
    private const CHAIR_BRANCH_CODES = [
        'AMB-001' => 'SC-TRU-01',
        'AMB-002' => 'SC-TRU-01',
        'AMB-003' => 'SC-TRU-01',
        'AMB-004' => 'SC-LIM-01',
        'AMB-005' => 'SC-NOR-01',
        'AMB-006' => 'SC-SUR-01',
        'AMB-007' => 'SC-TRU-01',
        'AMB-008' => 'SC-TRU-01',
    ];

    public function up(): void
    {
        $trujilloId = DB::table('branches')->where('code', self::BRANCH_CODE)->value('id');

        if (! $trujilloId) {
            return;
        }

        // 1) Every user without a sede gets Trujillo, except the one group
        //    administrator: without group scope nobody could administer sedes.
        DB::table('users')
            ->whereNull('branch_id')
            ->where('email', '!=', self::GROUP_SCOPE_EMAIL)
            ->update(['branch_id' => $trujilloId]);

        // 2) The environments follow their code map, resolved by code because
        //    branch ids differ between databases.
        foreach (self::CHAIR_BRANCH_CODES as $chairCode => $branchCode) {
            $branchId = DB::table('branches')->where('code', $branchCode)->value('id');

            if (! $branchId) {
                continue;
            }

            DB::table('dental_chairs')
                ->where('code', $chairCode)
                ->whereNull('branch_id')
                ->update(['branch_id' => $branchId]);
        }

        // 3) The drawer follows its owner: the sessions with no sede are the
        //    ones opened by users who now belong to Trujillo.
        if (Schema::hasColumn('cash_register_sessions', 'branch_id')) {
            DB::table('cash_register_sessions')
                ->whereNull('branch_id')
                ->update(['branch_id' => $trujilloId]);
        }

        // 4) Appointments inherit the sede, professional first and chair second.
        DB::statement(
            'UPDATE appointments a '
            .'JOIN users u ON u.id = a.user_id '
            .'SET a.branch_id = u.branch_id '
            .'WHERE a.branch_id IS NULL AND u.branch_id IS NOT NULL'
        );

        DB::statement(
            'UPDATE appointments a '
            .'JOIN dental_chairs c ON c.id = a.dental_chair_id '
            .'SET a.branch_id = c.branch_id '
            .'WHERE a.branch_id IS NULL AND c.branch_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        // A filled sede is the one the row always belonged to. Reverting it
        // would make the appointments invisible to their professionals again.
    }
};
