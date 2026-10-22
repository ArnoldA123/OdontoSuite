<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #283 S3/S6 — the cash surface that has no `branch_id` of its own.
 *
 * A transaction belongs to the sede of its drawer, pending payments are agenda
 * (not the shared patient file), and the branch tariff is read only for the
 * assigned sede. These are the surfaces the independent verification flagged
 * after the first pass.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class BranchScopeCashSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private function user(?Branch $branch, string $role = 'finanzas'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function openDrawer(User $owner, Branch $branch): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $owner->id,
            'branch_id' => $branch->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    private function transactionIn(CashRegisterSession $session): Transaction
    {
        return Transaction::factory()->create([
            'cash_register_session_id' => $session->id,
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'created_by' => $session->user_id,
            'type' => 'payment',
            'status' => 'completed',
            'notes' => 'Nota original',
        ]);
    }

    public function test_another_sede_transaction_is_not_reachable_by_id(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own);
        $this->openDrawer($actor, $own);

        $theirs = $this->transactionIn($this->openDrawer($this->user($other, 'recepcionista'), $other));

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/transactions/{$theirs->id}")
            ->assertNotFound();
    }

    public function test_the_own_sede_transaction_is_readable(): void
    {
        $own = Branch::factory()->create();
        $actor = $this->user($own);
        $mine = $this->transactionIn($this->openDrawer($actor, $own));

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/transactions/{$mine->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id);
    }

    public function test_updating_another_sede_transaction_is_forbidden(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own);
        $this->openDrawer($actor, $own);

        $theirs = $this->transactionIn($this->openDrawer($this->user($other, 'recepcionista'), $other));

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/transactions/{$theirs->id}", ['notes' => 'Nota reescrita'])
            ->assertForbidden();

        $this->assertSame('Nota original', $theirs->fresh()->notes);
    }

    public function test_deleting_another_sede_transaction_is_forbidden(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own);
        $this->openDrawer($actor, $own);

        $theirs = $this->transactionIn($this->openDrawer($this->user($other, 'recepcionista'), $other));

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/transactions/{$theirs->id}")
            ->assertForbidden();

        $this->assertNull($theirs->fresh()->deleted_at);
    }

    private function completedAppointmentIn(Branch $branch): Appointment
    {
        return Appointment::factory()->create([
            'patient_id' => Patient::factory()->create(['branch_id' => $branch->id])->id,
            'branch_id' => $branch->id,
            'status' => 'completed',
            'scheduled_at' => now()->subDay(),
            'total_cost' => 100,
            'paid_amount' => 0,
            'balance' => 100,
        ]);
    }

    public function test_pending_payments_only_list_the_assigned_sede(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own, 'recepcionista');

        $mine = $this->completedAppointmentIn($own);
        $theirs = $this->completedAppointmentIn($other);

        $response = $this->actingAs($actor, 'sanctum')->getJson('/api/pending-payments');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_paying_a_pending_payment_of_another_sede_is_forbidden_and_writes_nothing(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own, 'recepcionista');
        $theirs = $this->completedAppointmentIn($other);

        $this->actingAs($actor, 'sanctum')
            ->postJson("/api/pending-payments/{$theirs->id}/pay", [
                'payment_method_id' => PaymentMethod::factory()->create()->id,
                'amount' => 50,
            ])
            ->assertForbidden();

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0.0, (float) $theirs->fresh()->paid_amount);
    }

    public function test_naming_another_sede_in_the_tariff_read_is_forbidden(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own, 'administrador');

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/branches/{$other->id}/procedure-prices")
            ->assertForbidden();

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/branches/{$own->id}/procedure-prices")
            ->assertOk();
    }

    public function test_clearing_the_sede_of_a_user_is_forbidden_not_a_server_error(): void
    {
        $own = Branch::factory()->create();
        $actor = $this->user($own, 'administrador');
        $target = $this->user($own, 'odontologo');

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'username' => $target->username,
                'role' => 'odontologo',
                'branch_id' => null,
            ])
            ->assertForbidden();

        $this->assertSame($own->id, $target->fresh()->branch_id);
    }
}
