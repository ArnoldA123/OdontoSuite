<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #197, decision A — the period report has one population: completed
 * transactions created inside `[start, end]`. Sessions joined the report by
 * `opened_at` while sales joined by `created_at`, so a session crossing
 * midnight was attributed only to its opening day and the session block never
 * reconciled with the sales block.
 *
 * The mix and the hourly buckets used to query every session once per payment
 * method (and once more for the hourly buckets). The report now reads one
 * aggregate per range and the query count must not grow with the number of
 * sessions.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashReportPopulationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private PaymentMethod $cashMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->branch = Branch::factory()->create();
        $this->cashMethod = PaymentMethod::factory()->create(['name' => 'Efectivo']);

        $this->actingAs($this->admin, 'sanctum');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function register(float $amount, string $type = 'payment'): Transaction
    {
        return app(TransactionService::class)->createTransaction([
            'patient_id' => Patient::factory()->create()->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => $amount,
            'type' => $type,
            'description' => 'Reporte de caja',
        ]);
    }

    private function openSessionAt(string $moment, float $opening = 0.0): CashRegisterSession
    {
        Carbon::setTestNow($moment);

        return app(CashRegisterService::class)->openSession([
            'branch_id' => $this->branch->id,
            'opening_amount' => $opening,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function periodReport(string $start, string $end): array
    {
        $query = http_build_query([
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $response = $this->getJson('/api/cash-register/reports/period?'.$query);
        $response->assertOk();

        return $response->json('data');
    }

    private function queryCount(string $start, string $end): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->periodReport($start, $end);

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_period_report_declares_its_population(): void
    {
        $report = $this->periodReport('2026-03-01', '2026-03-31');

        $this->assertSame('transaction_date', $report['population']['basis']);
        $this->assertSame('2026-03-01', $report['population']['start_date']);
        $this->assertSame('2026-03-31', $report['population']['end_date']);
        $this->assertNotEmpty($report['population']['description']);
    }

    public function test_a_session_crossing_midnight_splits_its_sales_by_transaction_date(): void
    {
        $session = $this->openSessionAt('2026-03-10 22:00:00');

        Carbon::setTestNow('2026-03-10 23:50:00');
        $this->register(100.0);

        Carbon::setTestNow('2026-03-11 00:10:00');
        $this->register(40.0);

        Carbon::setTestNow('2026-03-11 01:00:00');
        app(CashRegisterService::class)->closeSession($session->id, [
            'closing_amount' => 140.0,
        ]);

        $openingDay = $this->periodReport('2026-03-10', '2026-03-10');
        $nextDay = $this->periodReport('2026-03-11', '2026-03-11');

        $this->assertEqualsWithDelta(100.0, $openingDay['total_income'], 0.001);
        $this->assertEqualsWithDelta(40.0, $nextDay['total_income'], 0.001);

        $this->assertSame(1, $openingDay['sessions_count']);
        $this->assertSame(1, $nextDay['sessions_count']);

        $this->assertEqualsWithDelta(140.0, $openingDay['total_opening_amount'] + $openingDay['total_income'] + $nextDay['total_income'], 0.001);
        $this->assertEqualsWithDelta(
            40.0,
            collect($nextDay['payment_methods_analysis'])->firstWhere('payment_method', 'Efectivo')['total'],
            0.001
        );
    }

    public function test_daily_summary_reconciles_with_the_period_sales(): void
    {
        $session = $this->openSessionAt('2026-03-10 09:00:00', 50.0);

        Carbon::setTestNow('2026-03-10 10:00:00');
        $this->register(100.0);

        Carbon::setTestNow('2026-03-11 10:00:00');
        $this->register(30.0);
        $this->register(10.0, 'refund');

        app(CashRegisterService::class)->closeSession($session->id, [
            'closing_amount' => 170.0,
        ]);

        $report = $this->periodReport('2026-03-10', '2026-03-11');

        $daily = collect($report['daily_summary'])->keyBy('date');

        $this->assertCount(2, $daily);

        $this->assertSame(1, $daily['2026-03-10']['sessions_count']);
        $this->assertSame(1, $daily['2026-03-11']['sessions_count']);
        $this->assertEqualsWithDelta(50.0, $daily['2026-03-10']['total_opening'], 0.001);
        $this->assertEqualsWithDelta(100.0, $daily['2026-03-10']['total_income'], 0.001);
        $this->assertEqualsWithDelta(30.0, $daily['2026-03-11']['total_income'], 0.001);
        $this->assertEqualsWithDelta(10.0, $daily['2026-03-11']['total_expenses'], 0.001);

        $this->assertEqualsWithDelta(
            $report['total_income'],
            $daily->sum('total_income'),
            0.001,
            'The daily rows must reconcile with the period sales.'
        );
        $this->assertEqualsWithDelta(
            $report['total_expenses'],
            $daily->sum('total_expenses'),
            0.001,
            'The daily rows must reconcile with the period expenses.'
        );
        $this->assertSame(1, $report['sessions_count']);
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_sessions(): void
    {
        $seedDay = '2026-03-10';

        $baselineSession = $this->openSessionAt($seedDay.' 09:00:00', 10.0);
        Carbon::setTestNow($seedDay.' 10:00:00');
        $this->register(20.0);
        $this->register(15.0);

        $start = '2026-03-10';
        $end = '2026-03-12';

        // The service allows one open session per user and branch: close the
        // baseline before the loop opens the additional sessions.
        app(CashRegisterService::class)->closeSession($baselineSession->id, [
            'closing_amount' => 35.0,
        ]);

        $baseline = $this->queryCount($start, $end);

        for ($i = 0; $i < 8; $i++) {
            $session = $this->openSessionAt($seedDay.' 12:00:00');
            Carbon::setTestNow($seedDay.' 12:30:00');
            $this->register(5.0 + $i);
            app(CashRegisterService::class)->closeSession($session->id, [
                'closing_amount' => 5.0 + $i,
            ]);
        }

        $withMoreSessions = $this->queryCount($start, $end);

        $this->assertLessThanOrEqual(
            $baseline + 2,
            $withMoreSessions,
            "The period report ran {$withMoreSessions} queries with more sessions and {$baseline} with fewer; the mix or the hour buckets still query per session."
        );
    }
}
