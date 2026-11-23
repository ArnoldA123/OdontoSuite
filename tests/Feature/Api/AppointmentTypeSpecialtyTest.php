<?php

namespace Tests\Feature\Api;

use App\Models\AppointmentType;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `appointment_types.specialty_id` is published and filtered by its own
 * catalog.
 *
 * Acceptance:
 *  - `index` and `active` publish `specialty_id` and the `specialty` object
 *    (`id`, `code`, `name`), with null for a type that has none.
 *  - `index` accepts `specialty_id` (a concrete id) and `specialty_id=none`
 *    (only the types without a specialty).
 *  - `store`/`update` accept a nullable `specialty_id` that must exist, and
 *    clearing it is a valid write.
 *  - Deleting the specialty leaves the type without one (nullOnDelete); the
 *    type is never deleted.
 */
class AppointmentTypeSpecialtyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function specialty(string $code, string $name): Specialty
    {
        return Specialty::create([
            'code' => $code,
            'name' => $name,
            'description' => null,
            'is_active' => true,
        ]);
    }

    public function test_active_publishes_the_specialty_of_each_type(): void
    {
        $specialty = $this->specialty('ortodoncia', 'Ortodoncia');
        $withSpecialty = AppointmentType::factory()->create([
            'specialty_id' => $specialty->id,
            'is_active' => true,
        ]);
        $withoutSpecialty = AppointmentType::factory()->create([
            'specialty_id' => null,
            'is_active' => true,
        ]);
        $inactive = AppointmentType::factory()->create([
            'specialty_id' => $specialty->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/appointment-types/active');

        $response->assertOk()->assertJsonStructure(['data', 'meta' => ['total']]);

        $rows = collect($response->json('data'))->keyBy('id');

        $this->assertCount(2, $rows);
        $this->assertArrayNotHasKey($inactive->id, $rows->all());

        $this->assertSame($specialty->id, $rows[$withSpecialty->id]['specialty_id']);
        $this->assertSame([
            'id' => $specialty->id,
            'code' => 'ortodoncia',
            'name' => 'Ortodoncia',
        ], $rows[$withSpecialty->id]['specialty']);

        $this->assertNull($rows[$withoutSpecialty->id]['specialty_id']);
        $this->assertNull($rows[$withoutSpecialty->id]['specialty']);
    }

    public function test_index_publishes_the_specialty_of_each_type(): void
    {
        $specialty = $this->specialty('rehabilitacion', 'Rehabilitación oral');
        $withSpecialty = AppointmentType::factory()->create(['specialty_id' => $specialty->id]);
        $withoutSpecialty = AppointmentType::factory()->create(['specialty_id' => null]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/appointment-types?status=all');

        $response->assertOk();

        $rows = collect($response->json('data'))->keyBy('id');

        $this->assertSame($specialty->id, $rows[$withSpecialty->id]['specialty_id']);
        $this->assertSame([
            'id' => $specialty->id,
            'code' => 'rehabilitacion',
            'name' => 'Rehabilitación oral',
        ], $rows[$withSpecialty->id]['specialty']);

        $this->assertNull($rows[$withoutSpecialty->id]['specialty_id']);
        $this->assertNull($rows[$withoutSpecialty->id]['specialty']);
    }

    public function test_index_filters_by_a_concrete_specialty(): void
    {
        $orthodontics = $this->specialty('ortodoncia', 'Ortodoncia');
        $general = $this->specialty('general', 'Odontología general');

        $orthodonticType = AppointmentType::factory()->create([
            'specialty_id' => $orthodontics->id,
        ]);
        $generalType = AppointmentType::factory()->create([
            'specialty_id' => $general->id,
        ]);
        $unassigned = AppointmentType::factory()->create(['specialty_id' => null]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/appointment-types?status=all&specialty_id={$orthodontics->id}");

        $response->assertOk();
        $this->assertSame(
            [$orthodonticType->id],
            collect($response->json('data'))->pluck('id')->all()
        );
        $this->assertSame(1, $response->json('meta.total'));

        $this->assertNotContains($generalType->id, collect($response->json('data'))->pluck('id'));
        $this->assertNotContains($unassigned->id, collect($response->json('data'))->pluck('id'));
    }

    public function test_index_filters_the_types_without_specialty(): void
    {
        $specialty = $this->specialty('endodoncia', 'Endodoncia');
        AppointmentType::factory()->create(['specialty_id' => $specialty->id]);
        $firstUnassigned = AppointmentType::factory()->create(['specialty_id' => null]);
        $secondUnassigned = AppointmentType::factory()->create(['specialty_id' => null]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/appointment-types?status=all&specialty_id=none');

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$firstUnassigned->id, $secondUnassigned->id],
            collect($response->json('data'))->pluck('id')->all()
        );
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_store_persists_the_specialty(): void
    {
        $specialty = $this->specialty('cirugia_oral', 'Cirugía oral');

        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/appointment-types', [
            'name' => 'Extracción',
            'duration_minutes' => 60,
            'color' => '#EF4444',
            'specialty_id' => $specialty->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.specialty_id', $specialty->id)
            ->assertJsonPath('data.specialty.id', $specialty->id)
            ->assertJsonPath('data.specialty.code', 'cirugia_oral')
            ->assertJsonPath('data.specialty.name', 'Cirugía oral');

        $this->assertDatabaseHas('appointment_types', [
            'name' => 'Extracción',
            'specialty_id' => $specialty->id,
        ]);
    }

    public function test_store_rejects_an_unknown_specialty(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/appointment-types', [
            'name' => 'Tipo con especialidad fantasma',
            'duration_minutes' => 30,
            'color' => '#3B82F6',
            'specialty_id' => 999999,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('specialty_id');
        $this->assertDatabaseMissing('appointment_types', [
            'name' => 'Tipo con especialidad fantasma',
        ]);
    }

    public function test_update_assigns_and_clears_the_specialty(): void
    {
        $orthodontics = $this->specialty('ortodoncia', 'Ortodoncia');
        $rehabilitation = $this->specialty('rehabilitacion', 'Rehabilitación oral');
        $type = AppointmentType::factory()->create(['specialty_id' => $orthodontics->id]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/appointment-types/{$type->id}", ['specialty_id' => $rehabilitation->id])
            ->assertOk()
            ->assertJsonPath('data.specialty_id', $rehabilitation->id)
            ->assertJsonPath('data.specialty.code', 'rehabilitacion');

        $this->assertDatabaseHas('appointment_types', [
            'id' => $type->id,
            'specialty_id' => $rehabilitation->id,
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/appointment-types/{$type->id}", ['specialty_id' => null])
            ->assertOk()
            ->assertJsonPath('data.specialty_id', null)
            ->assertJsonPath('data.specialty', null);

        $this->assertDatabaseHas('appointment_types', [
            'id' => $type->id,
            'specialty_id' => null,
        ]);
    }

    public function test_deleting_the_specialty_leaves_the_type_without_one(): void
    {
        $specialty = $this->specialty('estetica', 'Estética dental');
        $type = AppointmentType::factory()->create(['specialty_id' => $specialty->id]);

        Specialty::whereKey($specialty->id)->delete();

        $this->assertDatabaseHas('appointment_types', [
            'id' => $type->id,
            'specialty_id' => null,
        ]);
        $this->assertDatabaseMissing('specialties', ['id' => $specialty->id]);
    }
}
