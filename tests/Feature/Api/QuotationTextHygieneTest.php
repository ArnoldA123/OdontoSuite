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
 * #204 — `store` and `update` must persist the same value for the same input:
 * identical markup stripping, identical caps, identical rejection append.
 */
class QuotationTextHygieneTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function quotation(array $overrides = []): Quotation
    {
        $user = $this->admin();
        $patient = Patient::factory()->create();
        $plan = TreatmentPlan::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $user->id,
        ]);

        return Quotation::create(array_merge([
            'treatment_plan_id' => $plan->id,
            'patient_id' => $patient->id,
            'created_by' => $user->id,
            'quotation_number' => 'PR-'.strtoupper(Str::random(8)),
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 100,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'status' => 'draft',
        ], $overrides));
    }

    private function storePayload(Patient $patient, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $patient->id,
            'subtotal' => 100,
            'terms_conditions' => '<p>Pago <strong>contra entrega</strong></p>',
            'notes' => 'Nota <script>alert(1)</script> del paciente',
        ], $overrides);
    }

    public function test_store_and_update_persist_the_same_sanitized_value(): void
    {
        $user = $this->admin();
        $patient = Patient::factory()->create();
        $payload = $this->storePayload($patient);

        $created = $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations', $payload)
            ->assertCreated()
            ->json('data');

        $this->assertSame('Pago contra entrega', $created['terms_conditions']);
        $this->assertSame('Nota alert(1) del paciente', $created['notes']);

        $updated = $this->actingAs($user, 'sanctum')
            ->putJson('/api/quotations/'.$created['id'], [
                'terms_conditions' => $payload['terms_conditions'],
                'notes' => $payload['notes'],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame($created['terms_conditions'], $updated['terms_conditions']);
        $this->assertSame($created['notes'], $updated['notes']);
    }

    public function test_store_and_update_apply_the_same_cap(): void
    {
        $user = $this->admin();
        $patient = Patient::factory()->create();

        $created = $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations', $this->storePayload($patient, [
                'terms_conditions' => str_repeat('t', 2500),
                'notes' => str_repeat('n', 1500),
            ]))
            ->assertCreated()
            ->json('data');

        $this->assertSame(2000, mb_strlen($created['terms_conditions']));
        $this->assertSame(1000, mb_strlen($created['notes']));

        $updated = $this->actingAs($user, 'sanctum')
            ->putJson('/api/quotations/'.$created['id'], [
                'terms_conditions' => str_repeat('t', 2500),
                'notes' => str_repeat('n', 1500),
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame($created['terms_conditions'], $updated['terms_conditions']);
        $this->assertSame($created['notes'], $updated['notes']);
    }

    public function test_a_rejection_reason_cannot_push_notes_past_the_cap(): void
    {
        $user = $this->admin();
        $quotation = $this->quotation([
            'notes' => str_repeat('n', 1000),
            'status' => 'sent',
        ]);
        $reason = str_repeat('r', 500);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/reject', ['reason' => $reason])
            ->assertOk();

        $stored = $quotation->fresh()->notes;

        $this->assertLessThanOrEqual(1000, mb_strlen($stored));
        $this->assertStringContainsString('Rechazado: '.$reason, $stored);
    }
}
