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
use Tests\TestCase;

/**
 * #320 (T1, S8/S5) — the period report reconciles exactly like the daily one.
 *
 * #287 fixed the rule for a single day: cash net of its refunds, the money that
 * settled through an electronic medium, the deposits that reached the bank and
 * the cash still in the gaveta. Reportes paints that block from the period
 * report (S5), so the period must publish the same shape computed with the same
 * arithmetic instead of a second, drifting definition.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashPeriodReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private CashRegisterSession $session;

    private PaymentMethod $cashMethod;

    private PaymentMethod $cardMethod;

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

        $this->session = app(CashRegisterService::class)->openSession([
            'branch_id' => $this->branch->id,
            'opening_amount' => 100.0,
        ]);

        $this->cashMethod = PaymentMethod::factory()->create([
            'name' => 'Efectivo',
            'type' => PaymentMethod::TYPE_CASH,
        ]);
        $this->cardMethod = PaymentMethod::factory()->create([
            'name' => 'Tarjeta',
            'type' => PaymentMethod::TYPE_CARD,
            'requires_reference' => false,
        ]);
        $this->patient = Patient::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function register(float $amount, PaymentMethod $method, string $type): Transaction
    {
        return app(TransactionService::class)->createTransaction([
            'patient_id' => $this->patient->id,
            'payment_method_id' => $method->id,
            'amount' => $amount,
            'type' => $type,
            'description' => 'Cierre de período',
            ...($type === 'refund' ? ['authorization_reason' => 'Devolución de prueba'] : []),
        ]);
    }

    private function deposit(float $amount): void
    {
        $this->postJson('/api/cash-register/deposits', [
            'cash_register_session_id' => $this->session->id,
            'amount' => $amount,
            'reference' => 'BCP-PERIODO',
        ])->assertCreated();
    }

    /**
     * @return array<string, mixed>
     */
    private function periodReport(?string $start = null, ?string $end = null): array
    {
        $query = http_build_query([
            'start_date' => $start ?? now()->toDateString(),
            'end_date' => $end ?? now()->toDateString(),
        ]);

        return $this->getJson('/api/cash-register/reports/period?'.$query)
            ->assertOk()
            ->json('data');
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

    public function test_the_period_reconciliation_uses_the_same_rule_as_the_day(): void
    {
        $this->register(100.0, $this->cashMethod, 'payment');
        $this->register(50.0, $this->cardMethod, 'payment');
        $this->register(20.0, $this->cashMethod, 'refund');
        $this->deposit(60.0);

        $reconciliation = $this->periodReport()['reconciliation'];

        $this->assertEqualsWithDelta(
            80.0,
            $reconciliation['cash']['total'],
            0.001,
            'El efectivo del período es neto de su devolución.'
        );
        $this->assertSame(2, $reconciliation['cash']['count']);

        $this->assertEqualsWithDelta(50.0, $reconciliation['electronic']['total'], 0.001);
        $this->assertSame(1, $reconciliation['electronic']['count']);

        $this->assertSame(1, $reconciliation['deposits']['count']);
        $this->assertEqualsWithDelta(60.0, $reconciliation['deposits']['total'], 0.001);

        $this->assertEqualsWithDelta(
            20.0,
            $reconciliation['pending_deposit'],
            0.001,
            'El pendiente es el efectivo del período que todavía no salió a la banca.'
        );
    }

    public function test_the_period_reconciliation_keeps_the_daily_shape(): void
    {
        $this->register(100.0, $this->cashMethod, 'payment');
        $this->deposit(40.0);

        $period = $this->periodReport()['reconciliation'];
        $daily = $this->dailyReport()['reconciliation'];

        $this->assertSame(
            array_keys($daily),
            array_keys($period),
            'El bloque del período es el mismo bloque del reporte diario.'
        );

        foreach (['cash', 'electronic', 'deposits'] as $block) {
            $this->assertSame(
                array_keys($daily[$block]),
                array_keys($period[$block]),
                "El bloque `{$block}` del período no cambia de forma."
            );
        }

        $this->assertEqualsWithDelta($daily['cash']['total'], $period['cash']['total'], 0.001);
        $this->assertEqualsWithDelta($daily['electronic']['total'], $period['electronic']['total'], 0.001);
        $this->assertEqualsWithDelta($daily['deposits']['total'], $period['deposits']['total'], 0.001);
        $this->assertEqualsWithDelta($daily['pending_deposit'], $period['pending_deposit'], 0.001);
    }

    public function test_a_period_without_activity_reconciles_to_zero(): void
    {
        $empty = now()->subWeek()->toDateString();

        $reconciliation = $this->periodReport($empty, $empty)['reconciliation'];

        $this->assertSame(0, $reconciliation['cash']['count']);
        $this->assertEqualsWithDelta(0.0, $reconciliation['cash']['total'], 0.001);

        $this->assertSame(0, $reconciliation['electronic']['count']);
        $this->assertEqualsWithDelta(0.0, $reconciliation['electronic']['total'], 0.001);

        $this->assertSame(0, $reconciliation['deposits']['count']);
        $this->assertEqualsWithDelta(0.0, $reconciliation['deposits']['total'], 0.001);

        $this->assertEqualsWithDelta(0.0, $reconciliation['pending_deposit'], 0.001);
    }
}
