<?php

namespace Tests\Feature\Modules;

use App\Models\Patient;
use App\Models\ProcedureCatalog;
use App\Models\Specialty;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\Reports\ProcedureStatsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * procedure-stats-ui-redesign / T1 — metric contract of GET /api/admin/procedure-stats.
 *
 * S4.1 the requested period includes the whole `to` day (America/Lima).
 * S4.2 `treatment_plan_items.is_optional = true` never counts.
 * S4.3 items of `treatment_plans.status = 'cancelled'` never count.
 * S4.4 unchanged: catalog totals, usage ordering, limit, revenue formula,
 *      the "no specialty" bucket and the response shape.
 * S4.5 the 500 body never exposes the raw exception message.
 *
 * Runs against the MySQL harness (RefreshDatabase rebuilds every table):
 *   MYSQL_PORT=3308 docker compose up -d --wait mysql
 *   DB_PORT=3308 vendor/bin/phpunit --configuration=phpunit.mysql.xml --filter=ProcedureStatsReport
 */
class ProcedureStatsReportTest extends TestCase
{
    use RefreshDatabase;

    private const STATS_URL = '/api/admin/procedure-stats';

    private function user(string $role, string $suffix): User
    {
        // Direct create: User::factory() is blocked against MySQL because the
        // default factory does not emit `username`.
        return User::create([
            'name' => 'Stats ' . $suffix,
            'email' => "stats.{$suffix}@example.com",
            'username' => "stats_{$suffix}",
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        return $this->user('administrador', 'admin_' . uniqid());
    }

    private function patient(): Patient
    {
        return Patient::create([
            'first_name' => 'Stats',
            'last_name' => 'Patient',
            'is_active' => true,
        ]);
    }

    private function specialty(string $code): Specialty
    {
        return Specialty::create([
            'code' => $code,
            'name' => 'Especialidad ' . $code,
            'is_active' => true,
        ]);
    }

    private function catalog(string $code, ?Specialty $specialty = null, bool $isActive = true): ProcedureCatalog
    {
        return ProcedureCatalog::create([
            'code' => $code,
            'name' => 'Procedimiento ' . $code,
            'specialty_id' => $specialty?->id,
            'default_cost' => 100,
            'default_duration_minutes' => 30,
            'is_active' => $isActive,
        ]);
    }

    private function plan(User $owner, string $status, string $createdAt): TreatmentPlan
    {
        $this->travelTo(Carbon::parse($createdAt));

        $plan = TreatmentPlan::create([
            'patient_id' => $this->patient()->id,
            'created_by' => $owner->id,
            'plan_number' => 'TP-' . uniqid(),
            'title' => 'Plan ' . uniqid(),
            'status' => $status,
        ]);

        $this->travelBack();

        return $plan;
    }

    private function item(
        TreatmentPlan $plan,
        ?ProcedureCatalog $catalog,
        int $quantity = 1,
        float $unitCost = 50.0,
        bool $isOptional = false,
    ): TreatmentPlanItem {
        return TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'procedure_catalog_id' => $catalog?->id,
            'procedure_name' => 'Item ' . uniqid(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 2),
            'is_optional' => $isOptional,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function stats(User $actor, array $query = []): array
    {
        $response = $this->actingAs($actor, 'sanctum')
            ->getJson(self::STATS_URL . '?' . http_build_query($query));

        $response->assertOk();

        return $response->json('data');
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>|null
     */
    private function topProcedure(array $stats, int $procedureId): ?array
    {
        foreach ($stats['top_procedures'] as $row) {
            if ($row['procedure_id'] === $procedureId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>|null
     */
    private function specialtyRow(array $stats, ?int $specialtyId): ?array
    {
        foreach ($stats['by_specialty'] as $row) {
            if ($row['specialty_id'] === $specialtyId) {
                return $row;
            }
        }

        return null;
    }

    public function test_period_includes_the_whole_last_day(): void
    {
        $admin = $this->admin();
        $catalog = $this->catalog('S41-DAY');
        $evening = $this->plan($admin, 'approved', '2026-10-19 18:30:00');
        $this->item($evening, $catalog);

        $stats = $this->stats($admin, ['from' => '2026-10-19', 'to' => '2026-10-19']);

        $this->assertNotNull(
            $this->topProcedure($stats, $catalog->id),
            'S4.1: an item created at 18:30 on the `to` day MUST count inside a single-day period.'
        );
        $this->assertSame(1, $this->topProcedure($stats, $catalog->id)['usage_count']);
        $this->assertNotNull(
            $this->specialtyRow($stats, null),
            'S4.1: the specialty distribution MUST include the same `to` day.'
        );
    }

    public function test_period_does_not_leak_into_the_next_day(): void
    {
        $admin = $this->admin();
        $catalog = $this->catalog('S41-EDGE');
        $afterMidnight = $this->plan($admin, 'approved', '2026-10-20 00:00:01');
        $this->item($afterMidnight, $catalog);

        $stats = $this->stats($admin, ['from' => '2026-10-19', 'to' => '2026-10-19']);

        $this->assertNull(
            $this->topProcedure($stats, $catalog->id),
            'S4.1: 00:00:01 of the day after `to` MUST stay out of the period.'
        );
    }

    public function test_optional_items_are_excluded_from_usage_and_revenue(): void
    {
        $admin = $this->admin();
        $specialty = $this->specialty('S42');
        $catalog = $this->catalog('S42-OPT', $specialty);
        $plan = $this->plan($admin, 'approved', '2026-10-10 10:00:00');

        $this->item($plan, $catalog, 2, 50.0);
        $this->item($plan, $catalog, 3, 50.0, isOptional: true);

        $stats = $this->stats($admin, ['from' => '2026-10-01', 'to' => '2026-10-31']);

        $top = $this->topProcedure($stats, $catalog->id);
        $this->assertNotNull($top);
        $this->assertSame(1, $top['usage_count'], 'S4.2: only the non-optional item counts as usage.');
        $this->assertEqualsWithDelta(2.0, $top['total_quantity'], 0.001, 'S4.2: optional quantities stay out.');
        $this->assertEqualsWithDelta(100.0, $top['total_revenue'], 0.001, 'S4.2: optional revenue stays out.');

        $row = $this->specialtyRow($stats, $specialty->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['usage_count'], 'S4.2: the specialty distribution applies the same rule.');
    }

    public function test_cancelled_plan_items_are_excluded_from_usage_and_revenue(): void
    {
        $admin = $this->admin();
        $specialty = $this->specialty('S43');
        $catalog = $this->catalog('S43-CAN', $specialty);

        $approved = $this->plan($admin, 'approved', '2026-10-10 10:00:00');
        $this->item($approved, $catalog, 1, 50.0);

        $cancelled = $this->plan($admin, 'cancelled', '2026-10-11 10:00:00');
        $this->item($cancelled, $catalog, 5, 50.0);

        $stats = $this->stats($admin, ['from' => '2026-10-01', 'to' => '2026-10-31']);

        $top = $this->topProcedure($stats, $catalog->id);
        $this->assertNotNull($top);
        $this->assertSame(1, $top['usage_count'], 'S4.3: items of a cancelled plan never count.');
        $this->assertEqualsWithDelta(50.0, $top['total_revenue'], 0.001, 'S4.3: cancelled revenue stays out.');

        $row = $this->specialtyRow($stats, $specialty->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['usage_count'], 'S4.3: the specialty distribution applies the same rule.');
    }

    public function test_soft_deleted_plans_are_excluded_from_both_surfaces(): void
    {
        $admin = $this->admin();
        $specialty = $this->specialty('S44-TRASH');
        $catalog = $this->catalog('S44-TRASH', $specialty);
        $plan = $this->plan($admin, 'approved', '2026-10-10 10:00:00');
        $this->item($plan, $catalog);
        $plan->delete();

        $stats = $this->stats($admin, ['from' => '2026-10-01', 'to' => '2026-10-31']);

        $this->assertNull($this->topProcedure($stats, $catalog->id));
        $this->assertNull(
            $this->specialtyRow($stats, $specialty->id),
            'S4.4: the ranking and the specialty distribution MUST share the same population.'
        );
    }

    public function test_catalog_totals_ignore_the_period(): void
    {
        $admin = $this->admin();
        $this->catalog('S44-ACT-1');
        $this->catalog('S44-ACT-2');
        $this->catalog('S44-INA', null, false);

        $stats = $this->stats($admin, ['from' => '2026-01-01', 'to' => '2026-01-02']);

        $this->assertSame(3, $stats['catalog']['total']);
        $this->assertSame(2, $stats['catalog']['active']);
        $this->assertSame(1, $stats['catalog']['inactive']);
    }

    public function test_top_procedures_order_by_usage_and_respect_limit(): void
    {
        $admin = $this->admin();
        $most = $this->catalog('S44-MOST');
        $mid = $this->catalog('S44-MID');
        $least = $this->catalog('S44-LEAST');
        $plan = $this->plan($admin, 'approved', '2026-10-10 10:00:00');

        foreach ([[$most, 3], [$mid, 2], [$least, 1]] as [$catalog, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $this->item($plan, $catalog);
            }
        }

        $stats = $this->stats($admin, ['from' => '2026-10-01', 'to' => '2026-10-31', 'limit' => 2]);

        $codes = array_map(fn ($row) => $row['code'], $stats['top_procedures']);
        $this->assertCount(2, $codes, 'S4.4: `limit` caps the number of rows.');
        $this->assertSame(['S44-MOST', 'S44-MID'], $codes, 'S4.4: rows stay ordered by usage descending.');
    }

    public function test_revenue_is_unit_cost_times_quantity(): void
    {
        $admin = $this->admin();
        $catalog = $this->catalog('S44-REV');
        $plan = $this->plan($admin, 'approved', '2026-10-10 10:00:00');
        $this->item($plan, $catalog, 3, 33.33);

        $stats = $this->stats($admin, ['from' => '2026-10-01', 'to' => '2026-10-31']);

        $this->assertEqualsWithDelta(99.99, $this->topProcedure($stats, $catalog->id)['total_revenue'], 0.001);
    }

    public function test_items_without_specialty_land_in_the_null_bucket(): void
    {
        $admin = $this->admin();
        $catalog = $this->catalog('S44-NOSPEC');
        $plan = $this->plan($admin, 'approved', '2026-10-10 10:00:00');
        $this->item($plan, $catalog);

        $stats = $this->stats($admin, ['from' => '2026-10-01', 'to' => '2026-10-31']);

        $row = $this->specialtyRow($stats, null);
        $this->assertNotNull($row, 'S4.4: items without a specialty MUST keep their own bucket.');
        $this->assertSame(1, $row['usage_count']);
    }

    public function test_default_period_covers_the_last_90_days(): void
    {
        $admin = $this->admin();
        $inside = $this->catalog('S44-IN-90');
        $outside = $this->catalog('S44-OUT-90');

        $recentPlan = $this->plan($admin, 'approved', '2026-10-10 10:00:00');
        $this->item($recentPlan, $inside);

        $oldPlan = $this->plan($admin, 'approved', '2026-07-01 10:00:00');
        $this->item($oldPlan, $outside);

        $this->travelTo(Carbon::parse('2026-10-19 12:00:00'));
        $stats = $this->stats($admin);
        $this->travelBack();

        $this->assertNotNull($this->topProcedure($stats, $inside->id), 'S4.4: 80 days back is inside the default period.');
        $this->assertNull($this->topProcedure($stats, $outside->id), 'S4.4: 110 days back is outside the default period.');
    }

    public function test_response_shape_is_stable(): void
    {
        $admin = $this->admin();

        $stats = $this->stats($admin, ['from' => '2026-10-01', 'to' => '2026-10-31']);

        $this->assertSame(['period', 'catalog', 'top_procedures', 'by_specialty'], array_keys($stats));
        $this->assertSame(['from' => '2026-10-01', 'to' => '2026-10-31'], $stats['period']);
        $this->assertSame(['total', 'active', 'inactive'], array_keys($stats['catalog']));
    }

    public function test_limit_out_of_range_returns_422(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson(self::STATS_URL . '?limit=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);

        $this->actingAs($admin, 'sanctum')
            ->getJson(self::STATS_URL . '?limit=51')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_to_before_from_returns_422(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson(self::STATS_URL . '?from=2026-10-31&to=2026-10-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    }

    public function test_finanzas_can_read_the_report(): void
    {
        $this->actingAs($this->user('finanzas', 'fin_' . uniqid()), 'sanctum')
            ->getJson(self::STATS_URL)
            ->assertOk();
    }

    public function test_clinical_roles_are_forbidden(): void
    {
        $this->actingAs($this->user('odontologo', 'odo_' . uniqid()), 'sanctum')
            ->getJson(self::STATS_URL)
            ->assertStatus(403);
    }

    public function test_server_error_does_not_expose_the_exception_message(): void
    {
        $this->mock(ProcedureStatsService::class, function ($mock) {
            $mock->shouldReceive('getStats')
                ->once()
                ->andThrow(new \RuntimeException('SQLSTATE[HY000] secret=leaked-password'));
        });

        $response = $this->actingAs($this->admin(), 'sanctum')->getJson(self::STATS_URL);

        $response->assertStatus(500);
        $payload = $response->json();
        $this->assertArrayNotHasKey('error', $payload, 'S4.5: the 500 body MUST NOT carry the raw exception message.');
        $this->assertStringNotContainsString('leaked-password', $response->getContent());
        $this->assertSame('Error al obtener estadísticas de procedimientos', $payload['message']);
    }
}
