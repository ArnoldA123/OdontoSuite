<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\PatientCharge;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\PatientChargeService;
use App\Services\PatientStatementService;
use App\Services\TransactionService;
use App\Support\MoneyVocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * #287 (T3, S3/S6/S9) — the patient statement.
 *
 * The statement is the patient ledger walked in order: the charges the acts and
 * the counter wrote, the payments with their discount, the refunds that raise
 * the debt and the write-offs and adjustments that move it without touching the
 * drawer. Its summary is the reconciliation the issue asks for: the totals of
 * the period must agree with the transactions of the period, and the balance
 * must be what the patient still owes.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
#[Group('mysql')]
class PatientStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private PaymentMethod $method;

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

        $this->method = PaymentMethod::factory()->create();
    }

    /**
     * The day the ledger is written: the rows carry their own dates, and the
     * statement is read afterwards.
     */
    private function at(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment));
    }

    private function patient(): Patient
    {
        return Patient::factory()->create([
            'first_name' => 'Lucía',
            'last_name' => 'Paredes',
            'document_number' => '45678912',
        ]);
    }

    /**
     * The charge the closure of an act writes.
     */
    private function actCharge(Patient $patient, float $amount): PatientCharge
    {
        $encounter = ClinicalEncounter::create([
            'patient_id' => $patient->id,
            'mode' => ClinicalEncounter::MODE_EXECUTION,
            'status' => ClinicalEncounter::STATUS_CLOSED,
            'opened_at' => now()->subHour(),
            'closed_at' => now(),
            'final_amount' => $amount,
        ]);

        $charge = app(PatientChargeService::class)->chargeForEncounter($encounter);

        $this->assertNotNull($charge);

        return $charge;
    }

    private function manualCharge(Patient $patient, float $amount, string $description): PatientCharge
    {
        return PatientCharge::create([
            'patient_id' => $patient->id,
            'source' => PatientCharge::SOURCE_MANUAL,
            'description' => $description,
            'amount' => $amount,
            'charged_at' => now(),
        ]);
    }

    /**
     * A transaction written through the service.
     *
     * @param array<string, mixed> $extra
     */
    private function transaction(string $type, Patient $patient, float $amount, array $extra = []): Transaction
    {
        return app(TransactionService::class)->createTransaction([
            'patient_id' => $patient->id,
            'payment_method_id' => MoneyVocabulary::movesCash($type) ? $this->method->id : null,
            'type' => $type,
            'amount' => $amount,
            'description' => $extra['description'] ?? 'Movimiento de prueba',
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Autorizado para la prueba',
            ...$extra,
        ]);
    }

    /**
     * @param  array<string, string> $period
     * @return array<string, mixed>
     */
    private function statement(Patient $patient, array $period = []): array
    {
        $query = $period === [] ? '' : '?'.http_build_query($period);

        return $this->getJson("/api/patients/{$patient->id}/statement{$query}")
            ->assertOk()
            ->json('data');
    }

    /**
     * @param  array<string, mixed>                                                       $statement
     * @return list<array{0: string, 1: string, 2: string, 3: float, 4: float, 5: float}>
     */
    private function lines(array $statement): array
    {
        return array_map(fn (array $entry) => [
            $entry['kind'],
            $entry['reference'],
            $entry['description'],
            round((float) $entry['amount'], 2),
            round((float) $entry['effect'], 2),
            round((float) $entry['balance'], 2),
        ], $statement['entries']);
    }

    public function test_the_statement_walks_the_ledger_from_the_charges_to_the_adjustments(): void
    {
        $patient = $this->patient();

        $this->at('2026-03-01 10:00:00');
        $act = $this->actCharge($patient, 500.0);

        $this->at('2026-03-02 09:00:00');
        $manual = $this->manualCharge($patient, 200.0, 'Anticipo de ortodoncia');

        $this->at('2026-03-03 11:00:00');
        $payment = $this->transaction('payment', $patient, 300.0, [
            'description' => 'Cobro de la restauración',
            'discount_amount' => 50.0,
            'discount_authorized_by' => $this->admin->id,
        ]);

        $this->at('2026-03-05 12:00:00');
        $refund = $this->transaction('refund', $patient, 100.0, [
            'description' => 'Devolución pedida por el paciente',
        ]);

        $this->at('2026-03-06 09:00:00');
        $writeOff = $this->transaction('write_off', $patient, 50.0);

        $this->at('2026-03-07 09:00:00');
        $surcharge = $this->transaction('adjustment', $patient, 30.0);

        $this->at('2026-03-08 09:00:00');
        $correction = $this->transaction('adjustment', $patient, -20.0);

        $statement = $this->statement($patient);

        $this->assertSame([
            'id' => $patient->id,
            'first_name' => 'Lucía',
            'last_name' => 'Paredes',
            'document_number' => '45678912',
        ], $statement['patient']);

        $this->assertSame(['date_from' => '2026-03-01', 'date_to' => '2026-03-08'], $statement['period']);
        $this->assertEqualsWithDelta(0.0, $statement['opening_balance'], 0.001);
        $this->assertEqualsWithDelta(460.0, $statement['closing_balance'], 0.001);

        $this->assertSame([
            ['charge', 'CHG-'.str_pad((string) $act->id, 6, '0', STR_PAD_LEFT), 'Ejecución', 500.0, 500.0, 500.0],
            ['charge', 'CHG-'.str_pad((string) $manual->id, 6, '0', STR_PAD_LEFT), 'Anticipo de ortodoncia', 200.0, 200.0, 700.0],
            ['payment', $payment->transaction_number, 'Cobro de la restauración', 250.0, -250.0, 450.0],
            ['discount', $payment->transaction_number.'-D', 'Descuento', 50.0, -50.0, 400.0],
            ['refund', $refund->transaction_number, 'Devolución pedida por el paciente', 100.0, 100.0, 500.0],
            ['write_off', $writeOff->transaction_number, 'Condonación', 50.0, -50.0, 450.0],
            ['adjustment', $surcharge->transaction_number, 'Ajuste', 30.0, 30.0, 480.0],
            ['adjustment', $correction->transaction_number, 'Ajuste', 20.0, -20.0, 460.0],
        ], $this->lines($statement));

        $this->assertEqualsWithDelta(
            $statement['closing_balance'],
            app(PatientStatementService::class)->outstandingBalance($patient),
            0.001,
        );
    }

    public function test_the_period_summary_reconciles_against_the_transactions_of_the_period(): void
    {
        $patient = $this->patient();

        $this->at('2026-03-01 10:00:00');
        $this->actCharge($patient, 500.0);

        $this->at('2026-03-02 09:00:00');
        $this->manualCharge($patient, 200.0, 'Anticipo de ortodoncia');

        $this->at('2026-03-03 11:00:00');
        $this->transaction('payment', $patient, 300.0, [
            'discount_amount' => 50.0,
            'discount_authorized_by' => $this->admin->id,
        ]);

        $this->at('2026-03-05 12:00:00');
        $this->transaction('refund', $patient, 100.0);
        $this->at('2026-03-06 09:00:00');
        $this->transaction('write_off', $patient, 50.0);
        $this->at('2026-03-07 09:00:00');
        $this->transaction('adjustment', $patient, 30.0);
        $this->at('2026-03-08 09:00:00');
        $this->transaction('adjustment', $patient, -20.0);

        $statement = $this->statement($patient, [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);
        $summary = $statement['summary'];

        $this->assertSame([
            'charges',
            'discounts',
            'payments',
            'refunds',
            'write_offs',
            'adjustments',
            'collected',
            'forgiven',
            'balance',
        ], array_keys($summary));

        $period = fn (string $type, string $column = 'amount'): float => (float) Transaction::query()
            ->where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->where('type', $type)
            ->whereBetween('created_at', ['2026-03-01 00:00:00', '2026-03-31 23:59:59'])
            ->sum($column);

        $this->assertEqualsWithDelta(700.0, $summary['charges'], 0.001);
        $this->assertEqualsWithDelta($period(MoneyVocabulary::PAYMENT, 'discount_amount'), $summary['discounts'], 0.001);
        $this->assertEqualsWithDelta($period(MoneyVocabulary::PAYMENT), $summary['payments'], 0.001);
        $this->assertEqualsWithDelta($period(MoneyVocabulary::REFUND), $summary['refunds'], 0.001);
        $this->assertEqualsWithDelta($period(MoneyVocabulary::WRITE_OFF), $summary['write_offs'], 0.001);
        $this->assertEqualsWithDelta($period(MoneyVocabulary::ADJUSTMENT), $summary['adjustments'], 0.001);

        $this->assertEqualsWithDelta(150.0, $summary['collected'], 0.001);
        $this->assertEqualsWithDelta(50.0, $summary['forgiven'], 0.001);
        $this->assertEqualsWithDelta($statement['closing_balance'], $summary['balance'], 0.001);

        $this->assertEqualsWithDelta(
            $summary['balance'],
            $summary['charges']
                - $summary['discounts']
                - $summary['payments']
                + $summary['refunds']
                - $summary['write_offs']
                + $summary['adjustments'],
            0.001,
            'The summary must reconcile the four books of the period.',
        );
        $this->assertEqualsWithDelta(
            $summary['payments'] - $summary['refunds'],
            $summary['collected'],
            0.001,
        );
        $this->assertEqualsWithDelta($summary['write_offs'], $summary['forgiven'], 0.001);
    }

    public function test_a_voided_payment_is_left_out_of_the_statement(): void
    {
        $patient = $this->patient();

        $this->at('2026-03-01 10:00:00');
        $this->actCharge($patient, 300.0);

        $this->at('2026-03-03 11:00:00');
        $payment = $this->transaction('payment', $patient, 100.0);

        app(TransactionService::class)->voidTransaction($payment->id, 'Cobro duplicado');

        $statement = $this->statement($patient, [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);

        $this->assertSame(['charge'], array_column($statement['entries'], 'kind'));
        $this->assertEqualsWithDelta(0.0, $statement['summary']['payments'], 0.001);
        $this->assertEqualsWithDelta(300.0, $statement['closing_balance'], 0.001);
        $this->assertEqualsWithDelta(
            $statement['closing_balance'],
            app(PatientStatementService::class)->outstandingBalance($patient),
            0.001,
        );
    }

    public function test_the_opening_balance_carries_everything_before_the_period(): void
    {
        $patient = $this->patient();

        $this->at('2026-02-10 10:00:00');
        $this->actCharge($patient, 300.0);

        $this->at('2026-02-20 11:00:00');
        $this->transaction('payment', $patient, 100.0);

        $this->at('2026-03-15 09:00:00');
        $this->manualCharge($patient, 50.0, 'Radiografía panorámica');

        $statement = $this->statement($patient, [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);

        $this->assertEqualsWithDelta(200.0, $statement['opening_balance'], 0.001);
        $this->assertEqualsWithDelta(250.0, $statement['closing_balance'], 0.001);
        $this->assertSame(['charge'], array_column($statement['entries'], 'kind'));
        $this->assertSame([
            ['charge', $statement['entries'][0]['reference'], 'Radiografía panorámica', 50.0, 50.0, 250.0],
        ], $this->lines($statement));
        $this->assertEqualsWithDelta(
            $statement['closing_balance'],
            app(PatientStatementService::class)->outstandingBalance($patient),
            0.001,
        );
    }

    public function test_the_period_defaults_to_the_current_month_through_today(): void
    {
        $patient = $this->patient();

        $this->at(now()->subMonthNoOverflow()->startOfMonth()->addDay()->toDateTimeString());
        $this->manualCharge($patient, 80.0, 'Cargo del mes anterior');

        Carbon::setTestNow();

        $this->manualCharge($patient, 40.0, 'Cargo de hoy');

        $statement = $this->statement($patient);

        $this->assertSame([
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ], $statement['period']);
        $this->assertSame(['Cargo de hoy'], array_column($statement['entries'], 'description'));
        $this->assertEqualsWithDelta(80.0, $statement['opening_balance'], 0.001);
        $this->assertEqualsWithDelta(120.0, $statement['closing_balance'], 0.001);
    }

    public function test_a_clinical_role_cannot_read_the_statement(): void
    {
        $patient = $this->patient();
        $professional = User::factory()->create(['role' => 'odontologo', 'is_active' => true]);

        $this->actingAs($professional, 'sanctum')
            ->getJson("/api/patients/{$patient->id}/statement")
            ->assertForbidden();
    }

    public function test_the_statement_refuses_a_reversed_range(): void
    {
        $patient = $this->patient();

        $this->getJson("/api/patients/{$patient->id}/statement?date_from=2026-03-31&date_to=2026-03-01")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');

        $this->getJson("/api/patients/{$patient->id}/statement?date_from=no-es-fecha")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_from');
    }

    public function test_the_statement_of_an_unknown_patient_is_a_404(): void
    {
        $this->getJson('/api/patients/999999/statement')->assertNotFound();
    }

    public function test_a_legacy_discount_transaction_reduces_the_balance_instead_of_hiding(): void
    {
        $patient = $this->patient();

        $this->at('2026-03-02 10:00:00');
        PatientCharge::create([
            'patient_id' => $patient->id,
            'source' => PatientCharge::SOURCE_MANUAL,
            'description' => 'Cargo con descuento histórico',
            'amount' => 100.0,
            'charged_at' => now(),
        ]);

        $this->at('2026-03-02 11:00:00');
        Transaction::create([
            'patient_id' => $patient->id,
            'payment_method_id' => $this->method->id,
            'created_by' => $this->admin->id,
            'transaction_number' => 'TXN-LEGACY-0001',
            'type' => MoneyVocabulary::DISCOUNT,
            'amount' => 30.0,
            'description' => 'Descuento histórico',
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        $statement = app(PatientStatementService::class)->forPatient($patient, '2026-03-01', '2026-03-31');

        $this->assertSame(['charge', 'discount'], array_column($statement['entries'], 'kind'));
        $this->assertEqualsWithDelta(30.0, $statement['summary']['discounts'], 0.001);
        $this->assertEqualsWithDelta(70.0, $statement['closing_balance'], 0.001);
    }
}
