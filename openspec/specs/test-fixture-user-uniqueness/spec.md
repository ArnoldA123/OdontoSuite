# Test Fixture User Uniqueness (NEW — TEST-INFRASTRUCTURE FIX, PR5 delta)

## Purpose

Fix the test-infrastructure defect surfaced by PR5 (`apply-progress.md` §"PR5 risks" §3). `database/factories/UserFactory.php::definition()` returns `name`, `email`, `email_verified_at`, `password`, `remember_token` — but no `username`. The `users` table (`database/migrations/2025_09_13_151927_add_username_and_role_to_users_table.php` line 15) declares `username` as `string` (NOT NULL, no default) plus `unique`. Against MySQL strict mode, `User::factory()->create()` therefore raises an integrity violation on the `username` NOT NULL constraint. The defect blocks every `RefreshDatabase` Feature test that calls `User::factory()` (e.g. `PatientControllerAgeTest`, `ReminderDispatchTest`, `AuthTest`, `UserSpecialtySourceOfTruthTest`, `AuditLogMigrationTest`). This is a TEST-INFRASTRUCTURE FIX: only `UserFactory.php` is edited. Production user creation (`AuthController::register`, `UserSeeder`, `RoleBasedUsersSeeder`) is NOT touched. No new permissions, no migrations, no schema changes.

## Source evidence

- `database/factories/UserFactory.php` lines 24-33 — definition returns no `username`.
- `database/migrations/2025_09_13_151927_add_username_and_role_to_users_table.php` line 15 — `$table->string('username')->unique()->after('name');` (NOT NULL, no default).
- `apply-progress.md` PR5 §"PR5 risks" — User factory username defect is pre-existing across every RefreshDatabase Feature test under both SQLite and dev MySQL.

## Requirements

### Requirement: UserFactory generates a unique non-null username

`UserFactory::definition()` MUST include a `username` key whose value is non-null and unique per call (no two consecutive calls in the same test run may collide). The value MUST be a string that fits the existing `string` column (≤ 255 chars) and MUST default to a deterministic-safe form such as `fake()->unique()->userName()` or `Str::slug(fake()->unique()->userName())`.

#### Scenario: Two consecutive factory calls produce distinct usernames

- GIVEN `User::factory()` is called twice in the same test
- WHEN both users are saved against MySQL
- THEN `users[0].username !== users[1].username`
- AND `users[0].username !== null`
- AND both rows persist without `Integrity constraint violation: 1048 Column 'username' cannot be null` (or any equivalent NOT NULL violation).

#### Scenario: Username is string-shaped and within column width

- GIVEN `User::factory()` is called
- WHEN the user is persisted
- THEN `users.username` MUST be a string whose length is ≤ 255 characters (default `string` column width).

#### Scenario: Username uniqueness constraint respected

- GIVEN a `username = 'duplicate'` already exists in the database
- WHEN `User::factory()->create(['username' => 'duplicate'])` is invoked
- THEN the call MUST raise the standard unique-violation exception (sanity-check that the factory does not bypass uniqueness).

#### Scenario: A regression test exercises the factory against MySQL

- GIVEN a Feature test that calls `User::factory()->count(5)->create()` and asserts `assertDatabaseCount('users', 5)`
- WHEN `php artisan test --filter=UserFactoryUsernameTest` runs against the MySQL harness
- THEN every user row MUST persist with a non-null, distinct `username`.

### Requirement: Real test fixtures continue to work

The slice MUST NOT alter the `User::create(['username' => ...])` pattern used in feature tests and seeders. Direct construction with an explicit `username` MUST continue to work without modification.

#### Scenario: User::create with explicit username still works

- GIVEN a test fixture uses `User::create(['username' => 'fixture-admin', 'name' => 'Admin', 'email' => 'a@x.test', 'password' => bcrypt('x')])`
- WHEN the call runs against MySQL
- THEN the row persists with the explicit username, name, email, and password.

### Requirement: Production user creation flow untouched

The slice MUST NOT modify `AuthController::register`, `UserSeeder`, `RoleBasedUsersSeeder`, or any seeder that creates users. The factory is the only edited file.

#### Scenario: AuthController::register still creates users with explicit username

- GIVEN the registration request payload supplies `username`, `name`, `email`, `password`
- WHEN `AuthController::register` runs
- THEN the user persists with the request-supplied username
- AND the UserFactory is NOT invoked on this path.

#### Scenario: UserSeeder and RoleBasedUsersSeeder unchanged

- GIVEN the existing seeders
- WHEN the slice is applied
- THEN `database/seeders/UserSeeder.php` and `database/seeders/RoleBasedUsersSeeder.php` MUST be byte-identical to pre-slice.

### Requirement: Permission semantics unchanged

The slice MUST NOT add, remove, or rename roles. The factory MUST emit values compatible with the existing `role` default (`'user'`) when not overridden, matching pre-slice behaviour.

#### Scenario: Default factory user has role 'user'

- GIVEN `User::factory()->create()` is called without overriding `role`
- WHEN the user is persisted
- THEN `users.role` MUST default to `'user'` (the migration default), consistent with pre-slice behaviour.

#### Scenario: role override still works

- GIVEN `User::factory()->create(['role' => 'administrador'])`
- WHEN the user is persisted
- THEN `users.role === 'administrador'`.

## Permissions

- No new permissions. The slice only adjusts `UserFactory::definition()` to include `username`. No role, gate, or policy changes.

## Rollback invariants

- Reverting the slice commit alone MUST restore the original `UserFactory.php` (no `username` in definition). `User::create(['username' => ...])` paths remain valid; only `User::factory()->create()` loses the auto-username (the documented defect returns).
- The slice MUST NOT alter any production code path (`AuthController`, `UserSeeder`, `RoleBasedUsersSeeder`, registration API).
- The slice MUST NOT add a migration to the `users` table.
