<?php

namespace Tests\Feature\Modules;

use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Contract tests for the treatment-plans summary endpoint that feeds the
 * module KPI strip (treatment-plans-ui-redesign, S3).
 *
 * The endpoint summarizes the WHOLE table, never the visible page: the
 * assertions compare each field against the database after crafting plans, and
 * then assert the exact delta each crafted row produced.
 *
 * Runs against MySQL: `php artisan test --configuration=phpunit.mysql.xml`
 */
class TreatmentPlansSummaryTest extends TestCase
{
    use DatabaseTransactions;

    private const ACTIVE_STATUSES = ['draft', 'proposed', 'approved', 'in_progress'];

    private const ALL_STATUSES = ['draft', 'proposed', 'approved', 'in_progress', 'completed', 'cancelled'];

    private function actingUser(string $role = 'administrador'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function makePlan(Patient $patient, User $author, string $status, float $finalCost, ?string $endDate = null): TreatmentPlan
    {
        return TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $author->id,
            'status' => $status,
            'total_cost' => $finalCost,
            'discount_amount' => 0,
            'final_cost' => $finalCost,
            'end_date' => $endDate,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryFor(User $user): array
    {
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/treatment-plans/summary');
        $response->assertOk();

        return $response->json('data');
    }

    public function test_summary_agrees_with_the_whole_table_for_every_field(): void
    {
        $admin = $this->actingUser();

        $data = $this->summaryFor($admin);

        $this->assertSame(TreatmentPlan::query()->count(), $data['total']);

        $this->assertEqualsCanonicalizing(self::ALL_STATUSES, array_keys($data['by_status']));
        foreach (self::ALL_STATUSES as $status) {
            $this->assertSame(
                TreatmentPlan::query()->where('status', $status)->count(),
                $data['by_status'][$status],
                sprintf('by_status.%s must count the whole table, not the visible page (S3).', $status)
            );
        }

        $this->assertSame(
            TreatmentPlan::query()->whereIn('status', self::ACTIVE_STATUSES)->count(),
            $data['active'],
            'active must count every non-terminal plan (S3).'
        );
        $this->assertSame(
            TreatmentPlan::query()
                ->whereNotNull('end_date')
                ->where('end_date', '<', now()->toDateString())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count(),
            $data['overdue'],
            'overdue must count every plan past its end date that is still open (S3).'
        );
        $this->assertSame(
            TreatmentPlan::query()->where('status', 'proposed')->count(),
            $data['pending_approval'],
            'pending_approval must count the plans waiting for approval (S3).'
        );
        $this->assertEquals(
            round((float) TreatmentPlan::query()->whereIn('status', self::ACTIVE_STATUSES)->sum('final_cost'), 2),
            $data['pending_cost'],
            'pending_cost must sum the final cost of every open plan (S3).'
        );
    }

    public function test_summary_moves_by_the_exact_delta_of_the_plans_that_are_created(): void
    {
        $admin = $this->actingUser();
        $patient = Patient::factory()->create();

        $before = $this->summaryFor($admin);

        $overdueDate = now()->subDays(2)->toDateString();
        $this->makePlan($patient, $admin, 'draft', 100.00);
        $this->makePlan($patient, $admin, 'draft', 200.00);
        $this->makePlan($patient, $admin, 'proposed', 300.00, $overdueDate);
        $this->makePlan($patient, $admin, 'approved', 400.00);
        $this->makePlan($patient, $admin, 'in_progress', 500.00);
        $this->makePlan($patient, $admin, 'completed', 600.00);
        $this->makePlan($patient, $admin, 'cancelled', 700.00);

        $after = $this->summaryFor($admin);

        $this->assertSame($before['total'] + 7, $after['total']);
        $this->assertSame($before['by_status']['draft'] + 2, $after['by_status']['draft']);
        $this->assertSame($before['by_status']['proposed'] + 1, $after['by_status']['proposed']);
        $this->assertSame($before['by_status']['approved'] + 1, $after['by_status']['approved']);
        $this->assertSame($before['by_status']['in_progress'] + 1, $after['by_status']['in_progress']);
        $this->assertSame($before['by_status']['completed'] + 1, $after['by_status']['completed']);
        $this->assertSame($before['by_status']['cancelled'] + 1, $after['by_status']['cancelled']);
        $this->assertSame($before['active'] + 5, $after['active']);
        $this->assertSame($before['overdue'] + 1, $after['overdue']);
        $this->assertSame($before['pending_approval'] + 1, $after['pending_approval']);
        $this->assertEqualsWithDelta(
            $before['pending_cost'] + 1500.00,
            $after['pending_cost'],
            0.01,
            'pending_cost must add the open plans only: 100 + 200 + 300 + 400 + 500 (S3).'
        );
    }

    public function test_summary_requires_authentication(): void
    {
        $this->getJson('/api/treatment-plans/summary')->assertStatus(401);
    }

    public function test_summary_keeps_the_treatment_plans_role_gate(): void
    {
        $receptionist = $this->actingUser('recepcionista');

        $this->actingAs($receptionist, 'sanctum')
            ->getJson('/api/treatment-plans/summary')
            ->assertStatus(403);
    }
}
