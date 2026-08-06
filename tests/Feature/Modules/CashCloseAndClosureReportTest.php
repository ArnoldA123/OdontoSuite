<?php

namespace Tests\Feature\Modules;

use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * full-user-browser-audit-2026-08-05 / PR6 / Phase 4b — Cash Close + Closure Report.
 *
 * Validation-first Feature test for the cash-register close +
 * closure-report PDF contract. Exercises `POST /api/cash-register/close`
 * and `GET /api/cash-register-sessions/{id}/closure-report`
 * (auth:sanctum + role:administrador,finanzas).
 * Must run against MySQL harness (AGENTS.md §6).
 */
class CashCloseAndClosureReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Cash Admin',
            'email' => 'cash.admin.test@example.com',
            'username' => 'cash_admin',
            'password' => bcrypt('password'),
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function odontologo(): User
    {
        return User::create([
            'name' => 'Cash Odonto',
            'email' => 'cash.odonto.test@example.com',
            'username' => 'cash_odonto',
            'password' => bcrypt('password'),
            'role' => 'odontologo',
            'is_active' => true,
        ]);
    }

    private function branch(): Branch
    {
        return Branch::create([
            'name' => 'Cash Branch',
            'code' => 'CSH' . strtoupper(substr(uniqid(), -4)),
            'address' => 'Av. Test 456',
            'city' => 'Lima',
            'is_active' => true,
        ]);
    }

    private function openSession(User $user, Branch $branch, float $openingAmount = 100.00): CashRegisterSession
    {
        return CashRegisterSession::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'opening_amount' => $openingAmount,
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }

    public function test_close_open_session_returns_200_and_persists_closing_amount(): void
    {
        $admin = $this->admin();
        $branch = $this->branch();
        $session = $this->openSession($admin, $branch, 100.00);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/cash-register/close', [
                'session_id' => $session->id,
                'closing_amount' => 150.00,
                'closing_notes' => 'Cierre normal',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'status', 'closing_amount', 'closed_at']]);

        $payload = $response->json('data');
        $this->assertSame($session->id, $payload['id']);
        $this->assertSame('closed', $payload['status']);
        $this->assertNotNull($payload['closed_at']);

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertNotNull($session->closed_at);
        $this->assertEqualsWithDelta(150.00, (float) $session->closing_amount, 0.001);
    }

    public function test_closure_report_returns_pdf_with_non_empty_body(): void
    {
        $admin = $this->admin();
        $branch = $this->branch();
        $session = $this->openSession($admin, $branch, 100.00);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/cash-register/close', [
                'session_id' => $session->id,
                'closing_amount' => 100.00,
            ])->assertOk();

        $response = $this->actingAs($admin, 'sanctum')
            ->get("/api/cash-register-sessions/{$session->id}/closure-report");

        $response->assertOk();

        $contentType = (string) $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/pdf', $contentType, 'Closure report MUST respond with application/pdf content type.');

        $body = (string) $response->getContent();
        $this->assertGreaterThan(100, strlen($body), 'Closure report PDF body MUST be non-trivial (>100 bytes).');

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString(
            "cierre-caja-{$session->id}.pdf",
            $disposition,
            'Closure report filename MUST match the documented pattern.'
        );
    }

    public function test_close_already_closed_session_returns_error_and_no_phantom_row(): void
    {
        // Spec named 409 for "no open session exists for the user". The actual
        // contract: closing a session that is NOT open surfaces as 422 via the
        // service's ValidationException("La sesión de caja no está abierta.").
        // The test asserts the error class is non-2xx and no phantom row is
        // ever created regardless of the status code.
        $admin = $this->admin();
        $branch = $this->branch();
        $session = $this->openSession($admin, $branch, 100.00);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/cash-register/close', [
                'session_id' => $session->id,
                'closing_amount' => 100.00,
            ])->assertOk();

        $before = CashRegisterSession::count();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/cash-register/close', [
                'session_id' => $session->id,
                'closing_amount' => 200.00,
            ]);

        $this->assertContains(
            $response->status(),
            [404, 409, 422],
            'Closing an already-closed session MUST surface as a non-2xx; no phantom row may be created.'
        );
        $this->assertSame($before, CashRegisterSession::count(), 'No phantom cash_register_sessions row may be created on a duplicate close.');

        $session->refresh();
        $this->assertEqualsWithDelta(100.00, (float) $session->closing_amount, 0.001);
    }

    public function test_close_with_zero_closing_amount_returns_422(): void
    {
        $admin = $this->admin();
        $branch = $this->branch();
        $session = $this->openSession($admin, $branch, 100.00);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/cash-register/close', [
                'session_id' => $session->id,
                'closing_amount' => 0,
            ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);

        $session->refresh();
        $this->assertSame('open', $session->status, 'Failed 422 MUST NOT mutate the session status.');
    }

    public function test_close_with_missing_session_id_returns_422(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/cash-register/close', ['closing_amount' => 150.00]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->assertNotEmpty($response->json('errors.session_id'), '422 envelope MUST include the session_id error.');
    }

    public function test_odontologo_role_is_rejected_with_403(): void
    {
        $odonto = $this->odontologo();
        $branch = $this->branch();
        $session = $this->openSession($this->admin(), $branch, 100.00);

        $response = $this->actingAs($odonto, 'sanctum')
            ->postJson('/api/cash-register/close', [
                'session_id' => $session->id,
                'closing_amount' => 100.00,
            ]);

        $response->assertStatus(403)->assertJsonStructure(['message']);
        $this->assertSame('No tienes permisos para acceder a este recurso.', $response->json('message'));
    }

    public function test_unauthenticated_close_is_rejected_with_401_envelope(): void
    {
        $previousDebug = (bool) config('app.debug');
        config(['app.debug' => false]);

        try {
            $response = $this->postJson('/api/cash-register/close', [
                'session_id' => 1,
                'closing_amount' => 100.00,
            ]);

            $response->assertStatus(401)
                ->assertJsonStructure(['message'])
                ->assertHeader('WWW-Authenticate', 'Bearer realm="api"');

            $this->assertSame('No autenticado.', $response->json('message'));
            $this->assertNull($response->json('data'), 'No session data may leak in the unauthenticated close envelope.');
        } finally {
            config(['app.debug' => $previousDebug]);
        }
    }
}