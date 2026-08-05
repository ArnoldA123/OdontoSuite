<?php

namespace Tests\Feature\Api;

use App\Models\AppointmentType;
use App\Models\AuditLog;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED test (slice 03, T-03.10) for AuditLog byX filters (BF-004 second pass).
 *
 * The apiResource('audit-logs') was already replaced with explicit GET pair
 * under role:administrador in slice 01 (T-01.2). Slice 03 verifies the
 * remaining byX filter endpoints return 200 with the right shape.
 *
 * @see specs/03-stubs-501-implement.md
 */
class AuditLogFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    public function test_by_patient_returns_only_patient_logs(): void
    {
        $admin = $this->admin();
        $patient = Patient::factory()->create();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'patient.viewed',
            'auditable_type' => Patient::class,
            'auditable_id' => $patient->id,
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'user.viewed',
            'auditable_type' => User::class,
            'auditable_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/audit-logs/patient/{$patient->id}");

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['patient_id', 'total']]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_by_user_returns_only_user_logs(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['role' => 'odontologo', 'is_active' => true]);

        AuditLog::create([
            'user_id' => $other->id,
            'action' => 'patient.viewed',
            'auditable_type' => Patient::class,
            'auditable_id' => 1,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/audit-logs/user/{$other->id}");

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['user_id', 'total']]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($other->id, $response->json('meta.user_id'));
    }

    public function test_by_dental_chair_returns_only_chair_logs(): void
    {
        $admin = $this->admin();
        $chair = DentalChair::factory()->create();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'chair.viewed',
            'auditable_type' => DentalChair::class,
            'auditable_id' => $chair->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/audit-logs/dental-chair/{$chair->id}");

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['chair_id', 'total']]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($chair->id, $response->json('meta.chair_id'));
    }

    public function test_by_appointment_type_returns_only_type_logs(): void
    {
        $admin = $this->admin();
        $type = AppointmentType::factory()->create();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'appointment_type.viewed',
            'auditable_type' => AppointmentType::class,
            'auditable_id' => $type->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/audit-logs/appointment-type/{$type->id}");

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['type_id', 'total']]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($type->id, $response->json('meta.type_id'));
    }

    public function test_by_patient_returns_404_for_missing_patient(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/audit-logs/patient/999999')
            ->assertNotFound();
    }

    public function test_non_admin_cannot_use_filters(): void
    {
        $user = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/audit-logs/patient/1')
            ->assertForbidden();
    }
}
