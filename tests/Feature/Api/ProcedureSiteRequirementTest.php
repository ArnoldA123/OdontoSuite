<?php

namespace Tests\Feature\Api;

use App\Models\DentalPiece;
use App\Models\Patient;
use App\Models\ProcedureCatalog;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\ToothSurface;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #293 S1/S2 — a line names the site its procedure applies to.
 *
 * A procedure that applies to a tooth, a surface, a quadrant or an arch cannot
 * be written without that site. The rule lives in one class and is applied by
 * both line writers —the plan and the estimate— because the estimate inherits
 * what the plan declared.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class ProcedureSiteRequirementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);

        $this->patient = Patient::factory()->create();

        $this->actingAs($this->admin, 'sanctum');
    }

    private function procedure(string $siteType, string $name): ProcedureCatalog
    {
        return ProcedureCatalog::create([
            'code' => 'SITE-'.strtoupper(Str::random(8)),
            'name' => $name,
            'site_type' => $siteType,
            'default_cost' => 100.0,
            'is_active' => true,
        ]);
    }

    private function piece(string $fdiNumber = '16'): DentalPiece
    {
        return DentalPiece::create([
            'fdi_number' => $fdiNumber,
            'name' => 'Pieza '.$fdiNumber,
            'type' => 'molar',
            'quadrant' => 'superior_derecho',
            'position' => (int) substr($fdiNumber, -1),
            'is_permanent' => true,
            'is_active' => true,
        ]);
    }

    private function surface(DentalPiece $piece, string $code = 'O'): ToothSurface
    {
        return ToothSurface::create([
            'dental_piece_id' => $piece->id,
            'surface_code' => $code,
            'surface_name' => 'Oclusal',
            'abbreviation' => $code,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed> $site
     * @return array<string, mixed>
     */
    private function planItem(ProcedureCatalog $procedure, array $site = []): array
    {
        return array_merge([
            'description' => $procedure->name,
            'procedure_name' => $procedure->name,
            'procedure_catalog_id' => $procedure->id,
            'quantity' => 1,
            'unit_cost' => (float) $procedure->default_cost,
        ], $site);
    }

    private function createPlan(array $items)
    {
        return $this->postJson('/api/treatment-plans', [
            'patient_id' => $this->patient->id,
            'title' => 'Plan de prueba',
            'items' => $items,
        ]);
    }

    public function test_a_tooth_procedure_without_its_piece_is_refused_and_nothing_is_written(): void
    {
        $procedure = $this->procedure('tooth', 'Obturación composite');

        $response = $this->createPlan([$this->planItem($procedure)]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'El procedimiento «Obturación composite» se realiza sobre una pieza dental: falta la pieza.',
            $response->json('errors.items.0')
        );
        $this->assertDatabaseCount('treatment_plan_items', 0);
    }

    public function test_a_tooth_procedure_with_its_piece_is_written(): void
    {
        $procedure = $this->procedure('tooth', 'Obturación composite');
        $piece = $this->piece();

        $this->createPlan([$this->planItem($procedure, ['dental_piece_id' => $piece->id])])
            ->assertCreated();

        $item = TreatmentPlanItem::firstOrFail();

        $this->assertSame($piece->id, $item->dental_piece_id);
        $this->assertNull($item->tooth_surface_id);
        $this->assertNull($item->site_code);
    }

    public function test_a_surface_procedure_demands_its_surface_and_derives_the_piece(): void
    {
        $procedure = $this->procedure('surface', 'Obturación oclusal');
        $piece = $this->piece();
        $surface = $this->surface($piece);
        $otherPiece = $this->piece('26');

        $response = $this->createPlan([$this->planItem($procedure)]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'El procedimiento «Obturación oclusal» se realiza sobre una superficie dental: falta la superficie dental.',
            $response->json('errors.items.0')
        );

        $this->createPlan([$this->planItem($procedure, [
            'tooth_surface_id' => $surface->id,
            'dental_piece_id' => $otherPiece->id,
        ])])->assertCreated();

        $item = TreatmentPlanItem::firstOrFail();

        $this->assertSame($surface->id, $item->tooth_surface_id);
        $this->assertSame($piece->id, $item->dental_piece_id);
    }

    public function test_a_quadrant_procedure_demands_a_code_from_the_quadrant_vocabulary(): void
    {
        $procedure = $this->procedure('quadrant', 'Raspado por cuadrante');

        $invalid = $this->createPlan([
            $this->planItem($procedure, ['site_code' => 'superior']),
        ]);

        $invalid->assertStatus(422);
        $this->assertStringContainsString(
            'El cuadrante declarado no es válido para el procedimiento «Raspado por cuadrante».',
            $invalid->json('errors.items.0')
        );

        $this->createPlan([
            $this->planItem($procedure, ['site_code' => 'superior_derecho']),
        ])->assertCreated();

        $item = TreatmentPlanItem::firstOrFail();

        $this->assertSame('superior_derecho', $item->site_code);
    }

    public function test_an_arch_procedure_demands_a_code_from_the_arch_vocabulary(): void
    {
        $procedure = $this->procedure('arch', 'Rehabilitación de arcada');

        $this->createPlan([$this->planItem($procedure, ['site_code' => 'superior'])])
            ->assertCreated();

        $item = TreatmentPlanItem::firstOrFail();
        $this->assertSame('superior', $item->site_code);

        $invalid = $this->createPlan([
            $this->planItem($procedure, ['site_code' => 'superior_derecho']),
        ]);

        $invalid->assertStatus(422);
        $this->assertStringContainsString(
            'La arcada declarada no es válida para el procedimiento «Rehabilitación de arcada».',
            $invalid->json('errors.items.0')
        );
        $this->assertDatabaseCount('treatment_plan_items', 1);
    }

    public function test_a_procedure_that_applies_to_the_patient_needs_no_site(): void
    {
        $procedure = $this->procedure('none', 'Limpieza dental');

        $this->createPlan([$this->planItem($procedure)])->assertCreated();

        $item = TreatmentPlanItem::firstOrFail();

        $this->assertNull($item->dental_piece_id);
        $this->assertNull($item->tooth_surface_id);
        $this->assertNull($item->site_code);
    }

    public function test_a_manual_quotation_refuses_a_tooth_procedure_without_its_piece(): void
    {
        $procedure = $this->procedure('tooth', 'Obturación composite');
        $piece = $this->piece();

        $payload = [
            'patient_id' => $this->patient->id,
            'subtotal' => 100,
            'items' => [[
                'description' => 'Obturación composite',
                'procedure_catalog_id' => $procedure->id,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ];

        $response = $this->postJson('/api/quotations', $payload);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'El procedimiento «Obturación composite» se realiza sobre una pieza dental: falta la pieza.',
            $response->json('errors.items.0')
        );
        $this->assertDatabaseCount('quotation_items', 0);

        $payload['items'][0]['dental_piece_id'] = $piece->id;

        $this->postJson('/api/quotations', $payload)->assertCreated();

        $this->assertDatabaseHas('quotation_items', [
            'procedure_catalog_id' => $procedure->id,
            'dental_piece_id' => $piece->id,
            'tooth_surface_id' => null,
            'site_code' => null,
        ]);
    }

    public function test_a_quotation_generated_from_a_plan_copies_each_line_site(): void
    {
        $tooth = $this->procedure('tooth', 'Obturación composite');
        $surface = $this->procedure('surface', 'Obturación oclusal');
        $quadrant = $this->procedure('quadrant', 'Raspado por cuadrante');
        $arch = $this->procedure('arch', 'Rehabilitación de arcada');

        $piece = $this->piece();
        $toothSurface = $this->surface($piece);

        $this->createPlan([
            $this->planItem($tooth, ['dental_piece_id' => $piece->id]),
            $this->planItem($surface, ['tooth_surface_id' => $toothSurface->id]),
            $this->planItem($quadrant, ['site_code' => 'inferior_izquierdo']),
            $this->planItem($arch, ['site_code' => 'inferior']),
        ])->assertCreated();

        $plan = TreatmentPlan::firstOrFail();

        $this->postJson('/api/quotations', [
            'treatment_plan_id' => $plan->id,
            'subtotal' => 400,
        ])->assertCreated();

        $toothItem = QuotationItem::where('procedure_catalog_id', $tooth->id)->firstOrFail();
        $this->assertSame($piece->id, $toothItem->dental_piece_id);
        $this->assertNull($toothItem->tooth_surface_id);
        $this->assertNull($toothItem->site_code);

        $surfaceItem = QuotationItem::where('procedure_catalog_id', $surface->id)->firstOrFail();
        $this->assertSame($toothSurface->id, $surfaceItem->tooth_surface_id);
        $this->assertSame($piece->id, $surfaceItem->dental_piece_id);
        $this->assertNull($surfaceItem->site_code);

        $quadrantItem = QuotationItem::where('procedure_catalog_id', $quadrant->id)->firstOrFail();
        $this->assertSame('inferior_izquierdo', $quadrantItem->site_code);
        $this->assertNull($quadrantItem->dental_piece_id);

        $archItem = QuotationItem::where('procedure_catalog_id', $arch->id)->firstOrFail();
        $this->assertSame('inferior', $archItem->site_code);
        $this->assertNull($archItem->dental_piece_id);
    }

    public function test_a_plan_line_written_before_the_rule_cannot_be_quoted(): void
    {
        $procedure = $this->procedure('tooth', 'Endodoncia unirradicular');

        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'created_by' => $this->admin->id,
        ]);

        // Pre-slice row: the model still accepts it, but it describes no act.
        TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'procedure_catalog_id' => $procedure->id,
            'procedure_name' => $procedure->name,
            'quantity' => 1,
            'unit_cost' => 100,
            'total_cost' => 100,
        ]);

        $response = $this->postJson('/api/quotations', [
            'treatment_plan_id' => $plan->id,
            'subtotal' => 100,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'El procedimiento «Endodoncia unirradicular» se realiza sobre una pieza dental: falta la pieza.',
            $response->json('errors.items.0')
        );
        $this->assertDatabaseCount('quotations', 0);
        $this->assertSame(0, Quotation::count());
    }

    public function test_the_add_item_door_forwards_and_enforces_the_site(): void
    {
        $procedure = $this->procedure('quadrant', 'Raspado por cuadrante');

        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'description' => $procedure->name,
            'procedure_name' => $procedure->name,
            'procedure_catalog_id' => $procedure->id,
            'quantity' => 1,
            'unit_cost' => 100,
        ];

        $this->postJson('/api/treatment-plans/'.$plan->id.'/add-item', $payload)
            ->assertStatus(422);

        $this->postJson('/api/treatment-plans/'.$plan->id.'/add-item', $payload + [
            'site_code' => 'inferior_izquierdo',
        ])->assertCreated();

        $item = TreatmentPlanItem::firstOrFail();

        $this->assertSame('inferior_izquierdo', $item->site_code);
    }

    public function test_the_plan_update_door_keeps_the_site_of_each_line(): void
    {
        $procedure = $this->procedure('arch', 'Rehabilitación de arcada');

        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $this->patient->id,
            'created_by' => $this->admin->id,
        ]);

        $this->putJson('/api/treatment-plans/'.$plan->id, [
            'title' => 'Plan con arcada',
            'items' => [$this->planItem($procedure, ['site_code' => 'superior'])],
        ])->assertOk();

        $item = TreatmentPlanItem::firstOrFail();

        $this->assertSame('superior', $item->site_code);
    }
}
