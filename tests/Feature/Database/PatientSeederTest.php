<?php

namespace Tests\Feature\Database;

use App\Models\Patient;
use Database\Seeders\PatientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression guard for PatientSeeder email uniqueness.
 *
 * The seeder used to build every email as `firstName.lastName@domain` from
 * `array_rand`-picked names, so two of the 100 rows intermittently collided on
 * the `patients_email_unique` index (about 7% of the runs). MySQL rejected the
 * insert and `migrate:fresh --seed` aborted halfway, which left
 * `MigrateFreshPortabilityTest` red.
 *
 * The emails are now derived from the loop index, so the 100 rows are distinct
 * by construction. Two gates protect that:
 *
 *  - `emails_are_unique` proves the seeded dataset does not violate the unique
 *    index. On its own this gate only detects a re-randomised derivation on
 *    the runs where the random draw actually collides, so it is probabilistic.
 *  - `emails_are_position_derived` pins the deterministic mechanism, which
 *    turns the guard into a deterministic one.
 *
 * The `migrate:fresh --seed` dataset committed by MigrateFreshPortabilityTest
 * survives this class's RefreshDatabase transaction, and PatientSeeder is not
 * idempotent (`document_number` is derived from the loop index too), so the
 * patients table is purged in setUp — the same ordering leak
 * SpecialtyRecordSeederTest handles. The purge is transactional and rolls back
 * with the class.
 *
 * `@group mysql`: the migration chain is not portable on SQLite (see
 * AGENTS.md §6), so the class is skipped there and runs in CI on MySQL 8.0.
 *
 * @group mysql
 */
class PatientSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('PatientSeeder gate requires the MySQL migration chain — see AGENTS.md §6');
        }

        DB::table('patients')->delete();
    }

    /**
     * @test
     */
    public function emails_are_unique(): void
    {
        $this->seed(PatientSeeder::class);

        $this->assertSame(100, Patient::count(), 'PatientSeeder must create exactly 100 patients');
        $this->assertSame(
            100,
            Patient::distinct()->count('email'),
            'The 100 seeded patients must not share any email (patients_email_unique)'
        );
    }

    /**
     * @test
     */
    public function phones_are_unique(): void
    {
        $this->seed(PatientSeeder::class);

        $this->assertSame(
            100,
            Patient::distinct()->count('phone'),
            'The 100 seeded patients must not share any phone (patients_phone_unique)'
        );
    }

    /**
     * @test
     */
    public function emails_are_position_derived(): void
    {
        $this->seed(PatientSeeder::class);

        $emails = Patient::orderBy('id')->pluck('email')->all();

        foreach ($emails as $position => $email) {
            $index = $position + 1;

            $this->assertStringContainsString(
                ".{$index}@",
                (string) $email,
                "Patient {$index} must carry its loop index in the email local part, so uniqueness never depends on the random name draw"
            );
        }
    }
}
