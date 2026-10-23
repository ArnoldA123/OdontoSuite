<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEvolution;
use App\Models\Patient;
use App\Models\ProcedureMaterial;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #295 (S1, S2, S3, S5) — the stock the act consumes.
 *
 * Closing an act with materials discounts `products.stock_quantity` and writes
 * one `stock_movements` exit per line, with its batch and the unit cost frozen
 * at that moment. When the aggregated demand of a product exceeds what the
 * warehouse holds, the closure is refused with 422 and the act rolls back whole:
 * no evolution, no material, no movement, no stock.
 */
class EncounterMaterialConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private function professional(?Branch $branch = null): User
    {
        return User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function inConsultation(User $professional, ?int $branchId = null): Appointment
    {
        return Appointment::factory()->create([
            'user_id' => $professional->id,
            'status' => 'in_consultation',
            'checked_in_at' => now()->subMinutes(30),
            'branch_id' => $branchId,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>> $materials
     * @param  array<string, mixed>             $overrides
     * @return array<string, mixed>
     */
    private function payload(array $materials, array $overrides = []): array
    {
        return array_merge([
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
            'materials' => $materials,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function product(float $stock, array $attributes = []): Product
    {
        return Product::factory()->withStock($stock)->create(array_merge([
            'name' => 'Resina A2',
            'cost_price' => 20,
        ], $attributes));
    }

    public function test_the_closure_discounts_stock_and_writes_one_exit_per_line(): void
    {
        $branch = Branch::factory()->create();
        $professional = $this->professional($branch);
        $appointment = $this->inConsultation($professional, $branch->id);

        $resin = $this->product(10);
        $anesthetic = $this->product(5, ['name' => 'Anestésico', 'cost_price' => 8.5]);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload([
                [
                    'product_id' => $resin->id,
                    'quantity_used' => 2,
                    'batch_number' => 'L-001',
                    'expiry_date' => '2027-05-31',
                ],
                [
                    'product_id' => $anesthetic->id,
                    'quantity_used' => 1.5,
                    'batch_number' => 'L-002',
                    'expiry_date' => '2027-01-15',
                ],
            ]))
            ->assertOk();

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();

        // S1: the balance is what the warehouse holds after the act.
        $this->assertSame(8.0, (float) $resin->fresh()->stock_quantity);
        $this->assertSame(3.5, (float) $anesthetic->fresh()->stock_quantity);

        // S5: one exit per line, with its batch and the lot it left.
        $movements = StockMovement::where('clinical_encounter_id', $encounter->id)->orderBy('id')->get();
        $this->assertCount(2, $movements);

        $first = $movements[0];
        $this->assertSame($resin->id, $first->product_id);
        $this->assertSame('out', $first->type);
        $this->assertSame(2.0, (float) $first->quantity);
        $this->assertSame(10.0, (float) $first->previous_stock);
        $this->assertSame(8.0, (float) $first->new_stock);
        $this->assertSame(20.0, (float) $first->unit_cost);
        $this->assertSame(40.0, (float) $first->total_cost);
        $this->assertSame('L-001', $first->batch_number);
        $this->assertSame('2027-05-31', $first->expiry_date?->toDateString());
        $this->assertSame("ENC-{$encounter->id}", $first->reference);
        $this->assertSame('Consumo en atención', $first->reason);
        $this->assertSame(now()->toDateString(), $first->movement_date?->toDateString());
        $this->assertSame($branch->id, $first->branch_id);
        $this->assertSame($professional->id, $first->created_by);

        $second = $movements[1];
        $this->assertSame($anesthetic->id, $second->product_id);
        $this->assertSame(1.5, (float) $second->quantity);
        $this->assertSame(5.0, (float) $second->previous_stock);
        $this->assertSame(3.5, (float) $second->new_stock);
        // S3: no unit cost in the payload means the catalog cost at that moment.
        $this->assertSame(8.5, (float) $second->unit_cost);
        $this->assertSame(12.75, (float) $second->total_cost);

        $materials = ProcedureMaterial::where('clinical_encounter_id', $encounter->id)->orderBy('id')->get();
        $this->assertCount(2, $materials);
        $this->assertSame(2.0, (float) $materials[0]->quantity_used);
        $this->assertSame(40.0, (float) $materials[0]->total_cost);
        $this->assertSame(12.75, (float) $materials[1]->total_cost);
    }

    public function test_two_batches_of_the_same_product_are_guarded_over_the_aggregated_quantity(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $resin = $this->product(3);

        // 2 + 2 = 4 over a stock of 3: neither line is short on its own, so the
        // guard has to read the product, not the line.
        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload([
                ['product_id' => $resin->id, 'quantity_used' => 2, 'batch_number' => 'L-001'],
                ['product_id' => $resin->id, 'quantity_used' => 2, 'batch_number' => 'L-002'],
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'insufficient_stock')
            ->assertJsonPath('error.message', 'Stock insuficiente para «Resina A2»: disponible 3, solicitado 4.');

        // S2: the whole act rolls back, not only the materials.
        $this->assertSame(0, ClinicalEvolution::count());
        $this->assertSame(0, ProcedureMaterial::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(3.0, (float) $resin->fresh()->stock_quantity);
        $this->assertSame(0, ClinicalEncounter::count());
        $this->assertSame('in_consultation', $appointment->fresh()->status);
    }

    public function test_a_material_short_of_stock_is_refused_with_the_exact_message(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $resin = $this->product(2);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload([
                ['product_id' => $resin->id, 'quantity_used' => 5],
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error.error_code', 'insufficient_stock')
            ->assertJsonPath('error.message', 'Stock insuficiente para «Resina A2»: disponible 2, solicitado 5.')
            ->assertJsonPath('error.context.product_id', $resin->id)
            ->assertJsonPath('error.context.available', 2)
            ->assertJsonPath('error.context.requested', 5);

        $this->assertSame(0, ClinicalEvolution::count());
        $this->assertSame(0, ProcedureMaterial::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(2.0, (float) $resin->fresh()->stock_quantity);
    }

    public function test_an_unknown_product_is_refused_instead_of_being_skipped(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);
        $resin = $this->product(10);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload([
                ['product_id' => 999999, 'quantity_used' => 1],
                ['product_id' => $resin->id, 'quantity_used' => 1],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('materials.0.product_id');

        $this->assertSame(0, ClinicalEvolution::count());
        $this->assertSame(0, ProcedureMaterial::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(10.0, (float) $resin->fresh()->stock_quantity);
    }

    public function test_the_unit_cost_is_frozen_when_the_material_is_consumed(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $resin = $this->product(4);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload([
                ['product_id' => $resin->id, 'quantity_used' => 2],
            ]))
            ->assertOk();

        $material = ProcedureMaterial::sole();
        $movement = StockMovement::sole();

        // S3: the catalog price moves later; the act keeps the cost it froze.
        $resin->fresh()->update(['cost_price' => 99]);

        $this->assertSame(20.0, (float) $material->fresh()->unit_cost);
        $this->assertSame(40.0, (float) $material->fresh()->total_cost);
        $this->assertSame(20.0, (float) $movement->fresh()->unit_cost);
        $this->assertSame(40.0, (float) $movement->fresh()->total_cost);
        $this->assertSame(2.0, (float) $resin->fresh()->stock_quantity);
    }

    public function test_a_decimal_quantity_discounts_the_decimal(): void
    {
        $professional = $this->professional();
        $appointment = $this->inConsultation($professional);

        $syringe = $this->product(1, ['name' => 'Jeringa carpule', 'cost_price' => 4.5]);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->payload([
                ['product_id' => $syringe->id, 'quantity_used' => 0.5],
            ]))
            ->assertOk();

        $this->assertSame(0.5, (float) $syringe->fresh()->stock_quantity);

        $movement = StockMovement::sole();
        $this->assertSame(0.5, (float) $movement->quantity);
        $this->assertSame(1.0, (float) $movement->previous_stock);
        $this->assertSame(0.5, (float) $movement->new_stock);
        $this->assertSame(2.25, (float) $movement->total_cost);
    }

    public function test_an_urgent_act_without_appointment_takes_the_sede_of_the_professional(): void
    {
        $branch = Branch::factory()->create();
        $professional = $this->professional($branch);

        $encounter = ClinicalEncounter::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $professional->id,
            'appointment_id' => null,
            'mode' => ClinicalEncounter::MODE_URGENCY,
        ]);

        $resin = $this->product(20);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/encounters/{$encounter->id}/complete", $this->payload([
                ['product_id' => $resin->id, 'quantity_used' => 1],
            ]))
            ->assertOk();

        $this->assertSame($branch->id, StockMovement::sole()->branch_id);
        $this->assertSame(19.0, (float) $resin->fresh()->stock_quantity);
    }
}
