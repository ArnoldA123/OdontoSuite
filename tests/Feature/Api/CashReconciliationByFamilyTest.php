<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #291, S5 — the day reconciles by family: physical cash on one side, every
 * medium that settles against a bank, a POS or a wallet on the other.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class CashReconciliationByFamilyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
        $this->branch = Branch::factory()->create();

        $this->actingAs($this->admin, 'sanctum');

        app(CashRegisterService::class)->openSession([
            'branch_id' => $this->branch->id,
            'opening_amount' => 0.0,
        ]);
    }

    private function method(string $type, bool $requiresReference, string $name): PaymentMethod
    {
        return PaymentMethod::factory()->create([
            'name' => $name,
            'type' => $type,
            'requires_reference' => $requiresReference,
        ]);
    }

    private function charge(PaymentMethod $method, float $amount): void
    {
        app(TransactionService::class)->createTransaction([
            'patient_id' => Patient::factory()->create()->id,
            'payment_method_id' => $method->id,
            'amount' => $amount,
            'type' => 'payment',
            'description' => 'Cobro de prueba',
            'reference_number' => $method->requiresReference() ? 'REF-'.$method->id : null,
        ]);
    }

    private function dailyReport(): array
    {
        return $this->getJson('/api/cash-reports/daily?date='.now()->toDateString())
            ->assertOk()
            ->json('data');
    }

    private function mixedDay(): void
    {
        $this->charge($this->method('cash', false, 'Efectivo'), 100.0);
        $this->charge($this->method('card', true, 'Tarjeta de crédito'), 50.0);
        $this->charge($this->method('wallet', true, 'Yape'), 30.0);
        $this->charge($this->method('transfer', true, 'Transferencia bancaria'), 20.0);
    }

    public function test_the_daily_report_separates_cash_from_electronic_media(): void
    {
        $this->mixedDay();

        $reconciliation = $this->dailyReport()['reconciliation'];

        $this->assertEqualsWithDelta(100.0, $reconciliation['cash']['total'], 0.001);
        $this->assertSame(1, $reconciliation['cash']['count']);

        $this->assertEqualsWithDelta(100.0, $reconciliation['electronic']['total'], 0.001);
        $this->assertSame(3, $reconciliation['electronic']['count']);
    }

    public function test_the_reconciliation_adds_up_to_the_method_mix(): void
    {
        $this->mixedDay();

        $report = $this->dailyReport();

        $mixTotal = array_sum(array_column($report['summary_by_payment_method'], 'total'));
        $reconciled = $report['reconciliation']['cash']['total']
            + $report['reconciliation']['electronic']['total'];

        $this->assertEqualsWithDelta(200.0, $mixTotal, 0.001);
        $this->assertEqualsWithDelta($mixTotal, $reconciled, 0.001);
    }

    public function test_each_payment_method_row_declares_its_family(): void
    {
        $this->mixedDay();

        $rows = collect($this->dailyReport()['summary_by_payment_method'])
            ->keyBy('payment_method');

        $this->assertSame('cash', $rows['Efectivo']['type']);
        $this->assertSame('card', $rows['Tarjeta de crédito']['type']);
        $this->assertSame('wallet', $rows['Yape']['type']);
        $this->assertSame('transfer', $rows['Transferencia bancaria']['type']);
    }

    public function test_a_day_with_only_cash_leaves_the_electronic_block_empty(): void
    {
        $this->charge($this->method('cash', false, 'Efectivo'), 80.0);

        $reconciliation = $this->dailyReport()['reconciliation'];

        $this->assertEqualsWithDelta(80.0, $reconciliation['cash']['total'], 0.001);
        $this->assertEqualsWithDelta(0.0, $reconciliation['electronic']['total'], 0.001);
        $this->assertSame(0, $reconciliation['electronic']['count']);
    }

    public function test_a_refused_charge_does_not_move_the_reconciliation(): void
    {
        $this->charge($this->method('cash', false, 'Efectivo'), 80.0);

        $card = $this->method('card', true, 'Tarjeta de crédito');

        $this->postJson('/api/transactions', [
            'patient_id' => Patient::factory()->create()->id,
            'payment_method_id' => $card->id,
            'amount' => 500.0,
            'type' => 'payment',
            'description' => 'Cobro sin voucher',
        ])->assertStatus(422);

        $reconciliation = $this->dailyReport()['reconciliation'];

        $this->assertEqualsWithDelta(80.0, $reconciliation['cash']['total'], 0.001);
        $this->assertEqualsWithDelta(0.0, $reconciliation['electronic']['total'], 0.001);
    }

    public function test_the_period_analysis_declares_the_family_of_each_method(): void
    {
        $this->mixedDay();

        $response = $this->getJson(
            '/api/cash-reports/period?start_date='.now()->toDateString().'&end_date='.now()->toDateString()
        )->assertOk();

        $rows = collect($response->json('data.payment_methods_analysis'))
            ->keyBy('payment_method');

        $this->assertSame('cash', $rows['Efectivo']['type']);
        $this->assertSame('card', $rows['Tarjeta de crédito']['type']);
        $this->assertSame('wallet', $rows['Yape']['type']);
    }
}
