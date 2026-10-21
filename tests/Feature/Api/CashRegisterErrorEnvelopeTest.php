<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Cash follow-up S4 — the catch blocks of CashRegisterController answered
 * `error` with the exception message, the same leak #196 fixed in
 * pending-payments. A failing request must answer one generic message and
 * record the exception in the log.
 *
 * Every query fails through a DB listener so the controller's catch block is
 * the only thing under test; the acting user (and, for the session reads,
 * their session) exist before the listener is registered.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashRegisterErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function openSession(User $owner): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $owner->id,
            'branch_id' => Branch::factory()->create()->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);
    }

    private function failEveryQuery(): void
    {
        DB::listen(function () {
            throw new RuntimeException('leaking detail: cash_register_sessions table missing');
        });
    }

    private function assertGenericEnvelope(TestResponse $response, string $message): void
    {
        $response->assertStatus(500)
            ->assertJsonPath('message', $message)
            ->assertJsonMissingPath('error')
            ->assertJsonMissingPath('trace');

        $this->assertStringNotContainsString('leaking detail', (string) $response->getContent());

        Log::shouldHaveReceived('error')->once();
    }

    public function test_the_current_failure_hides_the_exception_and_records_it(): void
    {
        $admin = $this->admin();
        Log::spy();
        $this->failEveryQuery();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/cash-register/current');

        $this->assertGenericEnvelope($response, 'Error al obtener la sesión actual');
    }

    public function test_the_index_failure_hides_the_exception_and_records_it(): void
    {
        $admin = $this->admin();
        Log::spy();
        $this->failEveryQuery();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/cash-register-sessions');

        $this->assertGenericEnvelope($response, 'Error al obtener las sesiones de caja');
    }

    public function test_the_show_failure_hides_the_exception_and_records_it(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin);
        Log::spy();
        $this->failEveryQuery();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/cash-register-sessions/{$session->id}");

        $this->assertGenericEnvelope($response, 'Error al obtener la sesión de caja');
    }

    public function test_the_movements_failure_hides_the_exception_and_records_it(): void
    {
        $admin = $this->admin();
        $session = $this->openSession($admin);
        Log::spy();
        $this->failEveryQuery();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/cash-register/sessions/{$session->id}/movements");

        $this->assertGenericEnvelope($response, 'Error al obtener los movimientos de la sesión');
    }

    public function test_the_summary_failure_hides_the_exception_and_records_it(): void
    {
        $admin = $this->admin();
        Log::spy();
        $this->failEveryQuery();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/cash-register/summary');

        $this->assertGenericEnvelope($response, 'Error al obtener el resumen de caja');
    }
}
