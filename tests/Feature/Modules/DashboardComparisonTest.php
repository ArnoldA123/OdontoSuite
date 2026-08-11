<?php

namespace Tests\Feature\Modules;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ui-premium-microdetail-2026-08 / PR3 — Backend additive `data.comparisons` block.
 *
 * Spec contract: `openspec/changes/ui-premium-microdetail-2026-08/specs/dashboard-period-comparisons/spec.md`.
 * The dashboard `stats` response MUST include a top-level `data.comparisons` object with three keys
 * (`appointments_today`, `total_patients`, `total_appointments_this_month`). Each value has the shape
 * `{ current, previous, period_label, delta_label }`. Existing scalar fields are preserved verbatim.
 *
 * Known runtime caveat: runs against MySQL harness (`odontosuite_test`) because `RefreshDatabase`
 * drops and recreates every table. SQLite test runner is NOT safe for this file — `phpunit.xml`
 * stays untouched per PR3 invariants.
 *
 * Shared fixtures and the same `Carbon::setTestNow`/`->setTestNow()` teardown live in setUp/tearDown
 * so each method can focus on its scenario without repeating boilerplate.
 */
class DashboardComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset the cache between tests — `RefreshDatabase` resets auto-increment, so two
        // tests' users end up with id=1 and the controller's `dashboard_stats_<id>_all`
        // cache key collides, returning the previous test's stats payload.
        \Illuminate\Support\Facades\Cache::flush();
        // Belt-and-braces: also clear any test-time `Carbon::setTestNow` left by another test.
        Carbon::setTestNow();

        // Wipe patients + appointments tables — `RefreshDatabase` only runs
        // `migrate:fresh` ONCE per test process. After that, each test relies on
        // a database transaction for isolation. But `seed()` calls `db:seed`
        // in a separate artisan process that commits OUTSIDE the test transaction,
        // so data from `SpecialtyRecordSeederTest` (100 PatientSeeder patients) leaks
        // into every subsequent test that creates its own patients. A targeted
        // truncate keeps our fixtures deterministic.
        \Illuminate\Support\Facades\DB::table('appointments')->delete();
        \Illuminate\Support\Facades\DB::table('patients')->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'DC Admin',
            'email' => 'dc.admin.' . uniqid('', true) . '@example.com',
            'username' => 'dc_admin_' . uniqid(),
            'password' => bcrypt('password'),
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function branch(): Branch
    {
        return Branch::create([
            'name' => 'DC Branch',
            // branches.code is varchar(10); truncate to fit.
            'code' => substr('DC' . uniqid(), 0, 10),
            'address' => 'Av. Test 123',
            'city' => 'Lima',
            'is_active' => true,
        ]);
    }

    private function chair(): DentalChair
    {
        return DentalChair::create([
            'name' => 'DC Chair',
            'code' => substr('DC' . uniqid(), 0, 10),
            'is_active' => true,
        ]);
    }

    private function appointmentType(): AppointmentType
    {
        return AppointmentType::create([
            'name' => 'DC Control',
            'default_duration_minutes' => 30,
            'price' => 25000,
            'requires_confirmation' => true,
            'is_active' => true,
        ]);
    }

    private function appointment(
        User $doctor,
        Branch $branch,
        AppointmentType $type,
        DentalChair $chair,
        Carbon $scheduledAt,
    ): Appointment {
        $patient = Patient::create([
            'first_name' => 'DC',
            'last_name' => 'Patient',
            'email' => 'dc.patient.' . uniqid('', true) . '@example.com',
            'phone' => '+51 9' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'is_active' => true,
        ]);

        return Appointment::create([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'dental_chair_id' => $chair->id,
            'branch_id' => $branch->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'created_by' => $doctor->id,
        ]);
    }

    private function patientAt(Carbon $createdAt, bool $isActive = true): Patient
    {
        $patient = new Patient([
            'first_name' => 'Reg',
            'last_name' => 'Patient',
            'email' => 'reg.patient.' . uniqid('', true) . '@example.com',
            'phone' => '+51 9' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'is_active' => $isActive,
        ]);
        $patient->created_at = $createdAt;
        $patient->updated_at = $createdAt;
        $patient->save();
        return $patient;
    }

    public function test_same_weekday_comparison_counts_previous_weekday(): void
    {
        // Wednesday 2026-08-12 in America/Lima
        $today = Carbon::create(2026, 8, 12, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();
        $branch = $this->branch();
        $type = $this->appointmentType();
        $chair = $this->chair();

        // 7 appointments today
        for ($i = 0; $i < 7; $i++) {
            $this->appointment($admin, $branch, $type, $chair, $today->copy()->addHours($i));
        }
        // 4 appointments on previous Wednesday (2026-08-05)
        $prevWed = $today->copy()->subDays(7);
        for ($i = 0; $i < 4; $i++) {
            $this->appointment($admin, $branch, $type, $chair, $prevWed->copy()->addHours($i));
        }

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats?branch_id=' . $branch->id);

        $response->assertOk();

        $cmp = $response->json('data.comparisons.appointments_today');
        $this->assertIsArray($cmp, 'data.comparisons.appointments_today MUST exist as an additive block.');
        $this->assertSame(7, $cmp['current']);
        $this->assertSame(4, $cmp['previous']);
        // Assert on the derived month token (Aug = "ago"), not a hardcoded "ago" string.
        // The test would still pass with a wrong "ago" literal in non-August months — see
        // test_period_labels_anchor_december_uses_nov_and_dic_tokens for the proper guard.
        $this->assertStringStartsWith('vs mié 5 ago', $cmp['period_label']);
        $this->assertStringContainsString(' ago', $cmp['period_label']);
        $this->assertSame('+3', $cmp['delta_label']);

        // Scalar headline preserved
        $this->assertSame(7, $response->json('data.appointments_today'));
    }

    public function test_monday_comparison_uses_prior_monday_not_sunday(): void
    {
        // Monday 2026-08-10 (verifiable: Aug 10, 2026 is in fact a Monday).
        // The previous-weekday window must be the prior Monday 2026-08-03.
        // Sunday 2026-08-09 sits between them with 0 appointments — the controller
        // MUST NOT fall back to Sunday when today is Monday.
        $today = Carbon::create(2026, 8, 10, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();
        $branch = $this->branch();
        $type = $this->appointmentType();
        $chair = $this->chair();

        $prevMon = $today->copy()->subDays(7); // 2026-08-03 (Monday)
        for ($i = 0; $i < 5; $i++) {
            $this->appointment($admin, $branch, $type, $chair, $prevMon->copy()->addHours($i));
        }

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats?branch_id=' . $branch->id);

        $response->assertOk();
        $cmp = $response->json('data.comparisons.appointments_today');

        $this->assertSame(5, $cmp['previous'], 'Monday MUST compare to the prior Monday, not Sunday.');
        // Assert on the derived month token (Aug = "ago"), not a hardcoded literal.
        $this->assertStringStartsWith('vs lun 3 ago', $cmp['period_label']);
    }

    public function test_previous_zero_produces_null_delta_label(): void
    {
        $today = Carbon::create(2026, 8, 12, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();
        $branch = $this->branch();
        $type = $this->appointmentType();
        $chair = $this->chair();

        for ($i = 0; $i < 7; $i++) {
            $this->appointment($admin, $branch, $type, $chair, $today->copy()->addHours($i));
        }
        // Zero appointments on previous Wednesday — by construction.

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats?branch_id=' . $branch->id);

        $response->assertOk();
        $cmp = $response->json('data.comparisons.appointments_today');

        $this->assertIsArray($cmp);
        $this->assertSame(0, $cmp['previous']);
        $this->assertNull($cmp['delta_label'], 'delta_label MUST be null when previous === 0.');
        $this->assertNotNull($cmp['period_label'], 'period_label stays informative even when delta is suppressed.');
    }

    public function test_current_zero_with_positive_previous_must_render_negative_delta(): void
    {
        // Monday 2026-08-11 — no new patient registrations this month, 5 last month.
        $today = Carbon::create(2026, 8, 11, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();

        // 5 registrations in previous month (July 2026)
        $lastMonth = $today->copy()->subMonth();
        for ($i = 0; $i < 5; $i++) {
            $this->patientAt($lastMonth->copy()->addDays($i));
        }
        // Zero registrations this month — by construction.

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertOk();
        $cmp = $response->json('data.comparisons.total_patients');

        $this->assertSame(0, $cmp['current']);
        $this->assertSame(5, $cmp['previous']);
        $this->assertNotNull($cmp['delta_label'], 'current === 0 MUST NOT trigger the suppression rule.');
        $this->assertSame('-5', $cmp['delta_label']);
        $this->assertSame('nuevos este mes', $cmp['period_label']);
    }

    public function test_month_boundary_clamp_to_shorter_previous_month(): void
    {
        // Today = 2026-07-31. Previous month = June 2026 (30 days).
        // The same-day-span for previous MUST clamp to June 30.
        $today = Carbon::create(2026, 7, 31, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();
        $branch = $this->branch();
        $type = $this->appointmentType();
        $chair = $this->chair();

        // 30 appointments on June 1-30
        $juneStart = Carbon::create(2026, 6, 1, 9, 0, 0, 'America/Lima');
        for ($i = 0; $i < 30; $i++) {
            $this->appointment($admin, $branch, $type, $chair, $juneStart->copy()->addDays($i));
        }
        // 3 appointments that WOULD be "June 31" if the controller forgot the clamp.
        // They sit on July 1 (next day) to keep the data shape legal — they must NOT count.
        for ($i = 0; $i < 3; $i++) {
            $this->appointment($admin, $branch, $type, $chair, Carbon::create(2026, 7, 1, 9, 0, 0, 'America/Lima')->addHours($i));
        }

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats?branch_id=' . $branch->id);

        $response->assertOk();
        $cmp = $response->json('data.comparisons.total_appointments_this_month');

        $this->assertSame(30, $cmp['previous'], 'The previous-month day-span MUST clamp to June 30 (no June 31, no July leak).');
    }

    public function test_february_span_does_not_fabricate_day_30(): void
    {
        // Today = 2026-02-28. Previous month = January 2026 (31 days).
        // The same-day-span for previous MUST end on Jan 28, NOT on a fabricated Jan 30/31.
        $today = Carbon::create(2026, 2, 28, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();
        $branch = $this->branch();
        $type = $this->appointmentType();
        $chair = $this->chair();

        // 28 appointments on Jan 1-28
        $janStart = Carbon::create(2026, 1, 1, 9, 0, 0, 'America/Lima');
        for ($i = 0; $i < 28; $i++) {
            $this->appointment($admin, $branch, $type, $chair, $janStart->copy()->addDays($i));
        }
        // 5 appointments on Jan 29-31 — MUST NOT count.
        for ($i = 0; $i < 5; $i++) {
            $this->appointment($admin, $branch, $type, $chair, Carbon::create(2026, 1, 29, 9, 0, 0, 'America/Lima')->addDays($i));
        }

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats?branch_id=' . $branch->id);

        $response->assertOk();
        $cmp = $response->json('data.comparisons.total_appointments_this_month');

        $this->assertSame(28, $cmp['previous'], 'February controller path MUST clamp to 28, not include Jan 29-31.');
        $this->assertIsInt($cmp['previous']);
    }

    public function test_total_patients_headline_remains_cumulative_active_count(): void
    {
        $today = Carbon::create(2026, 8, 12, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();

        // 95 historical active patients, all registered strictly before the current month.
        // Their created_at is well below the comparison's current-month window.
        for ($i = 0; $i < 95; $i++) {
            $createdAt = Carbon::create(2025, 1, 1, 10, 0, 0, 'America/Lima')->addDays($i);
            $this->patientAt($createdAt, isActive: true);
        }
        // 10 new registrations this month — these drive the COMPARISON, not the headline.
        $monthStart = $today->copy()->startOfMonth();
        for ($i = 0; $i < 10; $i++) {
            $this->patientAt($monthStart->copy()->addDays($i));
        }
        // Cumulative active = 95 + 10 = 105 — the headline value the spec pins.

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertOk();
        // D15 regression guard — cumulative headline is preserved.
        $this->assertSame(105, $response->json('data.total_patients'),
            'data.total_patients MUST stay the cumulative active count even with the comparison block attached.');

        // Comparison is the DIFFERENT quantity (new registrations) — not the headline.
        $cmp = $response->json('data.comparisons.total_patients');
        $this->assertSame(10, $cmp['current']);
        $this->assertSame('nuevos este mes', $cmp['period_label']);
    }

    public function test_total_patients_delta_is_absolute_not_percentage(): void
    {
        $today = Carbon::create(2026, 8, 12, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();

        // 22 registrations this month (all created within the current calendar month)
        // and 10 in the previous month → delta = +12.
        $monthStart = $today->copy()->startOfMonth();
        for ($i = 0; $i < 22; $i++) {
            $this->patientAt($monthStart->copy()->addDays($i));
        }
        $lastMonth = $today->copy()->subMonth()->startOfMonth();
        for ($i = 0; $i < 10; $i++) {
            $this->patientAt($lastMonth->copy()->addDays($i));
        }

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertOk();
        $delta = $response->json('data.comparisons.total_patients.delta_label');

        $this->assertIsString($delta);
        $this->assertSame('+12', $delta, 'delta_label MUST be an absolute figure, not a percentage.');
        $this->assertStringNotContainsString('%', $delta);
        $this->assertStringNotContainsString('Infinity', $delta);
        $this->assertStringNotContainsString('NaN', $delta);
    }

    public function test_professionals_and_cash_have_no_comparison_keys(): void
    {
        $today = Carbon::create(2026, 8, 12, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertOk();
        $this->assertArrayNotHasKey('total_professionals', $response->json('data.comparisons'),
            'Profesionales MUST NOT carry a comparison entry.');
        $this->assertArrayNotHasKey('cash_session', $response->json('data.comparisons'),
            'Estado de Caja MUST NOT carry a comparison entry.');

        // But their scalar fields are still present.
        $this->assertArrayHasKey('total_professionals', $response->json('data'));
        $this->assertArrayHasKey('cash_session', $response->json('data'));
    }

    public function test_additive_shape_does_not_turn_existing_scalars_into_objects(): void
    {
        $today = Carbon::create(2026, 8, 12, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertOk();

        // Each existing scalar field stays a plain integer, not an object.
        foreach (['appointments_today', 'total_patients', 'total_appointments_this_month'] as $key) {
            $value = $response->json("data.{$key}");
            $this->assertIsInt($value, "data.{$key} MUST remain a scalar integer (not an object).");
        }

        // And the comparison block is its own sibling, not nested inside any scalar.
        $this->assertIsArray($response->json('data.comparisons'));
        $this->assertIsArray($response->json('data.comparisons.appointments_today'));
        $this->assertIsArray($response->json('data.comparisons.total_patients'));
        $this->assertIsArray($response->json('data.comparisons.total_appointments_this_month'));
    }

    public function test_comparison_block_never_emits_dangerous_percentages(): void
    {
        $today = Carbon::create(2026, 8, 12, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertOk();
        $comparisons = $response->json('data.comparisons');

        foreach ($comparisons as $stat => $payload) {
            foreach (['current', 'previous'] as $field) {
                $value = $payload[$field];
                $this->assertIsInt($value, "data.comparisons.{$stat}.{$field} MUST be a finite integer.");
                $this->assertGreaterThanOrEqual(0, $value);
            }
            $delta = $payload['delta_label'];
            if ($delta !== null) {
                $this->assertIsString($delta);
                $this->assertStringNotContainsString('%', $delta, "delta_label MUST NOT contain '%'.");
                $this->assertStringNotContainsString('Infinity', $delta);
                $this->assertStringNotContainsString('NaN', $delta);
            }
        }
    }

    public function test_first_day_of_month_uses_single_day_period_label(): void
    {
        // Spec scenario: today = 2026-09-01 (first day of September).
        // Previous month = August 2026 (31 days). Same-day-span = Aug 1 to Aug 1 (single day).
        // period_label uses the month abbreviation derived from the previous month (Aug → "ago").
        $today = Carbon::create(2026, 9, 1, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertOk();
        $cmp = $response->json('data.comparisons.total_appointments_this_month');
        $this->assertSame('ago', $this->previousMonthToken($today),
            'Anchor sanity: today=2026-09-01 → previous month is August → token "ago".');
        $this->assertStringStartsWith('vs ago 1 (1 día)', $cmp['period_label']);
    }

    public function test_period_labels_anchor_december_uses_nov_and_dic_tokens(): void
    {
        // today = 2026-12-15 (Tuesday). Outside August — the previous bug
        // hardcoded the literal "ago" which silently rendered "ago" in every month.
        // - total_appointments_this_month's previous window is Nov 1-15 → label must name "nov"
        // - appointments_today's previous weekday is 2026-12-08 (Tuesday) → label must name "dic"
        $today = Carbon::create(2026, 12, 15, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();
        $branch = $this->branch();
        $type = $this->appointmentType();
        $chair = $this->chair();

        for ($i = 0; $i < 3; $i++) {
            $this->appointment($admin, $branch, $type, $chair, $today->copy()->addHours($i));
        }
        $prevWeekday = $today->copy()->subDays(7);
        for ($i = 0; $i < 2; $i++) {
            $this->appointment($admin, $branch, $type, $chair, $prevWeekday->copy()->addHours($i));
        }

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats?branch_id=' . $branch->id);
        $response->assertOk();

        $cmpAppt = $response->json('data.comparisons.appointments_today');
        $this->assertSame(3, $cmpAppt['current']);
        $this->assertSame(2, $cmpAppt['previous']);
        $this->assertStringContainsString(' dic', $cmpAppt['period_label'],
            'appointments_today label MUST name the prev-weekday month (dic), not the hardcoded "ago".');
        $this->assertStringNotContainsString(' ago ', $cmpAppt['period_label'],
            'period_label MUST NOT embed the literal "ago" between spaces.');
        $this->assertStringContainsString('mar 8', $cmpAppt['period_label']);

        $cmpMonth = $response->json('data.comparisons.total_appointments_this_month');
        $this->assertStringContainsString(' nov ', $cmpMonth['period_label'],
            'total_appointments_this_month label MUST name the prev-month token (nov), not "ago".');
        $this->assertStringContainsString('15', $cmpMonth['period_label']);
    }

    public function test_period_labels_anchor_january_crosses_year_boundary(): void
    {
        // today = 2026-01-10 (Saturday). Pins three rules at once:
        // - subMonthNoOverflow crosses the year (Dec 2025, not Jan 2025)
        // - count window is Dec 1-10, 2025
        // - labels use "dic" for the previous month and "ene" for the previous weekday
        $today = Carbon::create(2026, 1, 10, 10, 0, 0, 'America/Lima');
        Carbon::setTestNow($today);

        $admin = $this->admin();
        $branch = $this->branch();
        $type = $this->appointmentType();
        $chair = $this->chair();

        $this->appointment($admin, $branch, $type, $chair, $today->copy()->addHour());
        // 2 on previous Saturday (2026-01-03)
        $prevWeekday = $today->copy()->subDays(7);
        for ($i = 0; $i < 2; $i++) {
            $this->appointment($admin, $branch, $type, $chair, $prevWeekday->copy()->addHours($i));
        }
        // 4 on Dec 1-4, 2025 (within window — must count)
        $decStart = Carbon::create(2025, 12, 1, 9, 0, 0, 'America/Lima');
        for ($i = 0; $i < 4; $i++) {
            $this->appointment($admin, $branch, $type, $chair, $decStart->copy()->addDays($i));
        }
        // 3 on Jan 5-7, 2026 (current month, outside window — must NOT count)
        $janStart = Carbon::create(2026, 1, 5, 9, 0, 0, 'America/Lima');
        for ($i = 0; $i < 3; $i++) {
            $this->appointment($admin, $branch, $type, $chair, $janStart->copy()->addDays($i));
        }

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats?branch_id=' . $branch->id);
        $response->assertOk();

        $cmpAppt = $response->json('data.comparisons.appointments_today');
        $this->assertSame(1, $cmpAppt['current']);
        $this->assertSame(2, $cmpAppt['previous']);
        $this->assertStringContainsString(' ene', $cmpAppt['period_label'],
            'appointments_today label MUST name the prev-weekday month (ene), not "ago".');
        $this->assertStringContainsString('sáb 3', $cmpAppt['period_label']);

        $cmpMonth = $response->json('data.comparisons.total_appointments_this_month');
        $this->assertSame(4, $cmpMonth['previous'],
            'Dec 1-10 window MUST count the 4 Dec fixtures and exclude the 3 Jan fixtures.');
        $this->assertStringContainsString(' dic ', $cmpMonth['period_label'],
            'total_appointments_this_month label MUST name the prev-month token (dic), not "ago".');
    }

    public function test_period_labels_never_hardcode_ago_between_spaces(): void
    {
        // Spread guard across months. appointments_today names the PREVIOUS-WEEKDAY's month
        // (NOT always today's month — e.g. today=Oct 5 → prev weekday Sep 28 = "sep").
        $admin = $this->admin();
        $branch = $this->branch();
        $type = $this->appointmentType();
        $chair = $this->chair();

        // [today, prev-weekday token, prev-month token]
        $cases = [
            ['2026-01-10', 'ene', 'dic'],
            ['2026-03-15', 'mar', 'feb'],
            ['2026-05-20', 'may', 'abr'],
            ['2026-07-25', 'jul', 'jun'],
            ['2026-08-30', 'ago', 'jul'],
            ['2026-10-05', 'sep', 'sep'],
            ['2026-12-15', 'dic', 'nov'],
        ];

        foreach ($cases as [$isoDay, $apptToken, $monthToken]) {
            $today = Carbon::parse($isoDay, 'America/Lima')->setTime(10, 0, 0);
            Carbon::setTestNow($today);

            $this->appointment($admin, $branch, $type, $chair, $today->copy()->addHour());
            $this->appointment($admin, $branch, $type, $chair, $today->copy()->subDays(7)->addHour());

            $response = $this->actingAs($admin, 'sanctum')
                ->getJson('/api/dashboard/stats?branch_id=' . $branch->id);
            $response->assertOk();
            $cmpAppt = $response->json('data.comparisons.appointments_today');
            $cmpMonth = $response->json('data.comparisons.total_appointments_this_month');

            $this->assertStringContainsString(" {$apptToken}", $cmpAppt['period_label'],
                "{$isoDay}: appointments_today label MUST contain ' {$apptToken}'.");
            $this->assertStringContainsString(" {$monthToken} ", $cmpMonth['period_label'],
                "{$isoDay}: total_appointments_this_month label MUST contain ' {$monthToken} '.");
        }
    }

    private function monthToken(int $month): string
    {
        return ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][$month - 1];
    }

    private function previousMonthToken(Carbon $today): string
    {
        $prev = $today->copy()->subMonthNoOverflow();
        return $this->monthToken($prev->month);
    }
}
