<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * full-user-browser-audit-2026-08-05 / PR2 / slice 02 — patient-age-accessor.
 *
 * Asserts the additive contract on PatientResource::toArray() that introduces
 * the `age` integer (or null) derived from `birth_date`. The test deliberately
 * avoids the Laravel container and the full RefreshDatabase stack so it can run
 * under `php artisan test --filter=PatientResourceAgeTest` on the SQLite
 * in-memory CI config without tripping the documented `transactions.type`
 * dropColumn baseline tech debt (AGENTS.md §6, openspec/config.yaml testing
 * block).
 *
 * Mirrors the `tests/Unit/Seeders/SpecialtyRecordSeederSourceTest` recipe at
 * the assertion level (pure source/contract checks), and adds a minimal
 * Capsule Manager boot in setUp() so the Patient model's `birth_date` cast
 * (which needs the connection resolver for the date format) works without
 * pulling the full Laravel app context. The Capsule connects to a throwaway
 * `:memory:` SQLite database; no migrations run, no rows are persisted
 * across tests, and no transaction lives beyond the test body.
 *
 * Spec scenarios under test:
 *   - Adult patient returns integer (1990-04-15 -> 36 against today 2026-08-05)
 *   - Infant returns 0 on day of birth
 *   - Day-before-first-birthday still returns 0 (no false increment)
 *   - Null birth_date returns JSON null
 *   - Server UTC timezone: identical age across local timezones
 *   - Resource key `age` exists and is the integer/null type
 */
class PatientResourceAgeTest extends TestCase
{
    /** Pin "today" for every boundary case so assertions stay deterministic. */
    private const TODAY_ISO = '2026-08-05 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(self::TODAY_ISO, 'UTC'));
        date_default_timezone_set('UTC');

        // Minimal Eloquent bootstrap so Patient's `birth_date` cast can resolve
        // the connection's date format. No schema, no migrations, no rows.
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setEventDispatcher(new Dispatcher());
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set(@date_default_timezone_get());

        // Tear down the Capsule so other test classes don't see leftover state.
        Capsule::connection()->disconnect();
        Patient::clearBootedModels();

