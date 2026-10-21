<?php

namespace Tests\Feature\Api;

use App\Events\QuotationRejected;
use App\Models\Patient;
use App\Models\Quotation;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #202 — rejecting a quotation emits its own `quotation.rejected` event, once
 * per transition, and the payload carries the patient decision reason.
 */
class QuotationRejectedEventTest extends TestCase
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
            'status' => 'sent',
        ], $overrides));
    }

    public function test_rejecting_a_sent_quotation_dispatches_the_rejected_event_with_the_reason(): void
    {
        Event::fake([QuotationRejected::class]);

        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'sent']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/reject', ['reason' => 'Precio alto'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        Event::assertDispatchedTimes(QuotationRejected::class, 1);
        Event::assertDispatched(QuotationRejected::class, function (QuotationRejected $event) use ($quotation) {
            return $event->quotation->id === $quotation->id
                && $event->reason === 'Precio alto';
        });
    }

    public function test_a_guarded_reject_does_not_dispatch_the_event_again(): void
    {
        Event::fake([QuotationRejected::class]);

        $user = $this->admin();
        $quotation = $this->quotation(['status' => 'sent']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/reject', ['reason' => 'Precio alto'])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/reject', ['reason' => 'Precio alto'])
            ->assertStatus(422);

        Event::assertDispatchedTimes(QuotationRejected::class, 1);
    }

    public function test_the_broadcast_payload_names_the_event_and_carries_the_reason(): void
    {
        $quotation = $this->quotation(['status' => 'sent']);

        $event = new QuotationRejected($quotation, 'Precio alto');

        $this->assertSame('quotation.rejected', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame('Precio alto', $payload['reason']);
        $this->assertSame($quotation->id, $payload['quotation']->id);
        $this->assertSame($quotation->patient_id, $payload['quotation']->patient_id);
    }
}
