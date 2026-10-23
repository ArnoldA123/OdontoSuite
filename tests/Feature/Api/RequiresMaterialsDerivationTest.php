<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\ProcedureCatalog;
use App\Models\Product;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ClinicalCompliancePayload;
use Tests\TestCase;

/**
 * #295 (S7) — `requires_materials` se deriva del recetario del procedimiento.
 *
 * Un acto exige materiales cuando alguno de los procedimientos que documenta
 * declara receta: el procedimiento por defecto del tipo de cita y los
 * `procedure_catalog_id` de los ítems del plan. `appointment_types.requires_materials`
 * queda como columna legacy: se sigue aceptando al escribir, pero ya no decide
 * y la respuesta publica el valor derivado.
 */
class RequiresMaterialsDerivationTest extends TestCase
{
    use ClinicalCompliancePayload;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'is_active' => true]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function inConsultation(User $user, AppointmentType $type, array $overrides = []): Appointment
    {
        return Appointment::factory()->create(array_merge([
            'user_id' => $user->id,
            'appointment_type_id' => $type->id,
            'status' => 'in_consultation',
            'checked_in_at' => now()->subMinutes(30),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            ...$this->compliancePayload(),
            'mode' => 'execution',
            'evolution' => [
                'subjective' => 'Dolor en pieza 16 desde hace tres días.',
                'objective' => 'Caries oclusal profunda, sin exposición pulpar.',
                'assessment' => 'Caries dentinaria.',
                'plan' => 'Obturación con resina compuesta.',
            ],
            'treatment_plan' => [
                'title' => 'Restauración 16',
                'items' => [
                    ['procedure_name' => 'Obturación composite', 'unit_cost' => 180, 'quantity' => 1],
                ],
            ],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function product(float $stock, array $attributes = []): Product
    {
        return Product::factory()->withStock($stock)->create(array_merge([
            'name' => 'Resina A2',
            'unit' => 'jeringa',
            'cost_price' => 20,
        ], $attributes));
    }

    private function procedureWithRecipe(Product $product, float $quantity): ProcedureCatalog
    {
        $procedure = ProcedureCatalog::factory()->create(['name' => 'Obturación composite']);
        $procedure->materialRecipe()->create(['product_id' => $product->id, 'quantity' => $quantity]);

        return $procedure;
    }

    public function test_the_type_whose_procedure_declares_a_recipe_requires_materials(): void
    {
        $admin = $this->admin();
        $resin = $this->product(10);
        $procedure = $this->procedureWithRecipe($resin, 1);
        $type = AppointmentType::factory()->create([
            'default_procedure_catalog_id' => $procedure->id,
            'requires_materials' => false,
        ]);

        // La respuesta publica el valor derivado, no la columna legacy.
        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/appointment-types/{$type->id}")
            ->assertOk()
            ->assertJsonPath('data.requires_materials', true);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/appointment-types')
            ->assertOk()
            ->assertJsonPath('data.0.requires_materials', true);

        $appointment = $this->inConsultation($admin, $type);

        $refused = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload());

        $refused->assertStatus(422)
            ->assertJsonPath('error.error_code', 'missing_materials');

        // `skip_materials` sigue siendo la salida declarada.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload([
                'skip_materials' => true,
            ]))
            ->assertOk();
    }

    public function test_the_type_whose_procedure_declares_no_recipe_closes_without_materials(): void
    {
        $admin = $this->admin();
        $procedure = ProcedureCatalog::factory()->create(['name' => 'Limpieza dental']);
        $type = AppointmentType::factory()->create([
            'default_procedure_catalog_id' => $procedure->id,
            'requires_materials' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/appointment-types/{$type->id}")
            ->assertOk()
            ->assertJsonPath('data.requires_materials', false);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/appointments/{$this->inConsultation($admin, $type)->id}/complete", $this->payload())
            ->assertOk();
    }

    public function test_the_legacy_flag_alone_no_longer_requires_materials(): void
    {
        $admin = $this->admin();
        $type = AppointmentType::factory()->create([
            'default_procedure_catalog_id' => null,
            'requires_materials' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/appointment-types/{$type->id}")
            ->assertOk()
            ->assertJsonPath('data.requires_materials', false);

        // El flag se sigue aceptando (el formulario lo envía), pero no decide.
        $this->assertDatabaseHas('appointment_types', [
            'id' => $type->id,
            'requires_materials' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/appointments/{$this->inConsultation($admin, $type)->id}/complete", $this->payload())
            ->assertOk();
    }

    public function test_a_procedure_of_the_act_plan_declares_the_requirement(): void
    {
        $admin = $this->admin();
        $resin = $this->product(10);
        $procedure = $this->procedureWithRecipe($resin, 1);
        $type = AppointmentType::factory()->create(['default_procedure_catalog_id' => null]);
        $appointment = $this->inConsultation($admin, $type);

        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $appointment->patient_id,
            'created_by' => $admin->id,
            'status' => 'approved',
        ]);

        TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'procedure_catalog_id' => $procedure->id,
            'procedure_name' => 'Obturación composite',
            'quantity' => 1,
            'unit_cost' => 180,
            'total_cost' => 180,
            'status' => 'in_progress',
        ]);

        // El tipo no declara procedimiento: la exigencia viene del plan del acto.
        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/appointment-types/{$type->id}")
            ->assertOk()
            ->assertJsonPath('data.requires_materials', false);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload([
                'mode' => 'plan_session',
                'treatment_plan' => ['id' => $plan->id],
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'missing_materials');
    }

    public function test_the_context_publishes_the_derived_flag_and_the_prefilled_recipe(): void
    {
        $admin = $this->admin();
        $resin = $this->product(10, ['name' => 'Resina A2']);
        $anesthetic = $this->product(4, [
            'name' => 'Anestesia lidocaína',
            'unit' => 'cartucho',
            'cost_price' => 3.5,
        ]);

        $procedure = $this->procedureWithRecipe($resin, 1.5);
        $procedure->materialRecipe()->create(['product_id' => $anesthetic->id, 'quantity' => 1]);
        $type = AppointmentType::factory()->create(['default_procedure_catalog_id' => $procedure->id]);

        $appointment = $this->inConsultation($admin, $type);

        // El plan activo suma su propio procedimiento: la receta se agrega por
        // producto y el mismo insumo aparece en los dos.
        $secondProcedure = $this->procedureWithRecipe($resin, 0.5);
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $appointment->patient_id,
            'created_by' => $admin->id,
            'status' => 'approved',
        ]);
        TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'procedure_catalog_id' => $secondProcedure->id,
            'procedure_name' => 'Sellado de fosas',
            'quantity' => 1,
            'unit_cost' => 90,
            'total_cost' => 90,
            'status' => 'proposed',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/appointments/{$appointment->id}/consultation-context");

        $response->assertOk()->assertJsonPath('data.requires_materials', true);

        $recipe = $response->json('data.material_recipe');
        $this->assertCount(2, $recipe);

        // Ordenado por nombre: el wizard pinta la lista tal como llega.
        $this->assertSame($anesthetic->id, $recipe[0]['product_id']);
        $this->assertSame('Anestesia lidocaína', $recipe[0]['name']);
        $this->assertSame('cartucho', $recipe[0]['unit']);
        $this->assertSame(3.5, (float) $recipe[0]['cost_price']);
        $this->assertSame(4.0, (float) $recipe[0]['stock_quantity']);
        $this->assertSame(1.0, (float) $recipe[0]['quantity']);

        $this->assertSame($resin->id, $recipe[1]['product_id']);
        $this->assertSame('Resina A2', $recipe[1]['name']);
        $this->assertSame('jeringa', $recipe[1]['unit']);
        $this->assertSame(20.0, (float) $recipe[1]['cost_price']);
        $this->assertSame(10.0, (float) $recipe[1]['stock_quantity']);
        $this->assertSame(2.0, (float) $recipe[1]['quantity']);
    }

    public function test_the_closure_persists_the_procedure_of_its_own_plan_item(): void
    {
        $admin = $this->admin();
        $resin = $this->product(10);
        $procedure = $this->procedureWithRecipe($resin, 1);

        // El tipo de cita no aporta procedimiento: la unica fuente es el item
        // que el propio acto declara al cerrarse.
        $type = AppointmentType::factory()->create([
            'default_procedure_catalog_id' => null,
            'requires_materials' => false,
        ]);

        $appointment = $this->inConsultation($admin, $type);

        $payload = $this->payload([
            'treatment_plan' => [
                'title' => 'Restauración 16',
                'items' => [[
                    'procedure_catalog_id' => $procedure->id,
                    'procedure_name' => 'Obturación composite',
                    'unit_cost' => 180,
                    'quantity' => 1,
                ]],
            ],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'missing_materials');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", array_merge($payload, ['skip_materials' => true]))
            ->assertOk();

        // Sin el vinculo guardado, el plan del acto no podria derivar la
        // exigencia en una sesion posterior.
        $item = TreatmentPlanItem::where('procedure_name', 'Obturación composite')->sole();
        $this->assertSame($procedure->id, $item->procedure_catalog_id);
    }
}
