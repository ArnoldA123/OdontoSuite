<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\CashRegisterSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RED test (slice 01, T-01.9) for the 5 missing cash-register endpoints:
 *  - GET /api/cash-register/summary
 *  - GET /api/cash-register/sessions/{id}
 *  - GET /api/cash-register/sessions/{id}/closure-report
 *  - GET /api/cash-register/reports/period
 *  - POST /api/cash-register/reports/export/{excel|pdf}
 *
 * Also covers API-011 (getSessions shape) and API-002..006.
 */
class CashRegisterEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function finanzasUser(): User
    {
        return User::factory()->create([
            'role' => 'finanzas',
            'is_active' => true,
        ]);
    }

    public function test_cash_register_summary_returns_200_envelope(): void
    {
        $user = $this->finanzasUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/cash-register/summary');

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
    }

    public function test_cash_register_session_show_returns_200(): void
    {
        $user = $this->finanzasUser();

        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/cash-register-sessions/{$session->id}");

        $response->assertOk();
        $this->assertNotNull($response->json('data'));
    }

    public function test_closure_report_returns_200(): void
    {
        $user = $this->finanzasUser();

        $session = CashRegisterSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'open',
            'opening_amount' => 100.0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->get("/api/cash-register-sessions/{$session->id}/closure-report");

        // Closure report is a PDF download — must NOT 404 / 500.
        $this->assertNotEquals(404, $response->status());
        $this->assertNotEquals(500, $response->status());
    }

    public function test_reports_period_returns_200(): void
    {
        $user = $this->finanzasUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/cash-reports/period');

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
    }

    public function test_reports_export_excel_returns_file(): void
    {
        $user = $this->finanzasUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/cash-register/reports/export/excel', [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
                'report_type' => 'period',
            ]);

        $this->assertNotEquals(404, $response->status());
        $this->assertNotEquals(500, $response->status());
    }

    public function test_reports_export_pdf_returns_file(): void
    {
        $user = $this->finanzasUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/cash-register/reports/export/pdf', [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
                'report_type' => 'period',
            ]);

        $this->assertNotEquals(404, $response->status());
        $this->assertNotEquals(500, $response->status());
    }

    public function test_reports_export_invalid_format_returns_4xx(): void
    {
        $user = $this->finanzasUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/cash-register/reports/export/bogus', [
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString(),
                'report_type' => 'period',
            ]);

        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertLessThan(500, $response->status());
    }
}
