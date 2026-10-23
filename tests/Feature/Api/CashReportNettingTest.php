<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PatientCharge;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #287 (T4, S7) — the money aggregates net the refunds and ignore the ledger.
 *
 * Every aggregate that describes the drawer reads `payment` and `refund` only:
 * a refund subtracts, and a write-off or an adjustment never enters the method
 * mix, the income or the ranking, because they are ledger entries with no
 * session and no method (D4). The percentages divide by the net mix.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashReportNettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private CashRegisterSession $session;

    private PaymentMethod $cashMethod;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->branch = Branch::factory()->create();

        $this->actingAs($this->admin, 'sanctum');

        $this->cashMethod = PaymentMethod::factory()->create(['name' => 'Efectivo', 'type' => 'cash']);
        $this->patient = Patient::factory()->create();

        $this->session = app(CashRegisterService::class)->openSession([
            'branch_id' => $this->branch->id,
            'opening_amount' => 0.0,
        ]);

        // Debt the write-off has something to forgive (#287, T3).
        PatientCharge::create([
            'patient_id' => $this->patient->id,
            'source' => PatientCharge::SOURCE_MANUAL,
            'description' => 'Cargo de prueba',
            'amount' => 200,
            'charged_at' => now(),
        ]);

        $this->register(100.0, 'payment');
        $this->register(20.0, 'refund');
        $this->register(30.0, 'write_off');
    }

    private function register(float $amount, string $type): Transaction
    {
        return app(TransactionService::class)->createTransaction([
            'patient_id' => $this->patient->id,
            'payment_method_id' => $type === 'payment' || $type === 'refund' ? $this->cashMethod->id : null,
            'amount' => $amount,
            'type' => $type,
            'description' => 'Reporte de caja',
            ...($type === 'payment' ? [] : ['authorization_reason' => 'Autorizado en la prueba']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dailyReport(): array
    {
        return $this->getJson('/api/cash-reports/daily?date='.now()->toDateString())
            ->assertOk()
            ->json('data');
    }

    /**
     * @return array<string, mixed>
     */
    private function periodReport(): array
    {
        $query = http_build_query([
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        return $this->getJson('/api/cash-register/reports/period?'.$query)
            ->assertOk()
            ->json('data');
    }

    public function test_the_daily_method_mix_nets_the_refunds(): void
    {
        $summary = collect($this->dailyReport()['summary_by_payment_method'])->keyBy('payment_method');

        $this->assertEqualsWithDelta(80.0, $summary['Efectivo']['total'], 0.001);
        $this->assertSame(2, $summary['Efectivo']['count']);
        $this->assertEqualsWithDelta(100.0, $summary['Efectivo']['percentage'], 0.001);

        $this->assertEqualsWithDelta(
            80.0,
            (float) collect($this->dailyReport()['summary_by_payment_method'])->sum('total'),
            0.001
        );
    }

    public function test_the_period_analysis_nets_the_refunds(): void
    {
        $analysis = collect($this->periodReport()['payment_methods_analysis'])->keyBy('payment_method');

        $this->assertCount(1, $analysis);
        $this->assertEqualsWithDelta(80.0, $analysis['Efectivo']['total'], 0.001);
        $this->assertEqualsWithDelta(40.0, $analysis['Efectivo']['average'], 0.001);
        $this->assertEqualsWithDelta(100.0, $analysis['Efectivo']['percentage'], 0.001);
    }

    public function test_the_session_details_and_the_hourly_buckets_net_the_refunds(): void
    {
        $report = $this->dailyReport();
        $session = $report['sessions'][0];

        $this->assertEqualsWithDelta(80.0, $session['transactions_total'], 0.001);
        $this->assertEqualsWithDelta(80.0, $session['by_payment_method'][0]['total'], 0.001);
        $this->assertEqualsWithDelta(80.0, (float) collect($report['summary_by_hour'])->sum('total'), 0.001);
    }

    public function test_a_write_off_is_absent_from_the_income_and_the_method_mix(): void
    {
        $daily = $this->dailyReport();
        $period = $this->periodReport();

        $this->assertEqualsWithDelta(100.0, $period['total_income'], 0.001);
        $this->assertEqualsWithDelta(20.0, $period['total_expenses'], 0.001);
        $this->assertEqualsWithDelta(80.0, (float) collect($period['payment_methods_analysis'])->sum('total'), 0.001);
        $this->assertEqualsWithDelta(80.0, (float) collect($daily['summary_by_payment_method'])->sum('total'), 0.001);

        $this->assertNotContains(
            'write_off',
            collect($daily['top_transactions'])->pluck('type')->all(),
            'A write-off is a ledger entry, not a drawer movement.'
        );
    }
}
