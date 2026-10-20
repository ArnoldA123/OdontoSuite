<?php

namespace Tests\Feature\Modules;

use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Contract tests for the treatment-plans index filters the redesigned list
 * surface depends on (treatment-plans-ui-redesign, S4 + L5).
 *
 * The quick-filter pills used to send `status=active`, `status=mine` and
 * `status=overdue` into an exact-match filter, so three of the five presets
 * always returned an empty list. These tests pin the additive filters that make
 * them truthful (`statuses` list, `overdue`) and prove the pre-existing exact
 * `status` / `created_by` filters keep working.
 *
 * Runs against MySQL: `php artisan test --configuration=phpunit.mysql.xml`
 */
class TreatmentPlansIndexFiltersTest extends TestCase
{
    use DatabaseTransactions;

    private function actingUser(string $role = 'administrador'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function makePlan(Patient $patient, User $author, string $status, ?string $endDate = null): TreatmentPlan
    {
        return TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $author->id,
            'status' => $status,
            'final_cost' => 0,
            'end_date' => $endDate,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function listedIds(User $user, string $query): array
    {
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/treatment-plans?'.$query);
        $response->assertOk();

        return array_column($response->json('data'), 'id');
    }

    public function test_statuses_filter_returns_the_matching_open_plans_only(): void
    {
        $admin = $this->actingUser();
        $patient = Patient::factory()->create();

        $draft = $this->makePlan($patient, $admin, 'draft');
        $proposed = $this->makePlan($patient, $admin, 'proposed');
        $approved = $this->makePlan($patient, $admin, 'approved');
        $completed = $this->makePlan($patient, $admin, 'completed');

        $ids = $this->listedIds($admin, 'statuses=draft,proposed');

        $this->assertContains($draft->id, $ids);
        $this->assertContains($proposed->id, $ids);
        $this->assertNotContains($approved->id, $ids);
        $this->assertNotContains($completed->id, $ids);
    }

    public function test_statuses_filter_ignores_unknown_values_without_failing(): void
    {
        $admin = $this->actingUser();
        $patient = Patient::factory()->create();
        $draft = $this->makePlan($patient, $admin, 'draft');

        $this->assertContains($draft->id, $this->listedIds($admin, 'statuses=draft,not-a-status'));

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/treatment-plans?statuses=not-a-status');
        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_overdue_filter_returns_open_plans_past_their_end_date(): void
    {
        $admin = $this->actingUser();
        $patient = Patient::factory()->create();

        $overdue = $this->makePlan($patient, $admin, 'approved', now()->subDays(3)->toDateString());
        $future = $this->makePlan($patient, $admin, 'approved', now()->addDays(3)->toDateString());
        $closedOverdue = $this->makePlan($patient, $admin, 'completed', now()->subDays(3)->toDateString());
        $noEndDate = $this->makePlan($patient, $admin, 'in_progress');

        $ids = $this->listedIds($admin, 'overdue=1');

        $this->assertContains($overdue->id, $ids);
        $this->assertNotContains($future->id, $ids);
        $this->assertNotContains($closedOverdue->id, $ids);
        $this->assertNotContains($noEndDate->id, $ids);
    }

    public function test_statuses_and_overdue_filters_combine(): void
    {
        $admin = $this->actingUser();
        $patient = Patient::factory()->create();
        $past = now()->subDays(3)->toDateString();

        $draftOverdue = $this->makePlan($patient, $admin, 'draft', $past);
        $completedOverdue = $this->makePlan($patient, $admin, 'completed', $past);

        $ids = $this->listedIds($admin, 'statuses=draft,proposed&overdue=1');

        $this->assertContains($draftOverdue->id, $ids);
        $this->assertNotContains($completedOverdue->id, $ids);
    }

    public function test_exact_status_and_created_by_filters_keep_working(): void
    {
        $admin = $this->actingUser();
        $otherAuthor = $this->actingUser('odontologo');
        $patient = Patient::factory()->create();

        $mine = $this->makePlan($patient, $admin, 'completed');
        $theirs = $this->makePlan($patient, $otherAuthor, 'completed');

        $ids = $this->listedIds($admin, 'status=completed&created_by='.$admin->id);

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }
}
