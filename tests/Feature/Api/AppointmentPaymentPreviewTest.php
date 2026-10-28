<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ClinicalCompliancePayload;
use Tests\TestCase;

/**
 * #459 — the payment preview never contradicts itself.
 *
 * `subtotal` summed only the completed items of the treatment plan, while the
 * amount to collect came from `AppointmentBalance`. An attention billed by its
 * appointment type has no plan items, so the breakdown published
 * `Subtotal 0.00` next to `Total a cobrar 90.00` with no line explaining the
 * difference. The preview now names the origin of the amount and, when there
 * are no items, the subtotal is that same total.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class AppointmentPaymentPreviewTest extends TestCase
{
    use ClinicalCompliancePayload;
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * An attention closed by its appointment type: the closure projected the
     * amount and the treatment plan left no completed items behind.
     */
    private function fixedAmountAppointment(User $professional, float $amount): Appointment
    {
        return Appointment::factory()->create([
            'user_id' => $professional->id,
            'created_by' => $professional->id,
            'status' => 'completed',
            'completed_at' => now(),
            'final_amount' => $amount,
            'total_cost' => $amount,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function closurePayload(float $amount): array
    {
        return [
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
                'items' => [[
                    'procedure_name' => 'Obturación composite',
                    'dental_piece_id' => null,
                    'unit_cost' => $amount,
                    'quantity' => 1,
                ]],
            ],
        ];
    }

    private function closedAppointmentWithItems(User $professional, float $amount): Appointment
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $professional->id,
            'created_by' => $professional->id,
            'status' => 'arrived',
            'checked_in_at' => now(),
        ]);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start-attention")
            ->assertOk();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", $this->closurePayload($amount))
            ->assertOk();

        return $appointment->fresh();
    }

    private function preview(User $actor, Appointment $appointment): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($actor, 'sanctum')
            ->getJson("/api/appointments/{$appointment->id}/payment-preview")
            ->assertOk();
    }

    public function test_a_fixed_amount_attention_publishes_the_total_as_its_subtotal(): void
    {
        $admin = $this->user('administrador');
        $appointment = $this->fixedAmountAppointment($this->user('odontologo'), 90);

        $response = $this->preview($admin, $appointment);

        $response->assertJsonPath('data.amount_source', 'fixed_amount')
            ->assertJsonCount(0, 'data.items');

        $this->assertEqualsWithDelta(90.0, (float) $response->json('data.final_amount'), 0.001);
        $this->assertEqualsWithDelta(
            (float) $response->json('data.final_amount'),
            (float) $response->json('data.subtotal'),
            0.001,
            'Without plan items the subtotal is the total: the card must not publish a subtraction that explains nothing.'
        );
        $this->assertEqualsWithDelta(90.0, (float) $response->json('data.balance'), 0.001);
    }

    public function test_a_plan_attention_keeps_the_items_as_the_source_of_the_subtotal(): void
    {
        $admin = $this->user('administrador');
        $appointment = $this->closedAppointmentWithItems($this->user('odontologo'), 180);

        $response = $this->preview($admin, $appointment);

        $response->assertJsonPath('data.amount_source', 'plan_items')
            ->assertJsonCount(1, 'data.items');

        $itemsSubtotal = collect($response->json('data.items'))->sum('total_cost');
        $this->assertEqualsWithDelta(
            (float) $itemsSubtotal,
            (float) $response->json('data.subtotal'),
            0.001
        );
        $this->assertEqualsWithDelta(
            (float) $response->json('data.subtotal'),
            (float) $response->json('data.final_amount'),
            0.001
        );
    }
}
