<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Issue #196 — the 500 of the pending-payments endpoints answered with the
 * exception message (and, in `index`, the stack trace), the same information
 * leak already fixed in the procedure and BI reports. A failing request must
 * answer one generic message and record the exception in the log.
 *
 * Every query fails through a DB listener so the controller's catch block is
 * the only thing under test.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class PendingPaymentsErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function failEveryQuery(): void
    {
        DB::listen(function () {
            throw new RuntimeException('leaking detail: appointments table missing');
        });
    }

    public function test_the_index_failure_hides_the_exception_and_records_it(): void
    {
        $admin = $this->admin();
        Log::spy();
        $this->failEveryQuery();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/cash-register/pending-payments');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Error al obtener los pagos pendientes')
            ->assertJsonMissingPath('error')
            ->assertJsonMissingPath('trace');

        $this->assertStringNotContainsString('leaking detail', $response->getContent());

        Log::shouldHaveReceived('error')->once();
    }

    public function test_the_pay_failure_hides_the_exception_and_records_it(): void
    {
        $admin = $this->admin();
        Log::spy();
        $this->failEveryQuery();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/pending-payments/1/pay', [
                'payment_method_id' => 1,
                'amount' => 10,
            ]);

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Error al procesar el pago pendiente')
            ->assertJsonMissingPath('error')
            ->assertJsonMissingPath('trace');

        $this->assertStringNotContainsString('leaking detail', $response->getContent());

        Log::shouldHaveReceived('error')->once();
    }
}
