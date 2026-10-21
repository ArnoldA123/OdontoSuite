<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Issue #195, decision B — a manual cash movement cannot be an `adjustment`.
 *
 * The ledger is the source of the arqueo: `cash_in` is income + deposit and
 * `cash_out` is expense + withdrawal. An `adjustment` row carries no sign, so
 * the closing snapshot stays out of the equation by design. The form and the
 * endpoint nevertheless offered `adjustment` as a manual type, producing a
 * "correction" that moved no figure. The manual surface now only accepts the
 * four signed types; `CashRegisterService::closeSession()` keeps writing its
 * `adjustment` row straight into the model, so the closing snapshot survives.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashMovementManualWhitelistTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private CashRegisterSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->branch = Branch::factory()->create();

        $this->actingAs($this->admin, 'sanctum');

        $this->session = CashRegisterSession::factory()->create([
            'user_id' => $this->admin->id,
            'branch_id' => $this->branch->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cash_register_session_id' => $this->session->id,
            'concept' => 'deposit',
            'type' => 'income',
            'amount' => 25.5,
            'description' => 'Movimiento manual',
        ], $overrides);
    }

    private function movementCount(): int
    {
        $response = $this->getJson("/api/cash-register/sessions/{$this->session->id}/movements");
        $response->assertOk();

        return (int) $response->json('meta.total');
    }

    public function test_adjustment_is_rejected_as_a_manual_movement_type(): void
    {
        $before = $this->movementCount();

        $this->postJson('/api/cash-movements', $this->payload([
            'type' => 'adjustment',
            'concept' => 'deposit',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->assertSame(
            $before,
            $this->movementCount(),
            'A rejected movement must not reach the ledger.'
        );
    }

    public function test_adjustment_is_rejected_as_a_manual_movement_concept(): void
    {
        $this->postJson('/api/cash-movements', $this->payload([
            'type' => 'income',
            'concept' => 'adjustment',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('concept');
    }

    #[DataProvider('acceptedManualTypes')]
    public function test_the_four_signed_types_are_still_accepted(string $type, string $concept): void
    {
        $before = $this->movementCount();

        $response = $this->postJson('/api/cash-movements', $this->payload([
            'type' => $type,
            'concept' => $concept,
        ]));

        $response->assertCreated();
        $this->assertSame($type, $response->json('data.type'));
        $this->assertSame($before + 1, $this->movementCount());
    }

    public static function acceptedManualTypes(): array
    {
        return [
            'income' => ['income', 'deposit'],
            'expense' => ['expense', 'withdrawal'],
            'withdrawal' => ['withdrawal', 'withdrawal'],
            'deposit' => ['deposit', 'deposit'],
        ];
    }

    public function test_the_closing_snapshot_is_still_written_as_an_adjustment(): void
    {
        $this->postJson('/api/cash-register/close', [
            'session_id' => $this->session->id,
            'closing_amount' => 100.0,
        ])->assertOk();

        $movements = $this->getJson("/api/cash-register/sessions/{$this->session->id}/movements")
            ->assertOk()
            ->json('data');

        $closing = collect($movements)->firstWhere('type', 'adjustment');

        $this->assertNotNull($closing, 'The closing snapshot must keep its adjustment row.');
        $this->assertSame('Cierre de caja', $closing['description']);
        $this->assertEqualsWithDelta(100.0, (float) $closing['amount'], 0.001);

        // The snapshot is the count, not a flow: expected == opening with no
        // manual movements in the session.
        $session = $this->getJson("/api/cash-register-sessions/{$this->session->id}")
            ->assertOk()
            ->json('data');

        $this->assertEqualsWithDelta(100.0, (float) $session['expected_amount'], 0.001);
    }
}
