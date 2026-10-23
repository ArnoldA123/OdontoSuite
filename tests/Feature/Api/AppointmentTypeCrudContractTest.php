<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\ProcedureCatalog;
use App\Models\Product;
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
 *  - the catalog flags, the default procedure reference and the duration are
 *    writable through the documented contract (`default_duration_minutes` is
 *    the resource field, so an echoed resource must not be a silent no-op).
 *  - `color` is a hex triplet, not any string up to seven characters.
 *  - `requires_materials` (#295 S7) is published derived from the recipe of the
 *    default procedure. The column keeps accepting the flag the form sends, but
 *    the response no longer echoes it.
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

    public function test_store_persists_the_catalog_flags(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/appointment-types', [
            'name' => 'Consulta de valoracion',
            'duration_minutes' => 45,
            'color' => '#10B981',
            'requires_confirmation' => true,
            'requires_materials' => true,
            'is_consultation_mode' => true,
        ]);

        // El flag legacy se acepta y se guarda, pero la respuesta publica el
        // valor derivado: sin procedimiento por defecto no hay recetario.
        $response->assertCreated()
            ->assertJsonPath('data.requires_confirmation', true)
            ->assertJsonPath('data.requires_materials', false)
            ->assertJsonPath('data.is_consultation_mode', true);

        $this->assertDatabaseHas('appointment_types', [
            'name' => 'Consulta de valoracion',
            'requires_confirmation' => true,
            'requires_materials' => true,
            'is_consultation_mode' => true,
        ]);
    }

    public function test_store_publishes_the_derived_requirement_of_the_default_procedure(): void
    {
        $product = Product::factory()->create();
        $procedure = ProcedureCatalog::factory()->create();
        $procedure->materialRecipe()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/appointment-types', [
            'name' => 'Obturacion',
            'duration_minutes' => 45,
            'color' => '#10B981',
            'default_procedure_catalog_id' => $procedure->id,
            'requires_materials' => false,
        ]);

        $response->assertCreated()->assertJsonPath('data.requires_materials', true);
        $this->assertDatabaseHas('appointment_types', [
            'name' => 'Obturacion',
            'requires_materials' => false,
        ]);
    }

    public function test_update_accepts_the_legacy_materials_flag_and_publishes_the_derived_value(): void
    {
        $product = Product::factory()->create();
        $procedure = ProcedureCatalog::factory()->create();
        $procedure->materialRecipe()->create(['product_id' => $product->id, 'quantity' => 2]);

        $type = AppointmentType::factory()->create(['requires_materials' => false]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/appointment-types/{$type->id}", [
                'default_procedure_catalog_id' => $procedure->id,
                'requires_materials' => false,
            ]);

        $response->assertOk()->assertJsonPath('data.requires_materials', true);

        // Quitar el procedimiento por defecto apaga la exigencia, sin importar
        // lo que diga la columna legacy.
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/appointment-types/{$type->id}", [
                'default_procedure_catalog_id' => null,
                'requires_materials' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.requires_materials', false);

        $this->assertDatabaseHas('appointment_types', [
            'id' => $type->id,
            'default_procedure_catalog_id' => null,
            'requires_materials' => true,
        ]);
    }

    public function test_store_defaults_to_confirming_appointment_types(): void
    {
        // Decision D1 (2026-10-19): appointments confirm by default, so the
        // schema default agrees with the seeded catalog instead of contradicting
        // it. Materials and consultation mode stay off.
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/appointment-types', [
            'name' => 'Control',
            'duration_minutes' => 30,
            'color' => '#3B82F6',
        ]);

        $response->assertCreated()->assertJsonPath('data.requires_confirmation', true);
        $this->assertDatabaseHas('appointment_types', [
            'name' => 'Control',
            'requires_confirmation' => true,
            'requires_materials' => false,
            'is_consultation_mode' => false,
        ]);
    }

    public function test_update_can_change_the_catalog_flags(): void
    {
        $type = AppointmentType::factory()->create([
            'requires_confirmation' => false,
            'is_consultation_mode' => false,
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/appointment-types/{$type->id}", [
                'requires_confirmation' => true,
                'is_consultation_mode' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.requires_confirmation', true)
            ->assertJsonPath('data.is_consultation_mode', true);

        $this->assertDatabaseHas('appointment_types', [
            'id' => $type->id,
            'requires_confirmation' => true,
            'is_consultation_mode' => true,
        ]);
    }

    public function test_update_accepts_the_resource_field_default_duration_minutes(): void
    {
        $type = AppointmentType::factory()->create(['default_duration_minutes' => 30]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/appointment-types/{$type->id}", ['default_duration_minutes' => 90]);

        $response->assertOk();
        $this->assertDatabaseHas('appointment_types', [
            'id' => $type->id,
            'default_duration_minutes' => 90,
        ]);
    }

    public function test_store_accepts_default_duration_minutes_as_the_duration(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/appointment-types', [
            'name' => 'Radiografia',
            'default_duration_minutes' => 75,
            'color' => '#3B82F6',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('appointment_types', [
            'name' => 'Radiografia',
            'default_duration_minutes' => 75,
        ]);
    }

    public function test_update_prefers_duration_minutes_when_both_duration_fields_are_sent(): void
    {
        $type = AppointmentType::factory()->create(['default_duration_minutes' => 30]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/appointment-types/{$type->id}", [
                'duration_minutes' => 120,
                'default_duration_minutes' => 90,
            ])
            ->assertOk();

        $this->assertDatabaseHas('appointment_types', [
            'id' => $type->id,
            'default_duration_minutes' => 120,
        ]);
    }

    public function test_store_rejects_a_color_that_is_not_a_hex_triplet(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/appointment-types', [
            'name' => 'Color invalido',
            'duration_minutes' => 30,
            'color' => 'red',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('color');
        $this->assertDatabaseMissing('appointment_types', ['name' => 'Color invalido']);
    }

    public function test_update_rejects_a_color_that_is_not_a_hex_triplet(): void
    {
        $type = AppointmentType::factory()->create(['color' => '#3B82F6']);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/appointment-types/{$type->id}", ['color' => '#GGGGGG']);

        $response->assertStatus(422)->assertJsonValidationErrors('color');
        $this->assertDatabaseHas('appointment_types', [
            'id' => $type->id,
            'color' => '#3B82F6',
        ]);
    }

    public function test_store_persists_the_default_procedure_reference(): void
    {
        // The shared ProcedureCatalogFactory still defines a `category`
        // column that the schema does not have, so the fixture is built from
        // the model instead (finding F12, outside this fix's scope).
        $procedure = ProcedureCatalog::create([
            'code' => 'PROC-9001',
            'name' => 'Endodoncia unirradicular',
            'default_duration_minutes' => 90,
            'default_cost' => 300,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/appointment-types', [
            'name' => 'Endodoncia',
            'duration_minutes' => 90,
            'color' => '#8B5CF6',
            'default_procedure_catalog_id' => $procedure->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('appointment_types', [
            'name' => 'Endodoncia',
            'default_procedure_catalog_id' => $procedure->id,
        ]);
    }

    public function test_store_rejects_an_unknown_default_procedure_reference(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/appointment-types', [
            'name' => 'Procedimiento fantasma',
            'duration_minutes' => 30,
            'color' => '#3B82F6',
            'default_procedure_catalog_id' => 999999,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('default_procedure_catalog_id');
        $this->assertDatabaseMissing('appointment_types', ['name' => 'Procedimiento fantasma']);
    }
}
