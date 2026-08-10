<?php

namespace Tests\Unit\Database;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Slice 07c (test-fixture-user-uniqueness) regression guard.
 *
 * Contract: `UserFactory::definition()` MUST emit a non-null, per-call unique
 * `username` so `User::factory()->create()` satisfies the
 * `users.username varchar(255) NOT NULL UNIQUE` column declared by migration
 * 2025_09_13_151927_add_username_and_role_to_users_table.php.
 *
 * Canonical oracle is the dev MySQL harness (`phpunit.mysql.xml`), because
 * only MySQL strict mode raises the NOT NULL violation this slice fixes;
 * SQLite carries a pre-existing DROP COLUMN migration tech-debt.
 *
 * Rollback: delete this single file + revert the `username` key in
 * `database/factories/UserFactory.php::definition()`.
 */
class UserFactoryContractTest extends TestCase
{
    use RefreshDatabase;

    /** Maximum width of the `users.username` column (Laravel `string` default). */
    private const USERNAME_MAX_LENGTH = 255;

    /** Faker `userName()` charset after transliterate + strtolower. */
    private const HUMAN_READABLE_PATTERN = '/^[a-z0-9._-]+$/';

    private const AUTH_CONTROLLER = 'app/Http/Controllers/Api/AuthController.php';
    private const USER_CONTROLLER = 'app/Http/Controllers/Api/UserController.php';
    private const ROLE_BASED_SEEDER = 'database/seeders/RoleBasedUsersSeeder.php';

    // ---------------------------------------------------------------------
    // A. In-memory factory contract (no persistence)
    // ---------------------------------------------------------------------

    public function test_factory_definition_declares_a_non_null_username(): void
    {
        $user = User::factory()->make();

        $this->assertNotNull(
            $user->username,
            'UserFactory::definition() must emit a `username`; users.username is NOT NULL with no default.'
        );
        $this->assertIsString($user->username, 'The generated username must be a string.');
        $this->assertNotSame('', trim((string) $user->username), 'The generated username must not be blank.');
    }

    public function test_generated_username_fits_the_varchar_255_column(): void
    {
        $username = (string) User::factory()->make()->username;

        $this->assertGreaterThan(
            0,
            mb_strlen($username),
            'The generated username must not be empty; users.username is NOT NULL.'
        );
        $this->assertLessThanOrEqual(
            self::USERNAME_MAX_LENGTH,
            mb_strlen($username),
            'The generated username must fit users.username varchar(255).'
        );
    }

    public function test_generated_username_is_human_readable(): void
    {
        $username = (string) User::factory()->make()->username;

        $this->assertMatchesRegularExpression(
            self::HUMAN_READABLE_PATTERN,
            $username,
            'The generated username must stay human-readable ASCII (lowercase letters, digits, dot, underscore, hyphen).'
        );
    }

    public function test_factory_produces_a_unique_username_per_call(): void
    {
        $first = (string) User::factory()->make()->username;
        $second = (string) User::factory()->make()->username;

        $this->assertNotSame($first, $second, 'Two consecutive UserFactory calls must not collide on username.');
    }

    // ---------------------------------------------------------------------
    // B. Persisted contract (MySQL strict mode is the oracle)
    // ---------------------------------------------------------------------

    public function test_factory_create_persists_without_integrity_violation(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->username, 'The persisted user must carry a username.');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => $user->username]);
    }

    public function test_two_consecutive_creates_produce_distinct_persisted_usernames(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertNotNull($first->username);
        $this->assertNotNull($second->username);
        $this->assertNotSame($first->username, $second->username);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_count_five_create_produces_five_distinct_usernames(): void
    {
        $usernames = User::factory()->count(5)->create()->pluck('username');

        $this->assertDatabaseCount('users', 5);
        $this->assertCount(5, $usernames->filter(fn ($u) => $u !== null && $u !== ''));
        $this->assertSame(5, $usernames->unique()->count(), 'All five factory usernames must be distinct.');
    }

    public function test_explicit_duplicate_username_still_raises_a_unique_violation(): void
    {
        User::factory()->create(['username' => 'duplicate']);

        $this->expectException(QueryException::class);

        User::factory()->create(['username' => 'duplicate']);
    }

    // ---------------------------------------------------------------------
    // C. Existing factory recipes and fixtures preserved
    // ---------------------------------------------------------------------

    public function test_unverified_state_still_emits_a_username(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertNull($user->email_verified_at, 'The unverified() state must still clear email_verified_at.');
        $this->assertNotNull($user->username, 'The unverified() state must still emit a username.');
    }

    public function test_default_role_is_user_and_role_override_still_works(): void
    {
        $default = User::factory()->create();
        $admin = User::factory()->create(['role' => 'administrador']);

        $this->assertSame('user', $default->fresh()->role, 'The migration default role must stay `user`.');
        $this->assertSame('administrador', $admin->fresh()->role, 'Explicit role overrides must still win.');
    }

    public function test_username_override_still_wins_over_the_generated_value(): void
    {
        $user = User::factory()->create(['username' => 'fixture-admin']);

        $this->assertSame('fixture-admin', $user->username);
        $this->assertDatabaseHas('users', ['username' => 'fixture-admin']);
    }

    public function test_direct_user_create_with_explicit_username_still_works(): void
    {
        $user = User::create([
            'username' => 'fixture-direct',
            'name' => 'Admin',
            'email' => 'a@x.test',
            'password' => bcrypt('x'),
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'fixture-direct',
            'name' => 'Admin',
            'email' => 'a@x.test',
        ]);
    }

    // ---------------------------------------------------------------------
    // D. Production user-creation flow untouched
    // ---------------------------------------------------------------------

    public function test_production_user_creation_paths_never_invoke_the_factory(): void
    {
        foreach ([self::AUTH_CONTROLLER, self::USER_CONTROLLER, self::ROLE_BASED_SEEDER] as $relative) {
            $source = file_get_contents(base_path($relative));

            $this->assertIsString($source, "Missing production file: {$relative}");
            $this->assertStringNotContainsString(
                'User::factory()',
                $source,
                "{$relative} must never build users through the test factory."
            );
        }
    }

    public function test_role_based_users_seeder_still_supplies_explicit_usernames(): void
    {
        $source = file_get_contents(base_path(self::ROLE_BASED_SEEDER));

        $this->assertStringContainsString(
            "'username' =>",
            $source,
            'RoleBasedUsersSeeder must keep supplying explicit usernames.'
        );
        $this->assertStringContainsString(
            'User::firstOrCreate(',
            $source,
            'RoleBasedUsersSeeder must keep its idempotent firstOrCreate seeding path.'
        );
    }

    public function test_users_username_column_is_still_not_null_and_unique(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'username'));

        $this->expectException(QueryException::class);

        User::factory()->create(['username' => null]);
    }
}
