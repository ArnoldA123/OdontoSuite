<?php

namespace Tests\Feature\Seeders;

use App\Models\Branch;
use App\Models\DentalChair;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\EnvironmentSeeder;
use Database\Seeders\RoleBasedUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Invariant of the demo dataset (citas-privacidad T3).
 *
 * A user without a sede has group scope (ADR-0010), so the seeded dataset saw
 * the whole company and neither the sede column nor its filter had a
 * population to measure. The rule pinned here: Trujillo is seeded once, every
 * seeded user belongs to it, one group administrator keeps group scope to
 * manage the branch catalog, re-running the user seeder fills a missing sede
 * without duplicating or overwriting, and the environments are distributed by
 * their code map with at least three active chairs in Trujillo.
 */
class TrujilloSeedScopeTest extends TestCase
{
    use RefreshDatabase;

    private const TRUJILLO_CODE = 'SC-TRU-01';

    private const GROUP_SCOPE_EMAIL = 'admin+administrador@test.com';

    private const GROUP_SCOPE_NAME = 'Gerencia OdontoSuite';

    private const RECEPTION_EMAIL = 'recepcionista+recepcionista@test.com';

    /**
     * The demo population after the cleanup: one account per role plus the
     * second administrator (Trujillo) and the second dentist. The six "Test"
     * accounts and the duplicated Ever administrator are gone on purpose.
     *
     * @var list<string>
     */
    private const SEEDED_EMAILS = [
        'elizabet+administrador@test.com',
        'admin+administrador@test.com',
        'recepcionista+recepcionista@test.com',
        'ever+odontologo@test.com',
        'brenda+odontologo@test.com',
        'wilmer+implantologo@test.com',
        'sofia+tecnico@test.com',
        'azul+asistente@test.com',
        'milagros+finanzas@test.com',
    ];

    /** @var list<string> */
    private const PRUNED_EMAILS = [
        'ever+administrador@test.com',
        'odontologo+odontologo@test.com',
        'implantologo+implantologo@test.com',
        'tecnico+tecnico@test.com',
        'asistente+asistente@test.com',
        'finanzas+finanzas@test.com',
    ];

    /** @var array<string, string> environment code => branch code */
    private const ENVIRONMENT_BRANCH_CODES = [
        'AMB-001' => 'SC-TRU-01',
        'AMB-002' => 'SC-TRU-01',
        'AMB-003' => 'SC-TRU-01',
        'AMB-004' => 'SC-LIM-01',
        'AMB-005' => 'SC-NOR-01',
        'AMB-006' => 'SC-SUR-01',
        'AMB-007' => 'SC-TRU-01',
        'AMB-008' => 'SC-TRU-01',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // A previous migrate:fresh --seed run survives the RefreshDatabase
        // transaction; purge it so the population measured here is the one
        // this test's seeders produce.
        DB::table('users')->delete();
        DB::table('dental_chairs')->delete();
    }

    private function trujillo(): Branch
    {
        return Branch::query()->where('code', self::TRUJILLO_CODE)->firstOrFail();
    }

    private function seedUsers(): void
    {
        $this->seed(BranchSeeder::class);
        $this->seed(RoleBasedUsersSeeder::class);
    }

    public function test_trujillo_is_seeded_once_and_every_seeded_user_belongs_to_it(): void
    {
        $this->seed(BranchSeeder::class);
        $this->seed(BranchSeeder::class);
        $this->seed(RoleBasedUsersSeeder::class);

        $trujillo = $this->trujillo();

        $this->assertSame('Trujillo San Isidro', $trujillo->name);
        $this->assertSame(1, Branch::query()->where('code', self::TRUJILLO_CODE)->count());

        $this->assertCount(9, User::all());

        $seeded = User::query()->pluck('email')->all();
        sort($seeded);
        $expected = self::SEEDED_EMAILS;
        sort($expected);
        $this->assertSame($expected, $seeded);
        $this->assertSame(
            [],
            User::query()->whereIn('email', self::PRUNED_EMAILS)->pluck('email')->all(),
            'The pruned demo accounts MUST NOT come back with the seeder.'
        );

        $group = User::query()->where('email', self::GROUP_SCOPE_EMAIL)->firstOrFail();
        $this->assertSame(self::GROUP_SCOPE_NAME, $group->name);
        $this->assertSame(
            'Rosa Quispe Flores',
            User::query()->where('email', self::RECEPTION_EMAIL)->value('name'),
            'The receptionist demo account is renamed by the cleanup.'
        );

        foreach (User::all() as $user) {
            if ($user->email === self::GROUP_SCOPE_EMAIL) {
                $this->assertNull(
                    $user->branch_id,
                    'The group administrator MUST stay without a sede to manage the branch catalog.'
                );

                continue;
            }

            $this->assertSame(
                $trujillo->id,
                $user->branch_id,
                "The seeded user {$user->email} MUST belong to Trujillo."
            );
        }

        $this->assertSame(
            1,
            User::query()->whereNull('branch_id')->count(),
            'Only the group administrator keeps group scope.'
        );
    }

