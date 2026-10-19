<?php

namespace Tests\Feature\Modules;

use App\Models\Appointment;
use App\Models\AppointmentBlock;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * environments-audit / T1-T4: lifecycle contract for the Ambientes module.
 *
 * Covers the defects found in the 2026-10-19 audit:
 * - deleting an environment must never cascade-delete appointments or blocks;
 * - the generated AMB-### code is unique and sequential even after deletions;
 * - missing ids resolve to 404, not 500 with an internal SQL message;
 * - index/search share the same visibility rule and a stable order, and the
 *   active endpoint only offers operational chairs for scheduling.
 *
 * Runs against the MySQL harness with an isolated database:
 *   DB_SOCKET=/opt/lampp/var/mysql/mysql.sock DB_DATABASE=odontosuite_env_audit_test \
 *   DB_USERNAME=root DB_PASSWORD=<from .env> \
 *   vendor/bin/phpunit -c phpunit.mysql.xml --filter=DentalChairLifecycleTest
 */
class DentalChairLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Test Admin',
            'email' => 'admin.env.audit@example.com',
            'username' => 'admin_env_audit',
            'password' => bcrypt('password'),
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function doctor(): User
    {
        return User::create([
            'name' => 'Test Doctor',
            'email' => 'doctor.env.audit@example.com',
            'username' => 'doctor_env_audit',
            'password' => bcrypt('password'),
            'role' => 'odontologo',
            'is_active' => true,
        ]);
    }

    private function chair(string $code, string $status = 'active', bool $isActive = true): DentalChair
    {
        return DentalChair::create([
            'name' => 'Chair ' . $code,
            'code' => $code,
            'description' => 'Fixture',
            'equipment' => 'Fixture equipment',
            'status' => $status,
            'is_active' => $isActive,
        ]);
    }

    private function appointmentFor(DentalChair $chair, User $doctor): Appointment
    {
        $patient = Patient::create([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'email' => 'patient.env.audit@example.com',
            'is_active' => true,
        ]);

        $type = AppointmentType::create([
            'name' => 'Control',
            'default_duration_minutes' => 30,
            'requires_confirmation' => true,
            'is_active' => true,
        ]);

        $scheduledAt = now()->addDay()->seconds(0);

        return Appointment::create([
            'patient_id' => $patient->id,
            'user_id' => $doctor->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => $type->id,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'created_by' => $doctor->id,
        ]);
    }

    private function blockFor(DentalChair $chair, User $admin): AppointmentBlock
    {
        $startsAt = now()->addDay()->seconds(0);

        return AppointmentBlock::create([
            'user_id' => null,
            'dental_chair_id' => $chair->id,
            'title' => 'Mantenimiento',
            'reason' => 'Mantenimiento programado',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'type' => 'maintenance',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    }

    /**
     * Keep assertions independent from rows seeded by sibling suites: the CI
     * process can carry a seeded database into this class, so exact listing
     * equality would fail for reasons unrelated to this contract.
     *
     * @param  array<int, string>  $only
     * @return array<int, string>
     */
    private function listedCodes($response, array $only): array
    {
        $codes = collect($response->json('data'))->pluck('code')->all();

        return array_values(array_intersect($codes, $only));
    }

    /**
     * The next free numeric AMB code in the current database, seeded rows
     * included. Codes outside the AMB-### namespace are ignored.
     */
    private function nextAmbNumber(): int
    {
        $max = 0;

        foreach (DentalChair::pluck('code') as $code) {
            if (preg_match('/^AMB-(\d+)$/', $code, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max + 1;
    }

    private function ambCode(int $number): string
    {
        return 'AMB-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    public function test_destroy_is_blocked_when_the_environment_has_appointments(): void
    {
        $admin = $this->admin();
        $chair = $this->chair('AMB-LC-DEL-001');
        $appointment = $this->appointmentFor($chair, $this->doctor());

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/dental-chairs/{$chair->id}")
            ->assertStatus(409)
            ->assertJson([
                'message' => 'No se puede eliminar el ambiente porque tiene citas o bloqueos asociados.',
            ]);

        $this->assertDatabaseHas('dental_chairs', ['id' => $chair->id]);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
    }

    public function test_destroy_is_blocked_when_only_a_soft_deleted_appointment_references_it(): void
    {
        $chair = $this->chair('AMB-LC-DEL-002');
        $appointment = $this->appointmentFor($chair, $this->doctor());
        $appointment->delete();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/dental-chairs/{$chair->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('dental_chairs', ['id' => $chair->id]);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'deleted_at' => $appointment->deleted_at]);
    }

    public function test_destroy_is_blocked_when_the_environment_has_appointment_blocks(): void
    {
        $admin = $this->admin();
        $chair = $this->chair('AMB-LC-DEL-003');
        $block = $this->blockFor($chair, $admin);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/dental-chairs/{$chair->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('dental_chairs', ['id' => $chair->id]);
        $this->assertDatabaseHas('appointment_blocks', ['id' => $block->id]);
    }

    public function test_destroy_removes_an_environment_without_relations_and_records_audit(): void
    {
        $admin = $this->admin();
        $chair = $this->chair('AMB-LC-DEL-004');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/dental-chairs/{$chair->id}")
            ->assertOk()
            ->assertJson(['meta' => ['message' => 'Ambiente eliminado exitosamente']]);

        $this->assertDatabaseMissing('dental_chairs', ['id' => $chair->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'dental_chair_deleted',
            'auditable_id' => $chair->id,
        ]);
    }

    public function test_store_generates_the_next_sequential_code_after_a_deletion(): void
    {
        $start = $this->nextAmbNumber();
        $this->chair($this->ambCode($start));
        $deleted = $this->chair($this->ambCode($start + 1));
        $this->chair($this->ambCode($start + 2));
        $deleted->delete();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/dental-chairs', [
                'name' => 'Nuevo ambiente',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', $this->ambCode($start + 3));
    }

    public function test_store_does_not_reuse_a_code_that_is_still_taken(): void
    {
        $start = $this->nextAmbNumber();
        $this->chair($this->ambCode($start));
        $this->chair($this->ambCode($start + 1));
        $this->chair($this->ambCode($start + 3));

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/dental-chairs', [
                'name' => 'Nuevo ambiente',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', $this->ambCode($start + 4));
    }

    public function test_update_returns_404_for_a_missing_environment(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/dental-chairs/999999', [
                'name' => 'Fantasma',
                'status' => 'active',
            ])
            ->assertNotFound()
            ->assertJson(['message' => 'Ambiente no encontrado']);
    }

    public function test_destroy_returns_404_for_a_missing_environment(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson('/api/dental-chairs/999999')
            ->assertNotFound()
            ->assertJson(['message' => 'Ambiente no encontrado']);
    }

    public function test_show_returns_404_for_a_missing_environment(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dental-chairs/999999')
            ->assertNotFound()
            ->assertJson(['message' => 'Ambiente no encontrado']);
    }

    public function test_index_and_search_share_visibility_and_sort_by_name(): void
    {
        $this->chair('AMB-ZETA');
        $this->chair('AMB-ALFA');
        $this->chair('AMB-ARCHIVED', 'active', false);

        $admin = $this->admin();

        $index = $this->actingAs($admin, 'sanctum')->getJson('/api/dental-chairs');
        $index->assertOk();
        $fixtures = ['AMB-ALFA', 'AMB-ZETA', 'AMB-ARCHIVED'];
        $this->assertSame(['AMB-ALFA', 'AMB-ZETA'], $this->listedCodes($index, $fixtures));

        $search = $this->actingAs($admin, 'sanctum')->getJson('/api/dental-chairs/search?q=AMB');
        $search->assertOk();
        $this->assertSame(['AMB-ALFA', 'AMB-ZETA'], $this->listedCodes($search, $fixtures));
    }

    public function test_active_endpoint_filters_by_operational_status_on_request(): void
    {
        $this->chair('AMB-ACTIVE', 'active');
        $this->chair('AMB-INACTIVE', 'inactive');
        $this->chair('AMB-MAINTENANCE', 'maintenance');

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dental-chairs/active?status=active');

        $response->assertOk();
        $this->assertSame(
            ['AMB-ACTIVE'],
            $this->listedCodes($response, ['AMB-ACTIVE', 'AMB-INACTIVE', 'AMB-MAINTENANCE'])
        );
    }

    public function test_active_endpoint_keeps_the_active_flag_contract_for_other_consumers(): void
    {
        $this->chair('AMB-ACTIVE', 'active');
        $this->chair('AMB-INACTIVE', 'inactive');
        $this->chair('AMB-MAINTENANCE', 'maintenance');
        $this->chair('AMB-ARCHIVED', 'active', false);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dental-chairs/active');

        $response->assertOk();
        $this->assertSame(
            ['AMB-ACTIVE', 'AMB-INACTIVE', 'AMB-MAINTENANCE'],
            $this->listedCodes($response, ['AMB-ACTIVE', 'AMB-INACTIVE', 'AMB-MAINTENANCE', 'AMB-ARCHIVED'])
        );
    }

    public function test_index_can_include_or_isolate_archived_environments(): void
    {
        $this->chair('AMB-LIVE', 'active');
        $this->chair('AMB-ARCHIVED', 'maintenance', false);

        $admin = $this->admin();

        $fixtures = ['AMB-LIVE', 'AMB-ARCHIVED'];

        $default = $this->actingAs($admin, 'sanctum')->getJson('/api/dental-chairs');
        $default->assertOk();
        $this->assertSame(['AMB-LIVE'], $this->listedCodes($default, $fixtures));

        $include = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dental-chairs?archived=include');
        $include->assertOk();
        $this->assertSame(['AMB-ARCHIVED', 'AMB-LIVE'], $this->listedCodes($include, $fixtures));

        $only = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dental-chairs?archived=only');
        $only->assertOk();
        $this->assertSame(['AMB-ARCHIVED'], $this->listedCodes($only, $fixtures));
    }

    public function test_search_can_include_archived_environments(): void
    {
        $this->chair('AMB-LIVE', 'active');
        $this->chair('AMB-ARCHIVED', 'maintenance', false);

        $admin = $this->admin();

        $fixtures = ['AMB-LIVE', 'AMB-ARCHIVED'];

        $default = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dental-chairs/search?q=AMB');
        $default->assertOk();
        $this->assertSame(['AMB-LIVE'], $this->listedCodes($default, $fixtures));

        $include = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dental-chairs/search?q=AMB&archived=include');
        $include->assertOk();
        $this->assertSame(['AMB-ARCHIVED', 'AMB-LIVE'], $this->listedCodes($include, $fixtures));
    }

    public function test_invalid_archived_filter_returns_422(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dental-chairs?archived=bogus')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archived']);
    }
}