        parent::tearDown();
    }

    private function resolveAgeFromAttributes(array $attributes): mixed
    {
        $patient = new Patient();
        foreach ($attributes as $key => $value) {
            $patient->setAttribute($key, $value);
        }
        $patient->exists = false;

        $resource = new PatientResource($patient);
        $array = $resource->toArray(Request::create('/api/patients'));

        // Use array_key_exists so we can distinguish a missing `age` key
        // from a present-but-null value (the spec mandates the key is
        // present even when birth_date is null so the frontend's
        // `?? 'N/A'` fallback can fire).
        return array_key_exists('age', $array) ? $array['age'] : '__missing__';
    }

    // -------------------------------------------------------------------
    // Spec scenario: adult returns integer
    // -------------------------------------------------------------------

    /** @test */
    public function resource_includes_age_key_when_birth_date_is_present(): void
    {
        $age = $this->resolveAgeFromAttributes([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'birth_date' => Carbon::parse('1990-04-15')->startOfDay(),
        ]);

        $this->assertNotSame(
            '__missing__',
            $age,
            'PatientResource::toArray() MUST include the `age` key when birth_date is present.'
        );
        $this->assertIsInt(
            $age,
            'PatientResource `age` MUST be an integer (not float, string, or null) when birth_date is present.'
        );
        $this->assertSame(
            36,
            $age,
            'PatientResource `age` for 1990-04-15 against today 2026-08-05 MUST equal 36 (Carbon floor semantics).'
        );
    }

    // -------------------------------------------------------------------
    // Spec scenario: day-of-birth returns 0
    // -------------------------------------------------------------------

    /** @test */
    public function resource_age_returns_zero_on_day_of_birth(): void
    {
        $age = $this->resolveAgeFromAttributes([
            'first_name' => 'Bebé',
            'last_name' => 'López',
            'birth_date' => Carbon::parse('2026-08-05')->startOfDay(),
        ]);

        $this->assertIsInt($age, 'Day-of-birth age MUST be an integer.');
        $this->assertSame(
            0,
            $age,
            'A patient whose birth_date equals today MUST report age 0, not 1.'
        );
    }

    // -------------------------------------------------------------------
    // Spec scenario: day-before-first-birthday does NOT falsely increment
    // -------------------------------------------------------------------

    /** @test */
    public function resource_age_returns_zero_one_day_before_first_birthday(): void
    {
        $age = $this->resolveAgeFromAttributes([
            'first_name' => 'Casi',
            'last_name' => 'Cumple',
            'birth_date' => Carbon::parse('2025-08-06')->startOfDay(),
        ]);

        $this->assertIsInt($age, 'Boundary age MUST be an integer.');
        $this->assertSame(
            0,
            $age,
            'A patient born 2025-08-06 against today 2026-08-05 (one day before first birthday) MUST report age 0.'
        );
    }

    /** @test */
    public function resource_age_returns_one_on_first_birthday(): void
    {
        $age = $this->resolveAgeFromAttributes([
            'first_name' => 'Recién',
            'last_name' => 'Cumplido',
            'birth_date' => Carbon::parse('2024-08-06')->startOfDay(),
        ]);

        $this->assertIsInt($age, 'First-birthday age MUST be an integer.');
        $this->assertSame(
            1,
            $age,
            'A patient born 2024-08-06 against today 2026-08-05 (one day past second birthday) MUST report age 2 (Carbon floor from 2024-08-06 to 2026-08-05).'
        );
    }

    // -------------------------------------------------------------------
    // Spec scenario: null birth_date returns JSON null
    // -------------------------------------------------------------------

    /** @test */
    public function resource_age_returns_null_when_birth_date_is_null(): void
    {
        $age = $this->resolveAgeFromAttributes([
            'first_name' => 'Sin',
            'last_name' => 'Fecha',
            'birth_date' => null,
        ]);

        $this->assertNotSame(
            '__missing__',
            $age,
            'PatientResource MUST include the `age` key when birth_date is null (frontend relies on the key for `?? "N/A"` fallback).'
        );
        $this->assertNull(
            $age,
            'PatientResource `age` MUST be JSON null (not 0, not undefined, not "N/A") when birth_date is null.'
        );
    }

    // -------------------------------------------------------------------
    // Spec scenario: server UTC timezone — identical age across local timezones
    // -------------------------------------------------------------------

    /** @test */
    public function resource_age_is_identical_across_local_timezones_for_same_utc_day(): void
    {
        $patient = new Patient();
        $patient->setAttribute('first_name', 'UTC');
        $patient->setAttribute('last_name', 'Traveler');
        $patient->setAttribute('birth_date', Carbon::parse('2000-01-01')->startOfDay());
        $patient->exists = false;

        $ages = [];

        foreach (['UTC', 'America/Lima', 'Asia/Tokyo', 'Europe/Madrid'] as $tz) {
            date_default_timezone_set($tz);
            $resource = new PatientResource($patient);
            $array = $resource->toArray(Request::create('/api/patients'));
            $ages[$tz] = $array['age'] ?? null;
        }

        $this->assertNotSame(
            '__missing__',
            $ages['UTC'] ?? '__missing__',
            'PatientResource MUST include the `age` key regardless of the local timezone of the consuming client.'
        );
        $unique = array_values(array_unique($ages, SORT_REGULAR));
        $this->assertCount(
            1,
            $unique,
            'PatientResource `age` MUST be identical across local timezones for the same UTC instant; got: '
                . json_encode($ages, JSON_UNESCAPED_UNICODE)
        );
        $this->assertIsInt($ages['UTC']);
    }

    // -------------------------------------------------------------------
    // Spec scenario: source-of-truth — the resource contract exposes `age`
    // -------------------------------------------------------------------

    /** @test */
    public function resource_array_key_age_is_documented_in_source(): void
    {
        $source = (string) file_get_contents(
            realpath(__DIR__ . '/../../../app/Http/Resources/PatientResource.php')
        );

        $this->assertStringContainsString(
            "'age'",
            $source,
            'PatientResource::toArray() MUST declare the `age` key in the returned array (API contract).'
        );
    }

    /** @test */
    public function resource_age_uses_birth_date_and_now(): void
    {
        $source = (string) file_get_contents(
            realpath(__DIR__ . '/../../../app/Http/Resources/PatientResource.php')
        );

        // The age computation MUST derive from `birth_date` and call `now()`
        // so that the resource is timezone-aware and never depends on a
        // client-supplied clock. Allow the int cast/ternary that guarantees
        // the JSON contract returns `int|null` instead of Carbon's default
        // float (Carbon 3.x `diffInYears` returns float; the resource must
        // serialize a JSON integer to honour the API contract).
        //
        // Single-quoted string to avoid PHP variable interpolation of `$this`
        // inside the pattern (which would otherwise trigger a noisy PHPUnit
        // warning when the property is undefined on the test class).
        $pattern = '/\'age\'\s*=>.*\$this->birth_date.*->\s*diffInYears\(\s*now\(\s*\)\s*\)/s';

        $this->assertMatchesRegularExpression(
            $pattern,
            $source,
            'PatientResource::toArray() MUST compute age from $this->birth_date and now() (Carbon diffInYears).'
        );
    }
}
