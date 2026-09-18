<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #55: CREDENTIALS.md and the Caja nav promised recepcionista access
 * that the API middleware denied (POST cash-register-sessions, POST
 * transactions, GET quotations all 403).
 *
 * Product decision: recepcionista operates caja (sessions + transactions)
 * and reads quotations. Cash movements, reports and quotation writes stay
 * administrador/finanzas (+clinicos for quotation writes).
 */
class RecepcionistaCajaAccessTest extends TestCase
{
    use RefreshDatabase;

    private function recepcionista(): User
    {
        return User::factory()->create([
            'role' => 'recepcionista',
            'is_active' => true,
        ]);
    }

    public function test_recepcionista_can_open_cash_session(): void
    {
        $user = $this->recepcionista();
        $branch = Branch::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/cash-register/open', [
                'branch_id' => $branch->id,
                'opening_amount' => 100,
            ]);

        $response->assertCreated();
    }

    public function test_recepcionista_can_register_transaction(): void
    {
        $user = $this->recepcionista();
        $branch = Branch::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cash-register/open', [
                'branch_id' => $branch->id,
                'opening_amount' => 100,
            ])
            ->assertCreated();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/transactions', [
                'patient_id' => Patient::factory()->create()->id,
                'payment_method_id' => PaymentMethod::factory()->create()->id,
                'type' => 'payment',
                'amount' => 50,
                'description' => 'Cobro recepcion',
            ]);

        $response->assertCreated();
    }

    public function test_recepcionista_can_list_quotations_but_not_create(): void
    {
        $user = $this->recepcionista();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/quotations')
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/quotations', [])
            ->assertForbidden();
    }

    public function test_recepcionista_still_cannot_post_cash_movements(): void
    {
        $user = $this->recepcionista();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/cash-movements', [])
            ->assertForbidden();
    }
}
