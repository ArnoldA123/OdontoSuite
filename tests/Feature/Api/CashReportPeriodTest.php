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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract guard for the cash report arithmetic (caja interface redesign, T2).
 *
 * The period report separates completed sales into `total_income`
 * (payment/income) and `total_expenses` (refund/expense), and every payment
 * method percentage divides by the mix total, never by `closing_amount`.
 * The daily report keeps its own session payload, whose cash flow comes from
 * the movement ledger (`cash_in`/`cash_out`/`net_flow`), not from a
 * `movements_total` sum that mixed snapshots with flows.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashReportPeriodTest extends TestCase
{
    use RefreshDatabase;

    private const MIX_TOTAL = 170.0;

    private User $admin;

    private Branch $branch;

    private PaymentMethod $cashMethod;

    private Patient $patient;

    private PaymentMethod $cardMethod;

    private CashRegisterSession $session;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->branch = Branch::factory()->create();

        $this->actingAs($this->admin, 'sanctum');

        $this->session = app(CashRegisterService::class)->openSession([
            'branch_id' => $this->branch->id,
            'opening_amount' => 100.0,
        ]);

        $this->cashMethod = PaymentMethod::factory()->create(['name' => 'Efectivo']);
        $this->cardMethod = PaymentMethod::factory()->create(['name' => 'Tarjeta']);
        $this->patient = Patient::factory()->create();

        $this->register(100.0, $this->cashMethod, 'payment');
        $this->register(50.0, $this->cardMethod, 'payment');
        $this->register(20.0, $this->cashMethod, 'refund');

        app(CashRegisterService::class)->closeSession($this->session->id, [
            'closing_amount' => 230.0,
        ]);

        $this->today = now()->toDateString();
    }

    private function register(float $amount, PaymentMethod $method, string $type): Transaction
    {
        return app(TransactionService::class)->createTransaction([
            'patient_id' => $this->patient->id,
            'payment_method_id' => $method->id,
            'amount' => $amount,
            'type' => $type,
            'description' => 'Reporte de caja',
            ...($type === 'refund' ? ['authorization_reason' => 'Devolución de prueba'] : []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function periodReport(?int $branchId = null): array
    {
        $query = http_build_query(array_filter([
            'start_date' => $this->today,
            'end_date' => $this->today,
            'branch_id' => $branchId,
        ]));

        $response = $this->getJson('/api/cash-register/reports/period?'.$query);
        $response->assertOk();

        return $response->json('data');
    }

    /**
     * @return array<string, mixed>
     */
    private function dailyReport(string $date): array
    {
        $response = $this->getJson('/api/cash-reports/daily?date='.$date);
        $response->assertOk();

        return $response->json('data');
    }

    public function test_the_period_report_separates_completed_sales_into_income_and_expenses(): void
    {
        $report = $this->periodReport();

        $this->assertEqualsWithDelta(150.0, $report['total_income'], 0.001);
        $this->assertEqualsWithDelta(20.0, $report['total_expenses'], 0.001);

        $this->assertSame(1, $report['sessions_count']);

        foreach ([
            'total_opening_amount',
            'total_closing_amount',
            'total_expected_amount',
            'total_difference',
            'average_difference',
            'daily_summary',
            'summary_by_branch',
            'summary_by_user',
            'payment_methods_analysis',
        ] as $key) {
            $this->assertArrayHasKey($key, $report, "The period report lost its `{$key}` key.");
        }
    }

    public function test_payment_method_analysis_percentages_divide_by_the_mix_total(): void
    {
        $analysis = $this->periodReport()['payment_methods_analysis'];

        $this->assertCount(2, $analysis);

        $byMethod = collect($analysis)->keyBy('payment_method');

        $this->assertEqualsWithDelta(120.0, $byMethod['Efectivo']['total'], 0.001);
        $this->assertEqualsWithDelta(70.59, $byMethod['Efectivo']['percentage'], 0.001);
        $this->assertEqualsWithDelta(29.41, $byMethod['Tarjeta']['percentage'], 0.001);

        $sum = 0.0;
        foreach ($analysis as $item) {
            $this->assertEqualsWithDelta(
                round($item['total'] / self::MIX_TOTAL * 100, 2),
                $item['percentage'],
                0.001,
                'Every percentage must be its own total over the mix total.'
            );
            $sum += $item['percentage'];
        }

        $this->assertEqualsWithDelta(100.0, $sum, 0.01);
    }

    public function test_the_daily_report_uses_mix_percentages_and_the_ledger_cash_flow(): void
    {
        $report = $this->dailyReport($this->today);

        $summary = collect($report['summary_by_payment_method'])->keyBy('payment_method');

        $this->assertEqualsWithDelta(70.59, $summary['Efectivo']['percentage'], 0.001);
        $this->assertEqualsWithDelta(29.41, $summary['Tarjeta']['percentage'], 0.001);
        $this->assertEqualsWithDelta(100.0, $summary->sum('percentage'), 0.01);

        $this->assertCount(1, $report['sessions']);

        $session = $report['sessions'][0];

        $this->assertEqualsWithDelta(150.0, $session['cash_in'], 0.001);
        $this->assertEqualsWithDelta(20.0, $session['cash_out'], 0.001);
        $this->assertEqualsWithDelta(130.0, $session['net_flow'], 0.001);
        $this->assertArrayHasKey('movements_count', $session);
        $this->assertArrayNotHasKey(
            'movements_total',
            $session,
            'movements_total summed the opening and closing snapshots; it must not survive.'
        );
    }

    public function test_the_period_totals_follow_the_branch_filter(): void
    {
        $otherBranch = Branch::factory()->create();

        app(CashRegisterService::class)->openSession([
            'branch_id' => $otherBranch->id,
            'opening_amount' => 0.0,
        ]);
        $this->register(30.0, $this->cardMethod, 'payment');
        app(CashRegisterService::class)->closeSession(
            CashRegisterSession::where('branch_id', $otherBranch->id)->value('id'),
            ['closing_amount' => 30.0]
        );

        $ownBranch = $this->periodReport($this->branch->id);
        $this->assertEqualsWithDelta(150.0, $ownBranch['total_income'], 0.001);
        $this->assertEqualsWithDelta(20.0, $ownBranch['total_expenses'], 0.001);

        $other = $this->periodReport($otherBranch->id);
        $this->assertEqualsWithDelta(30.0, $other['total_income'], 0.001);
        $this->assertEqualsWithDelta(0.0, $other['total_expenses'], 0.001);

        $this->assertEqualsWithDelta(180.0, $this->periodReport()['total_income'], 0.001);
    }

    public function test_percentages_are_zero_when_the_mix_total_is_zero(): void
    {
        $report = $this->dailyReport(now()->subDay()->toDateString());

        $this->assertCount(2, $report['summary_by_payment_method']);

        foreach ($report['summary_by_payment_method'] as $item) {
            $this->assertEqualsWithDelta(0.0, $item['total'], 0.001);
            $this->assertEqualsWithDelta(0.0, $item['percentage'], 0.001);
        }
    }
}
