<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice 04 / T-04.9 RED tests.
 *
 * Asserts every endpoint removed by slice 04 returns 404 (or 405 for method-not-allowed
 * depending on route fallback) instead of the previous 501 / 200 stub. Covers:
 *  - BF-003 (WaitingListController removed; frontend listeners also removed)
 *  - BF-005 (AuthController::refresh removed)
 *  - BF-006 (RoleController CRUD removed)
 *  - API-016 (PendingPaymentsController@show removed)
 *  - API-017 (CashReportController::exportExcel/exportPdf removed)
 *  - API-037 (AppointmentBlockController apiResource removed)
 *  - API-038 (CalendarController getEvents/getAvailability removed)
 *  - API-039 (InterconsultationController removed)
 *  - API-041 (WorkScheduleController apiResource removed)
 *  - API-042 (WaitingListController apiResource removed)
 *  - API-043 (OdontogramController apiResource removed)
 *
 * Acceptance: every endpoint below returns 404 after slice 04. Before slice 04
 * the endpoints either return 200 (working stubs), 501 (waiting-list update/destroy),
 * or are wired to dead methods. RED on main, GREEN after backend removal.
 */
class StubsRemovedEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function clinicalUser(): User
    {
        return User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);
    }

    // ----------------------------------------------------------------
    // BF-003 / API-042: WaitingList apiResource removed
    // ----------------------------------------------------------------

    public function test_waiting_lists_index_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/waiting-lists');

        $response->assertNotFound();
    }

    public function test_waiting_lists_store_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson('/api/waiting-lists', [
                'patient_id' => 1,
                'appointment_type_id' => 1,
            ]);

        $response->assertNotFound();
    }

    public function test_waiting_lists_show_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/waiting-lists/1');

        $response->assertNotFound();
    }

    public function test_waiting_lists_update_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->putJson('/api/waiting-lists/1', ['priority' => 5]);

        $response->assertNotFound();
    }

    public function test_waiting_lists_destroy_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->deleteJson('/api/waiting-lists/1');

        $response->assertNotFound();
    }

    // ----------------------------------------------------------------
    // BF-006: RoleController CRUD removed (only index was wired; no FE consumer)
    // ----------------------------------------------------------------

    public function test_roles_index_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/roles');

        $response->assertNotFound();
    }

    // ----------------------------------------------------------------
    // BF-005: AuthController::refresh has no route (was orphan method)
    // ----------------------------------------------------------------

    public function test_auth_refresh_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson('/api/auth/refresh');

        $response->assertNotFound();
    }

    // ----------------------------------------------------------------
    // API-038: CalendarController getEvents / getAvailability removed
    // ----------------------------------------------------------------

    public function test_calendar_events_returns_404(): void
    {
        $response = $this->actingAs($this->clinicalUser(), 'sanctum')
            ->getJson('/api/calendar/events', [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
            ]);

        $response->assertNotFound();
    }

    public function test_calendar_availability_returns_404(): void
    {
        $response = $this->actingAs($this->clinicalUser(), 'sanctum')
            ->getJson('/api/calendar/availability', [
                'date' => '2026-08-01',
                'user_id' => 1,
            ]);

        $response->assertNotFound();
    }

    // ----------------------------------------------------------------
    // API-039: InterconsultationController apiResource removed
    // ----------------------------------------------------------------

    public function test_interconsultations_index_returns_404(): void
    {
        $response = $this->actingAs($this->clinicalUser(), 'sanctum')
            ->getJson('/api/interconsultations');

        $response->assertNotFound();
    }

    public function test_interconsultations_store_returns_404(): void
    {
        $response = $this->actingAs($this->clinicalUser(), 'sanctum')
            ->postJson('/api/interconsultations', [
                'patient_id' => 1,
                'to_specialist_id' => 2,
                'specialty_from' => 'odontologia_general',
                'specialty_to' => 'endodoncia',
                'priority' => 'medium',
            ]);

        $response->assertNotFound();
    }

    public function test_my_interconsultations_returns_404(): void
    {
        $response = $this->actingAs($this->clinicalUser(), 'sanctum')
            ->getJson('/api/my-interconsultations');

        $response->assertNotFound();
    }

    // ----------------------------------------------------------------
    // API-041: WorkSchedule apiResource removed (model still used by AppointmentService)
    // ----------------------------------------------------------------

    public function test_work_schedules_index_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/work-schedules');

        $response->assertNotFound();
    }

    public function test_work_schedules_store_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson('/api/work-schedules', [
                'user_id' => 1,
                'day_of_week' => 1,
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);

        $response->assertNotFound();
    }

    // ----------------------------------------------------------------
    // API-037: AppointmentBlock apiResource removed (model still used by AppointmentService)
    // ----------------------------------------------------------------

    public function test_appointment_blocks_index_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/appointment-blocks');

        $response->assertNotFound();
    }

    public function test_appointment_blocks_store_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson('/api/appointment-blocks', [
                'user_id' => 1,
                'starts_at' => '2026-08-01 08:00:00',
                'ends_at' => '2026-08-01 12:00:00',
                'reason' => 'lunch',
            ]);

        $response->assertNotFound();
    }

    // ----------------------------------------------------------------
    // API-043: Odontogram apiResource removed (model still used by ConsultationService)
    // ----------------------------------------------------------------

    public function test_odontograms_index_returns_404(): void
    {
        $response = $this->actingAs($this->clinicalUser(), 'sanctum')
            ->getJson('/api/odontograms');

        $response->assertNotFound();
    }

    public function test_odontograms_patient_index_returns_404(): void
    {
        $response = $this->actingAs($this->clinicalUser(), 'sanctum')
            ->getJson('/api/odontograms/patient/1');

        $response->assertNotFound();
    }

    public function test_odontograms_active_returns_404(): void
    {
        $response = $this->actingAs($this->clinicalUser(), 'sanctum')
            ->getJson('/api/odontograms/active', ['patient_id' => 1]);

        $response->assertNotFound();
    }

    // ----------------------------------------------------------------
    // API-016: PendingPaymentsController::show has no route (orphan method)
    // ----------------------------------------------------------------

    public function test_pending_payments_show_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->getJson('/api/pending-payments/1');

        $response->assertNotFound();
    }

    // ----------------------------------------------------------------
    // API-017: CashReportController::exportExcel/exportPdf are dead methods
    // (slice 01 wired the unified /api/cash-register/reports/export/{format})
    // ----------------------------------------------------------------

    public function test_cash_reports_export_excel_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson('/api/cash-reports/exportExcel', [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'report_type' => 'period',
            ]);

        $response->assertNotFound();
    }

    public function test_cash_reports_export_pdf_returns_404(): void
    {
        $response = $this->actingAs($this->adminUser(), 'sanctum')
            ->postJson('/api/cash-reports/exportPdf', [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
                'report_type' => 'period',
            ]);

        $response->assertNotFound();
    }
}