    public function test_the_seeded_population_has_one_account_per_role_and_no_test_duplicates(): void
    {
        $this->seedUsers();

        $this->assertSame(9, User::query()->count());
        $this->assertSame(
            1,
            User::query()->where('role', 'administrador')->whereNull('branch_id')->count(),
            'The group administrator is the only scope-less seeded account.'
        );

        foreach (['administrador', 'recepcionista', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente', 'finanzas'] as $role) {
            $this->assertGreaterThan(0, User::query()->where('role', $role)->count(), "The seeded dataset MUST keep a {$role} account.");
        }

        $this->assertSame(0, User::query()->where('name', 'like', '% Test')->count());
    }

    public function test_rerunning_the_user_seeder_fills_a_missing_sede_without_duplicating_or_overwriting(): void
    {
        $this->seedUsers();

        $trujillo = $this->trujillo();
        $lima = Branch::query()->where('code', 'SC-LIM-01')->firstOrFail();
        $totalUsers = User::query()->count();

        // One user already belongs to another sede; another lost his.
        $moved = User::query()->where('email', 'brenda+odontologo@test.com')->firstOrFail();
        $moved->update(['branch_id' => $lima->id]);

        $reset = User::query()->where('email', 'ever+odontologo@test.com')->firstOrFail();
        DB::table('users')->where('id', $reset->id)->update(['branch_id' => null]);

        $this->seed(RoleBasedUsersSeeder::class);

        $this->assertSame($totalUsers, User::query()->count());
        $this->assertSame(
            $lima->id,
            $moved->fresh()->branch_id,
            'A sede already assigned MUST NOT be overwritten.'
        );
        $this->assertSame(
            $trujillo->id,
            $reset->fresh()->branch_id,
            'A seeded user without a sede MUST be filled by the rerun.'
        );
        $this->assertSame('Brenda Tejada Alfaro', $moved->fresh()->name);
    }

    public function test_environments_are_distributed_across_sedes_and_a_rerun_repairs_a_missing_sede(): void
    {
        $this->seed(BranchSeeder::class);
        $this->seed(EnvironmentSeeder::class);

        $branchIds = Branch::query()
            ->whereIn('code', array_values(array_unique(self::ENVIRONMENT_BRANCH_CODES)))
            ->pluck('id', 'code');

        foreach (self::ENVIRONMENT_BRANCH_CODES as $chairCode => $branchCode) {
            $chair = DentalChair::query()->where('code', $chairCode)->firstOrFail();

            $this->assertSame(
                (int) $branchIds[$branchCode],
                (int) $chair->branch_id,
                "The environment {$chairCode} MUST belong to the sede {$branchCode}."
            );
        }

        $trujilloId = $this->trujillo()->id;

        $this->assertSame(5, DentalChair::query()->where('branch_id', $trujilloId)->count());
        $this->assertGreaterThanOrEqual(
            3,
            DentalChair::query()
                ->where('branch_id', $trujilloId)
                ->where('is_active', true)
                ->count(),
            'Trujillo MUST have at least three active environments to test the agenda.'
        );

        // A rerun corrects an existing environment that lost its sede and does
        // not duplicate the catalog.
        DB::table('dental_chairs')->where('code', 'AMB-001')->update(['branch_id' => null]);
        $this->seed(EnvironmentSeeder::class);

        $this->assertSame(
            $trujilloId,
            (int) DentalChair::query()->where('code', 'AMB-001')->value('branch_id')
        );
        $this->assertSame(count(self::ENVIRONMENT_BRANCH_CODES), DentalChair::query()->count());
    }
}
