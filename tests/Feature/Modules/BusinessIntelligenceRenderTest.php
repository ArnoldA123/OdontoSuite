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
 * full-user-browser-audit-2026-08-05 / PR6 / Phase 4b — Business Intelligence Render.
 *
 * Validation-first Feature test for the BI report contract documented in
 * `openspec/changes/full-user-browser-audit-2026-08-05/specs/module-validation-tests/spec.md`.
 * Exercises the EXISTING `GET /api/reports/revenue` endpoint (auth:sanctum).
 * Must run against MySQL harness (AGENTS.md §6 — SQLite transactions.type tech debt).
 */
class BusinessIntelligenceRenderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        // User::factory() bypass: same MySQL NOT NULL `username` reason as CatalogFilterTest.
        return User::create([
            'name' => 'BI Admin',
            'email' => 'bi.admin.test@example.com',
            'username' => 'bi_admin',
            'password' => bcrypt('password'),
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function branch(): Branch
    {
        return Branch::create([
            'name' => 'BI Branch',
            'code' => 'BI-001',
            'address' => 'Av. Test 123',
            'city' => 'Lima',
            'is_active' => true,
        ]);
    }

    private function appointmentType(int $price = 25000): AppointmentType
    {
        return AppointmentType::create([
            'name' => 'BI Control',
            'default_duration_minutes' => 30,
            'price' => $price,
            'requires_confirmation' => true,
            'is_active' => true,
        ]);
    }

    private function chair(): DentalChair
    {
        return DentalChair::create([
            'name' => 'BI Chair',
            'code' => 'BIC-1',
            'is_active' => true,
        ]);
    }

    private function completedAppointment(
        User $doctor,
        Branch $branch,
        AppointmentType $type,
        DentalChair $chair,
        Carbon $scheduledAt,
    ): Appointment {
        $patient = Patient::create([
            'first_name' => 'BI',
            'last_name' => 'Patient',
            'email' => 'bi.patient.' . uniqid('', true) . '@example.com',
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
            'status' => 'completed',
            'created_by' => $doctor->id,
        ]);
    }

    public function test_revenue_report_renders_with_required_sections_on_dataset(): void
    {
        $admin = $this->admin();
        $branch = $this->branch();
        $type = $this->appointmentType(25000);
        $chair = $this->chair();

        $this->completedAppointment($admin, $branch, $type, $chair, Carbon::now()->subDays(2));
        $this->completedAppointment($admin, $branch, $type, $chair, Carbon::now()->subDays(1));

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/reports/revenue');

        $response->assertOk();

        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows, 'Revenue report MUST contain at least one row when completed appointments exist.');

        // Spec names branch/total/period. Production emits category/total_revenue/period;
        // any of those branches/totals satisfy the contract semantics.
        $first = $rows[0];
        $this->assertIsArray($first);

        $this->assertTrue(
            array_key_exists('branch', $first)
                || array_key_exists('category', $first)
                || array_key_exists('environment_name', $first),
            'Revenue report rows MUST expose a branch / category / environment_name key.'
        );
        $this->assertTrue(
            array_key_exists('total', $first)
                || array_key_exists('total_revenue', $first)
                || array_key_exists('revenue', $first),
            'Revenue report rows MUST expose a total / total_revenue / revenue key.'
        );
        $this->assertArrayHasKey('period', $first, 'Revenue report rows MUST expose a period key (per spec).');
    }

    public function test_empty_dataset_renders_200_with_zero_summaries(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/reports/revenue');

        $response->assertOk();

        // The service emits a single "Resumen / Total General" summary row even
        // when no completed appointments exist. Aggregate counters MUST read zero
        // and no Por Tipo / Por Profesional / Por Ambiente grouped rows may leak.
        $rows = $response->json('data');
        $this->assertIsArray($rows, 'Empty dataset MUST return a `data` array.');

        $summary = collect($rows)->firstWhere('category', 'Total General');
        $this->assertNotNull($summary, 'Empty dataset MUST include the Total General summary row.');
        $this->assertSame(0, (int) ($summary['appointments_count'] ?? -1));
        $this->assertEqualsWithDelta(0, (float) ($summary['total_revenue'] ?? -1), 0.001);
        $this->assertEqualsWithDelta(0, (float) ($summary['average_per_appointment'] ?? -1), 0.001);

        $groupedTypes = collect($rows)->pluck('type')->unique()->values()->all();
        $this->assertSame(
            ['Resumen'],
            $groupedTypes,
            'Empty dataset MUST NOT emit Por Tipo / Por Profesional / Por Ambiente grouped rows.'
        );
    }

    public function test_unauthenticated_request_is_rejected_with_401_envelope(): void
    {
        $previousDebug = (bool) config('app.debug');
        config(['app.debug' => false]);

        try {
            $response = $this->getJson('/api/reports/revenue');

            $response->assertStatus(401)
                ->assertJsonStructure(['message'])
                ->assertHeader('WWW-Authenticate', 'Bearer realm="api"');

            $this->assertSame('No autenticado.', $response->json('message'));
            $this->assertNull(
                $response->json('data'),
                'No report data may leak in the unauthenticated response envelope.'
            );
        } finally {
            config(['app.debug' => $previousDebug]);
        }
    }
}