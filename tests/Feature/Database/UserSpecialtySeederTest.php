<?php

namespace Tests\Feature\Database;

use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\RoleBasedUsersSeeder;
use Database\Seeders\SpecialtySeeder;
use Database\Seeders\UserSpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Invariant for the demo dataset (issue #211).
 *
 * `user_specialties` is the source of truth for a professional's specialty
 * (ADR-0007), but the demo seeders only wrote the legacy `users.specialty`
 * string and the migration backfill ran before any user existed. The catalog
 * therefore reported zero professionals for every row.
 *
 * The rule pinned here: every seeded clinical user whose legacy value maps to
 * a catalog code owns the corresponding pivot row, the catalog counts them,
 * values that name a job instead of a specialty stay out of the pivot, and
 * re-running the seeder never duplicates a row.
 */
class UserSpecialtySeederTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{0: string, 1: string}> email => specialties.code */
    private const EXPECTED_PIVOT = [
        ['ever+odontologo@test.com', 'general'],
        ['odontologo+odontologo@test.com', 'general'],
        ['brenda+odontologo@test.com', 'ortodoncia'],
        ['wilmer+implantologo@test.com', 'implantologia'],
        ['implantologo+implantologo@test.com', 'implantologia'],
    ];

    /** @var array<int, string> legacy values that name a job, not a specialty */
    private const NON_CLINICAL_LEGACY = [
        'sofia+tecnico@test.com',
        'azul+asistente@test.com',
        'milagros+finanzas@test.com',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // A previous migrate:fresh --seed run survives the RefreshDatabase
        // transaction; purge it in dependency order so the counts below are
        // produced by this test's fixtures only.
        DB::table('user_specialties')->delete();
        DB::table('users')->delete();
        DB::table('specialties')->delete();
    }

    private function seedDomain(): void
    {
        $this->seed(RoleBasedUsersSeeder::class);
        $this->seed(SpecialtySeeder::class);
        $this->seed(UserSpecialtySeeder::class);
    }

    public function test_seeded_clinical_users_own_the_pivot_row_of_their_legacy_specialty(): void
    {
        $this->seedDomain();

        foreach (self::EXPECTED_PIVOT as [$email, $code]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $specialty = Specialty::query()->where('code', $code)->firstOrFail();

            $this->assertDatabaseHas('user_specialties', [
                'user_id' => $user->id,
                'specialty_id' => $specialty->id,
                'is_primary' => true,
            ]);
        }
    }

    public function test_the_catalog_reports_professionals_for_the_seeded_clinical_specialties(): void
    {
        $this->seedDomain();

        $admin = User::query()->where('email', 'elizabet+administrador@test.com')->firstOrFail();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/specialties');
        $response->assertOk();

        $rows = collect($response->json('data'))->keyBy('code');

        foreach (['general', 'ortodoncia', 'implantologia'] as $code) {
            $this->assertArrayHasKey($code, $rows->all());
            $this->assertGreaterThan(
                0,
                $rows[$code]['professionals_count'],
                "The catalog MUST count professionals for the seeded {$code} specialty."
            );
        }
    }

    public function test_legacy_values_that_name_a_job_do_not_write_the_pivot(): void
    {
        $this->seedDomain();

        foreach (self::NON_CLINICAL_LEGACY as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertDatabaseMissing('user_specialties', ['user_id' => $user->id]);
        }
    }

    public function test_running_the_seeder_again_does_not_duplicate_rows(): void
    {
        $this->seedDomain();

        $before = DB::table('user_specialties')->count();
        $this->assertGreaterThan(0, $before);

        $this->seed(UserSpecialtySeeder::class);

        $this->assertSame($before, DB::table('user_specialties')->count());
    }
}
