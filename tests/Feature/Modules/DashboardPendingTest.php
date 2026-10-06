<?php

namespace Tests\Feature\Modules;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\Quotation;
use App\Models\TreatmentPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * dashboard-ops-redesign / T7a: GET /api/dashboard/pending.
 *
 * Contract:
 * - `data.quotations` is present only for roles that can read the quotations module
 *   (mirrors `role:administrador,finanzas,odontologo,implantologo,recepcionista`).
 * - `data.treatment_plans` is present only for roles that can read the treatment-plans
 *   module (mirrors `role:administrador,odontologo,implantologo,tecnico_dental`).
 * - An unauthorized subset key is ABSENT from `data` (chosen approach; the alternative
 *   of a null value was rejected because absence is directly assertable).
 * - "Awaiting action" status definition, derived from the real model enums and the
 *   existing UI/transition rules:
 *     quotations: `sent` + `viewed` (issued, patient decision still pending).
 *       `draft` awaits internal completion and `approved`/`rejected`/`expired` are resolved.
 *     treatment_plans: `proposed` (awaiting acceptance). `draft` awaits internal
 *       completion, `approved`/`in_progress` are already accepted, the rest are terminal.
 * - Items are up to 5 most recent by `created_at`; `count` is the unbounded total.
 *
 * Runs against the MySQL harness because RefreshDatabase rebuilds every table:
 *   DB_SOCKET=/opt/lampp/var/mysql/mysql.sock DB_USERNAME=root DB_PASSWORD=<from .env> \
 *     php artisan test --configuration=phpunit.mysql.xml tests/Feature/Modules/DashboardPendingTest.php
 */
class DashboardPendingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase only resets once per process; seeded fixtures committed by
        // other tests leak into later tests that count rows, so wipe the involved
        // tables. Order respects foreign keys.
        Cache::flush();
        DB::table('appointments')->delete();
        DB::table('quotation_items')->delete();
        DB::table('quotations')->delete();
        DB::table('treatment_plan_items')->delete();
        DB::table('treatment_plans')->delete();
        DB::table('patients')->delete();
    }

    private function userWithRole(string $role): User
    {
        return User::create([
            'name' => 'Pending ' . ucfirst($role),
            'email' => 'pending.' . $role . '.' . uniqid('', true) . '@example.com',
            'username' => 'pending_' . $role . '_' . uniqid(),
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function patient(string $firstName = 'Pending', ?Branch $branch = null): Patient
    {
        return Patient::create([
            'first_name' => $firstName,
            'last_name' => 'Patient',
            'email' => 'pending.patient.' . uniqid('', true) . '@example.com',
            'phone' => '+51 9' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'branch_id' => $branch?->id,
            'is_active' => true,
        ]);
    }

    private function branch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'code' => 'PB' . strtoupper(Str::random(8)),
            'address' => 'Av. Pending 123',
            'city' => 'Lima',
            'is_active' => true,
        ]);
    }

    private function plan(
        User $author,
        Patient $patient,
        string $status,
        Carbon $createdAt,
        ?Branch $branch = null,
    ): TreatmentPlan {
        $plan = new TreatmentPlan([
            'patient_id' => $patient->id,
            'created_by' => $author->id,
            'plan_number' => 'TP-' . strtoupper(Str::random(8)),
            'title' => 'Plan ' . $status,
            'status' => $status,
            'total_cost' => 249.5,
            'final_cost' => 249.5,
            'branch_id' => $branch?->id,
        ]);
        $plan->created_at = $createdAt;
        $plan->updated_at = $createdAt;
        $plan->save();

        return $plan;
    }

    private function quotation(
        User $author,
        Patient $patient,
        TreatmentPlan $plan,
        string $status,
        Carbon $createdAt,
    ): Quotation {
        $quotation = new Quotation([
            'treatment_plan_id' => $plan->id,
            'patient_id' => $patient->id,
            'created_by' => $author->id,
            'quotation_number' => 'Q-' . strtoupper(Str::random(8)),
            'quotation_date' => $createdAt->toDateString(),
            'valid_until' => $createdAt->copy()->addDays(30)->toDateString(),
            'subtotal' => 149.9,
            'total_amount' => 149.9,
            'status' => $status,
        ]);
        $quotation->created_at = $createdAt;
        $quotation->updated_at = $createdAt;
        $quotation->save();

        return $quotation;
    }

    public function test_admin_receives_both_subsets_with_only_awaiting_action_items(): void
    {
        $admin = $this->userWithRole('administrador');
        $patient = $this->patient('Ada');

        // Fixture plan for quotations; draft is excluded from pending plans.
        $fixturePlan = $this->plan($admin, $patient, 'draft', now()->subHours(12));
        $excludedPlan = $this->plan($admin, $patient, 'approved', now()->subHours(11));

        $olderSent = $this->quotation($admin, $patient, $fixturePlan, 'sent', now()->subHours(2));
        $newerViewed = $this->quotation($admin, $patient, $fixturePlan, 'viewed', now()->subHour());
        $this->quotation($admin, $patient, $fixturePlan, 'draft', now()->subMinutes(30));
        $this->quotation($admin, $patient, $fixturePlan, 'approved', now()->subMinutes(20));
        $this->quotation($admin, $patient, $fixturePlan, 'rejected', now()->subMinutes(10));

        $olderProposed = $this->plan($admin, $patient, 'proposed', now()->subHours(4));
        $newerProposed = $this->plan($admin, $patient, 'proposed', now()->subMinutes(5));

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/pending');

        $response->assertOk();

        $quotations = $response->json('data.quotations');
        $this->assertSame(2, $quotations['count'], 'Only sent + viewed quotations count as awaiting action.');
        $this->assertCount(2, $quotations['items']);
        $this->assertSame($newerViewed->id, $quotations['items'][0]['id'], 'Most recent quotation comes first.');
        $this->assertSame($olderSent->id, $quotations['items'][1]['id']);
        $this->assertSame('Ada Patient', $quotations['items'][0]['patient_name']);
        $this->assertSame('viewed', $quotations['items'][0]['status']);
        $this->assertEqualsWithDelta(149.9, $quotations['items'][0]['total_amount'], 0.001);
        $this->assertArrayHasKey('created_at', $quotations['items'][0]);
        $this->assertNotNull($quotations['items'][0]['created_at']);

        $plans = $response->json('data.treatment_plans');
        $this->assertSame(2, $plans['count'], 'Only proposed plans count as needing attention.');
        $this->assertCount(2, $plans['items']);
        $this->assertSame($newerProposed->id, $plans['items'][0]['id'], 'Most recent plan comes first.');
        $this->assertSame($olderProposed->id, $plans['items'][1]['id']);
        $this->assertSame('Ada Patient', $plans['items'][0]['patient_name']);
        $this->assertSame('Plan proposed', $plans['items'][0]['title']);
        $this->assertSame('proposed', $plans['items'][0]['status']);
        $this->assertEqualsWithDelta(249.5, $plans['items'][0]['final_cost'], 0.001);
        $this->assertArrayHasKey('created_at', $plans['items'][0]);

        $this->assertNotContains($excludedPlan->id, array_column($plans['items'], 'id'));

        $this->assertIsString($response->json('meta.message'));
        $this->assertNotNull($response->json('meta.generated_at'));
        $this->assertTrue($response->json('meta.cached'));
    }

    public function test_items_cap_at_five_most_recent_while_count_stays_total(): void
    {
        $admin = $this->userWithRole('administrador');
        $patient = $this->patient();
        $fixturePlan = $this->plan($admin, $patient, 'draft', now()->subHours(13));

        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            // $ids[0] oldest ... $ids[5] newest
            $ids[] = $this->quotation($admin, $patient, $fixturePlan, 'sent', now()->subHours(6 - $i))->id;
        }

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/pending');
        $response->assertOk();

        $quotations = $response->json('data.quotations');
        $this->assertSame(6, $quotations['count']);
        $this->assertCount(5, $quotations['items']);
        $this->assertSame($ids[5], $quotations['items'][0]['id']);
        $this->assertSame($ids[1], $quotations['items'][4]['id']);
        $this->assertNotContains($ids[0], array_column($quotations['items'], 'id'), 'The oldest of six items must be dropped.');
    }

    public function test_recepcionista_sees_quotations_but_not_treatment_plans(): void
    {
        $receptionist = $this->userWithRole('recepcionista');
        $admin = $this->userWithRole('administrador');
        $patient = $this->patient();
        $fixturePlan = $this->plan($admin, $patient, 'draft', now()->subHours(2));
        $this->quotation($admin, $patient, $fixturePlan, 'sent', now()->subHour());
        $this->plan($admin, $patient, 'proposed', now()->subHour());

        $response = $this->actingAs($receptionist, 'sanctum')->getJson('/api/dashboard/pending');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('quotations', $data);
        $this->assertArrayNotHasKey('treatment_plans', $data);
        $this->assertSame(1, $data['quotations']['count']);
    }

    public function test_tecnico_dental_sees_treatment_plans_but_not_quotations(): void
    {
        $technician = $this->userWithRole('tecnico_dental');
        $admin = $this->userWithRole('administrador');
        $patient = $this->patient();
        $fixturePlan = $this->plan($admin, $patient, 'draft', now()->subHours(2));
        $this->quotation($admin, $patient, $fixturePlan, 'sent', now()->subHour());
        $this->plan($admin, $patient, 'proposed', now()->subHour());

        $response = $this->actingAs($technician, 'sanctum')->getJson('/api/dashboard/pending');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayNotHasKey('quotations', $data);
        $this->assertArrayHasKey('treatment_plans', $data);
        $this->assertSame(1, $data['treatment_plans']['count']);
    }

    public function test_asistente_receives_no_subsets(): void
    {
        $assistant = $this->userWithRole('asistente');
        $admin = $this->userWithRole('administrador');
        $patient = $this->patient();
        $fixturePlan = $this->plan($admin, $patient, 'draft', now()->subHours(2));
        $this->quotation($admin, $patient, $fixturePlan, 'sent', now()->subHour());
        $this->plan($admin, $patient, 'proposed', now()->subHour());

        $response = $this->actingAs($assistant, 'sanctum')->getJson('/api/dashboard/pending');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('quotations', $data);
        $this->assertArrayNotHasKey('treatment_plans', $data);
    }

    public function test_branch_filter_narrows_both_subsets(): void
    {
        $admin = $this->userWithRole('administrador');
        $branchA = $this->branch('Branch Pending A');
        $branchB = $this->branch('Branch Pending B');

        $patientA = $this->patient('Ana', $branchA);
        $patientB = $this->patient('Beto', $branchB);

        $fixturePlanA = $this->plan($admin, $patientA, 'draft', now()->subHours(3), $branchA);
        $fixturePlanB = $this->plan($admin, $patientB, 'draft', now()->subHours(3), $branchB);
        $this->quotation($admin, $patientA, $fixturePlanA, 'sent', now()->subHour());
        $this->quotation($admin, $patientB, $fixturePlanB, 'sent', now()->subHour());
        $this->plan($admin, $patientA, 'proposed', now()->subHour(), $branchA);
        $this->plan($admin, $patientB, 'proposed', now()->subHour(), $branchB);

        $filtered = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/pending?branch_id=' . $branchA->id);

        $filtered->assertOk();
        $this->assertSame(1, $filtered->json('data.quotations.count'));
        $this->assertSame('Ana Patient', $filtered->json('data.quotations.items.0.patient_name'));
        $this->assertSame(1, $filtered->json('data.treatment_plans.count'));
        $this->assertSame('Ana Patient', $filtered->json('data.treatment_plans.items.0.patient_name'));

        $unfiltered = $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard/pending');
        $unfiltered->assertOk();
        $this->assertSame(2, $unfiltered->json('data.quotations.count'));
        $this->assertSame(2, $unfiltered->json('data.treatment_plans.count'));
    }
}
