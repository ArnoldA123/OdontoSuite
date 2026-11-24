<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\Quotation;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «Unificar Pendientes por paciente» — the Pendientes desk groups recalls,
 * continuity requests, quotations and treatment plans into one row per patient,
 * so every published item must name its patient by id.
 *
 * Before this contract the commercial subsets only carried `patient_name`, and
 * two patients with the same full name were indistinguishable: grouping by the
 * display name either merged strangers or split one patient across rows.
 *
 * Runs against the MySQL harness (AGENTS.md §6).
 */
class PendingCommercialPatientIdTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function plan(Patient $patient, string $status): TreatmentPlan
    {
        return TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $this->admin->id,
            'plan_number' => 'TP-'.Str::upper(Str::random(8)),
            'title' => 'Plan '.$status,
            'status' => $status,
            'total_cost' => 249.5,
            'final_cost' => 249.5,
        ]);
    }

    private function quotation(Patient $patient, TreatmentPlan $plan, string $status): Quotation
    {
        return Quotation::create([
            'treatment_plan_id' => $plan->id,
            'patient_id' => $patient->id,
            'created_by' => $this->admin->id,
            'quotation_number' => 'Q-'.Str::upper(Str::random(8)),
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 149.9,
            'total_amount' => 149.9,
            'status' => $status,
        ]);
    }

    public function test_every_commercial_item_names_its_patient_by_id(): void
    {
        $ana = Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Torres']);
        $beto = Patient::factory()->create(['first_name' => 'Beto', 'last_name' => 'Díaz']);

        $anaQuotation = $this->quotation($ana, $this->plan($ana, 'draft'), 'sent');
        $betoQuotation = $this->quotation($beto, $this->plan($beto, 'draft'), 'viewed');
        $anaPlan = $this->plan($ana, 'proposed');
        $betoPlan = $this->plan($beto, 'proposed');

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/dashboard/pending');

        $response->assertOk();

        $quotations = collect($response->json('data.quotations.items'))->keyBy('id');
        $this->assertSame($ana->id, $quotations[$anaQuotation->id]['patient_id']);
        $this->assertSame($beto->id, $quotations[$betoQuotation->id]['patient_id']);
        $this->assertSame('Ana Torres', $quotations[$anaQuotation->id]['patient_name']);

        $plans = collect($response->json('data.treatment_plans.items'))->keyBy('id');
        $this->assertSame($ana->id, $plans[$anaPlan->id]['patient_id']);
        $this->assertSame($beto->id, $plans[$betoPlan->id]['patient_id']);
    }

    public function test_the_patient_id_is_the_owner_and_not_the_plan_or_the_author(): void
    {
        $patient = Patient::factory()->create(['first_name' => 'Carla', 'last_name' => 'Núñez']);
        $otherPatient = Patient::factory()->create(['first_name' => 'Diego', 'last_name' => 'Salas']);

        // The plan belongs to another patient and the quotation names its own:
        // the item must publish the patient the row is about, not the plan's.
        $quotation = $this->quotation($patient, $this->plan($otherPatient, 'draft'), 'sent');

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/dashboard/pending');
        $response->assertOk();

        $item = $response->json('data.quotations.items.0');

        $this->assertSame($quotation->id, $item['id']);
        $this->assertSame($patient->id, $item['patient_id']);
        $this->assertNotSame($otherPatient->id, $item['patient_id']);
        $this->assertNotSame($this->admin->id, $item['patient_id']);
    }

    public function test_two_patients_with_the_same_full_name_keep_their_own_id(): void
    {
        $first = Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Torres']);
        $second = Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Torres']);

        $this->quotation($first, $this->plan($first, 'draft'), 'sent');
        $this->quotation($second, $this->plan($second, 'draft'), 'viewed');

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/dashboard/pending');
        $response->assertOk();

        $items = $response->json('data.quotations.items');

        $this->assertCount(2, $items);
        $this->assertSame('Ana Torres', $items[0]['patient_name']);
        $this->assertSame('Ana Torres', $items[1]['patient_name']);
        $this->assertNotSame($items[0]['patient_id'], $items[1]['patient_id']);

        // The id — not the display name — is what the desk groups by.
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            array_column($items, 'patient_id'),
            'Los dos homónimos conservan su propio id.'
        );
    }
}
