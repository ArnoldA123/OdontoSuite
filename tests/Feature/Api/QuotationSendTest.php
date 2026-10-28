<?php

namespace Tests\Feature\Api;

use App\Events\QuotationSent;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Quotation;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #422 S4 — a quotation created from the screen is born `draft`, and until this
 * door existed nothing in the SPA wrote `sent`: the patient decision (approve
 * or reject) was unreachable, «Pendientes» and «por vencer» never saw the
 * document, and no row recorded the act of sending it.
 *
 * `POST /api/quotations/{id}/send` is that door. The transition is only
 * `draft -> sent`; the actor and the act live in `audit_logs`
 * (`quotation_sent`) instead of a new column, and `QuotationSent` publishes on
 * the private `quotations` channel.
 */
class QuotationSendTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'administrador'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function quotation(array $overrides = []): Quotation
    {
        $user = $this->staff();
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

    public function test_sending_a_draft_persists_the_sent_status(): void
    {
        $user = $this->staff();
        $quotation = $this->quotation(['status' => 'draft']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/send')
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.id', $quotation->id);

        $this->assertSame('sent', $quotation->fresh()->status);
    }

    public function test_the_act_of_sending_is_audited_with_actor_and_transition(): void
    {
        $user = $this->staff();
        $quotation = $this->quotation(['status' => 'draft']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/send')
            ->assertOk();

        $log = AuditLog::query()->where('action', 'quotation_sent')->sole();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(Quotation::class, $log->auditable_type);
        $this->assertSame($quotation->id, $log->auditable_id);
        $this->assertSame(['status' => 'draft'], $log->old_values);
        $this->assertSame(['status' => 'sent'], $log->new_values);
    }

    public function test_sending_dispatches_the_sent_event_exactly_once(): void
    {
        Event::fake([QuotationSent::class]);

        $user = $this->staff();
        $quotation = $this->quotation(['status' => 'draft']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/send')
            ->assertOk();

        Event::assertDispatchedTimes(QuotationSent::class, 1);
        Event::assertDispatched(
            QuotationSent::class,
            fn (QuotationSent $event): bool => $event->quotation->id === $quotation->id
                && $event->quotation->status === 'sent'
        );
    }

    public function test_a_sent_quotation_cannot_be_sent_again(): void
    {
        $user = $this->staff();
        $quotation = $this->quotation(['status' => 'sent']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/send')
            ->assertStatus(422);

        $this->assertSame(
            'Solo se puede enviar un presupuesto en borrador. Estado actual: sent.',
            $response->json('errors.status.0')
        );
        $this->assertSame('sent', $quotation->fresh()->status);
        $this->assertSame(0, AuditLog::query()->where('action', 'quotation_sent')->count());
    }

    public function test_an_approved_quotation_cannot_be_sent_and_the_message_names_its_state(): void
    {
        $user = $this->staff();
        $quotation = $this->quotation(['status' => 'approved']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/send')
            ->assertStatus(422);

        $this->assertSame(
            'Solo se puede enviar un presupuesto en borrador. Estado actual: approved.',
            $response->json('errors.status.0')
        );
        $this->assertSame('approved', $quotation->fresh()->status);
        $this->assertSame(0, AuditLog::query()->where('action', 'quotation_sent')->count());
    }

    public function test_a_reader_without_manage_quotation_cannot_send(): void
    {
        $reception = $this->staff('recepcionista');
        $quotation = $this->quotation(['status' => 'draft']);

        $this->actingAs($reception, 'sanctum')
            ->postJson('/api/quotations/'.$quotation->id.'/send')
            ->assertForbidden();

        $this->assertSame('draft', $quotation->fresh()->status);
        $this->assertSame(0, AuditLog::query()->where('action', 'quotation_sent')->count());
    }

    public function test_an_unknown_quotation_answers_404(): void
    {
        $user = $this->staff();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations/999999/send')
            ->assertNotFound()
            ->assertJsonPath('message', 'Presupuesto no encontrado');
    }
}
