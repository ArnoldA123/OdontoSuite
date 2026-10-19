<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD contract for the appointment-types admin resource.
 *
 * Acceptance (2026-10-19 audit, findings F1/F6):
 *  - DELETE refuses (409) while the type still has appointments, and the
 *    appointments survive: `appointments.appointment_type_id` cascades at the
 *    FK level, so a hard delete used to wipe every appointment of the type.
 *  - DELETE still removes a type with no appointments.
 *  - update/destroy answer 404 (not 500) for an unknown id.
 */
class AppointmentTypeCrudContractTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    public function test_destroy_refuses_to_delete_a_type_that_still_has_appointments(): void
    {
        $admin = $this->admin();
        $type = AppointmentType::factory()->create();
        $appointment = Appointment::factory()->create(['appointment_type_id' => $type->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/appointment-types/{$type->id}");

        $response->assertStatus(409)->assertJsonStructure(['message']);

        $this->assertStringContainsString(
            'cita',
            strtolower($response->json('message')),
            'The refusal must explain that the type still has appointments.'
        );

        $this->assertDatabaseHas('appointment_types', ['id' => $type->id]);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'appointment_type_id' => $type->id,
        ]);
    }

    public function test_destroy_deletes_a_type_without_appointments(): void
    {
        $admin = $this->admin();
        $type = AppointmentType::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/appointment-types/{$type->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('appointment_types', ['id' => $type->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'appointment_type_deleted',
            'auditable_id' => $type->id,
        ]);
    }

    public function test_update_returns_404_for_an_unknown_type(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/appointment-types/999999', ['name' => 'Fantasma']);

        $response->assertStatus(404)->assertJsonStructure(['message']);
        $this->assertDatabaseMissing('appointment_types', ['name' => 'Fantasma']);
    }

    public function test_destroy_returns_404_for_an_unknown_type(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson('/api/appointment-types/999999');

        $response->assertStatus(404)->assertJsonStructure(['message']);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'appointment_type_deleted',
            'auditable_id' => 999999,
        ]);
    }

    public function test_show_returns_404_for_an_unknown_type(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/appointment-types/999999');

        $response->assertStatus(404)->assertJsonStructure(['message']);
    }

    public function test_show_still_returns_the_type_with_its_audit_logs(): void
    {
        $admin = $this->admin();
        $type = AppointmentType::factory()->create(['name' => 'Limpieza dental']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/appointment-types/{$type->id}", ['price' => 150])
            ->assertOk();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/appointment-types/{$type->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Limpieza dental')
            ->assertJsonCount(1, 'data.audit_logs');
    }

    public function test_update_still_saves_the_submitted_fields(): void
    {
        $type = AppointmentType::factory()->create([
            'name' => 'Consulta',
            'price' => 50,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/appointment-types/{$type->id}", [
                'name' => 'Consulta general',
                'price' => 75.5,
                'is_active' => false,
            ]);

        $response->assertOk()->assertJsonPath('data.name', 'Consulta general');
        $this->assertDatabaseHas('appointment_types', [
            'id' => $type->id,
            'name' => 'Consulta general',
            'price' => 75.5,
            'is_active' => false,
        ]);
    }
}
