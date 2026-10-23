<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\PatientCharge;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\PatientStatementService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * #321 (S1-S4) — which charge a payment paid.
 *
 * #287 left the debt aggregated by patient. The allocation is a view over the
 * same money: a payment still reduces the patient balance and a charge still
 * raises it, and the allocation only says how much of one settled the other.
 * Its two sum rules are the contract: the allocations of a payment never exceed
 * its amount, and a charge never receives more than its own amount.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
#[Group('mysql')]
class PatientChargeAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private PaymentMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-24 10:00:00'));

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

        $this->method = PaymentMethod::factory()->create(['type' => 'cash']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function patient(string $document = '45678912'): Patient
    {
        return Patient::factory()->create([
            'first_name' => 'Lucía',
            'last_name' => 'Paredes',
            'document_number' => $document,
        ]);
    }

    private function charge(Patient $patient, float $amount, string $description = 'Consulta'): PatientCharge
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
     * A payment written through the service, without imputation.
     *
     * @param array<string, mixed> $extra
     */
    private function payment(Patient $patient, float $amount, array $extra = []): Transaction
    {
        return app(TransactionService::class)->createTransaction([
            'patient_id' => $patient->id,
            'payment_method_id' => $this->method->id,
            'type' => 'payment',
            'amount' => $amount,
            'description' => 'Cobro de prueba',
            ...$extra,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function statement(Patient $patient): array
    {
        return $this->getJson("/api/patients/{$patient->id}/statement")
            ->assertOk()
            ->json('data');
    }

    public function test_a_payment_can_cover_several_charges_within_its_amount(): void
    {
        $patient = $this->patient();
        $consultation = $this->charge($patient, 100.0, 'Consulta');
        $restoration = $this->charge($patient, 150.0, 'Restauración');

        $this->postJson('/api/transactions', [
            'patient_id' => $patient->id,
            'payment_method_id' => $this->method->id,
            'type' => 'payment',
            'amount' => 250.0,
            'description' => 'Cobro de la atención',
            'allocations' => [
                ['patient_charge_id' => $consultation->id, 'amount' => 100.0],
                ['patient_charge_id' => $restoration->id, 'amount' => 150.0],
            ],
        ])->assertCreated();

        $this->assertDatabaseCount('patient_charge_allocations', 2);

        $statement = $this->statement($patient);

        $this->assertSame([
            ['Consulta', 100.0, 100.0, 0.0],
            ['Restauración', 150.0, 150.0, 0.0],
        ], array_map(fn (array $row) => [
            $row['description'],
            (float) $row['amount'],
            (float) $row['allocated'],
            (float) $row['balance'],
        ], $statement['charge_balances']));

        $this->assertSame(0.0, (float) $statement['unallocated_payments']);
        $this->assertSame(0.0, (float) $statement['closing_balance']);
    }

    public function test_the_allocations_of_a_payment_cannot_exceed_its_amount(): void
    {
        $patient = $this->patient();
        $consultation = $this->charge($patient, 100.0, 'Consulta');
        $restoration = $this->charge($patient, 150.0, 'Restauración');

        $this->postJson('/api/transactions', [
            'patient_id' => $patient->id,
            'payment_method_id' => $this->method->id,
            'type' => 'payment',
            'amount' => 100.0,
            'description' => 'Cobro que no alcanza',
            'allocations' => [
                ['patient_charge_id' => $consultation->id, 'amount' => 60.0],
                ['patient_charge_id' => $restoration->id, 'amount' => 60.0],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('allocations');

        $this->assertSame(0, Transaction::count(), 'The refused payment must not be written.');
        $this->assertDatabaseCount('patient_charge_allocations', 0);
    }

    public function test_an_existing_payment_receives_its_allocations_afterwards(): void
    {
        $patient = $this->patient();
        $charge = $this->charge($patient, 100.0, 'Consulta');
        $payment = $this->payment($patient, 100.0);

        $this->postJson("/api/transactions/{$payment->id}/allocations", [
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 100.0],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('patient_charge_allocations', [
            'transaction_id' => $payment->id,
            'patient_charge_id' => $charge->id,
            'amount' => 100.0,
        ]);

        $this->assertSame(0.0, (float) $this->statement($patient)['charge_balances'][0]['balance']);
    }

    public function test_a_charge_cannot_receive_more_than_its_amount(): void
    {
        $patient = $this->patient();
        $charge = $this->charge($patient, 100.0, 'Consulta');
        $first = $this->payment($patient, 80.0);
        $second = $this->payment($patient, 50.0);

        $this->postJson("/api/transactions/{$first->id}/allocations", [
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 80.0],
            ],
        ])->assertCreated();

        $this->postJson("/api/transactions/{$second->id}/allocations", [
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 30.0],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('allocations.0.amount');

        $this->assertDatabaseCount('patient_charge_allocations', 1);
        $this->assertSame(80.0, (float) $this->statement($patient)['charge_balances'][0]['allocated']);
    }

    public function test_a_payment_cannot_be_imputed_twice_to_the_same_charge(): void
    {
        $patient = $this->patient();
        $charge = $this->charge($patient, 100.0, 'Consulta');
        $payment = $this->payment($patient, 100.0);

        $this->postJson("/api/transactions/{$payment->id}/allocations", [
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 60.0],
            ],
        ])->assertCreated();

        $this->postJson("/api/transactions/{$payment->id}/allocations", [
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 40.0],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('allocations.0.patient_charge_id');

        $this->assertDatabaseCount('patient_charge_allocations', 1);
        $this->assertSame(60.0, (float) $this->statement($patient)['charge_balances'][0]['allocated']);
    }

    public function test_a_charge_of_another_patient_cannot_be_imputed(): void
    {
        $patient = $this->patient('45678912');
        $other = $this->patient('78945612');
        $charge = $this->charge($other, 100.0, 'Consulta de otro paciente');
        $payment = $this->payment($patient, 100.0);

        $this->postJson("/api/transactions/{$payment->id}/allocations", [
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 100.0],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('allocations.0.patient_charge_id');

        $this->assertDatabaseCount('patient_charge_allocations', 0);
    }

    public function test_only_a_payment_receives_allocations(): void
    {
        $patient = $this->patient();
        $charge = $this->charge($patient, 100.0, 'Consulta');
        $writeOff = app(TransactionService::class)->createTransaction([
            'patient_id' => $patient->id,
            'type' => 'write_off',
            'amount' => 50.0,
            'description' => 'Condonación de prueba',
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Autorizado para la prueba',
        ]);

        $this->postJson("/api/transactions/{$writeOff->id}/allocations", [
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 50.0],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('transaction');

        $this->postJson('/api/transactions', [
            'patient_id' => $patient->id,
            'type' => 'write_off',
            'amount' => 50.0,
            'description' => 'Condonación con imputación',
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Autorizado para la prueba',
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 50.0],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('allocations');

        $this->assertDatabaseCount('patient_charge_allocations', 0);
    }

    public function test_a_voided_payment_cannot_be_imputed(): void
    {
        $patient = $this->patient();
        $charge = $this->charge($patient, 100.0, 'Consulta');
        $payment = $this->payment($patient, 100.0);

        app(TransactionService::class)->voidTransaction($payment->id, 'Cobro duplicado');

        $this->postJson("/api/transactions/{$payment->id}/allocations", [
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 100.0],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('transaction');

        $this->assertDatabaseCount('patient_charge_allocations', 0);
    }

    public function test_the_imputation_does_not_change_the_statement_total(): void
    {
        $patient = $this->patient();
        $charge = $this->charge($patient, 300.0, 'Consulta');
        $payment = $this->payment($patient, 100.0);

        $before = $this->statement($patient);

        $this->postJson("/api/transactions/{$payment->id}/allocations", [
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 100.0],
            ],
        ])->assertCreated();

        $after = $this->statement($patient);

        $this->assertSame((float) $before['closing_balance'], (float) $after['closing_balance']);
        $this->assertSame((float) $before['summary']['balance'], (float) $after['summary']['balance']);
        $this->assertSame((float) $before['summary']['payments'], (float) $after['summary']['payments']);

        $this->assertSame(300.0, (float) $after['charge_balances'][0]['amount']);
        $this->assertSame(100.0, (float) $after['charge_balances'][0]['allocated']);
        $this->assertSame(200.0, (float) $after['charge_balances'][0]['balance']);
        $this->assertSame(0.0, (float) $after['unallocated_payments']);
    }

    public function test_previous_charges_and_payments_read_as_unallocated(): void
    {
        $patient = $this->patient();
        $this->charge($patient, 300.0, 'Consulta histórica');
        $this->payment($patient, 100.0);

        $statement = $this->statement($patient);

        $this->assertSame(0.0, (float) $statement['charge_balances'][0]['allocated']);
        $this->assertSame(300.0, (float) $statement['charge_balances'][0]['balance']);
        $this->assertSame(100.0, (float) $statement['unallocated_payments']);
        $this->assertSame(200.0, (float) $statement['closing_balance']);
    }

    public function test_voiding_a_payment_stops_it_from_covering_its_charges(): void
    {
        $patient = $this->patient();
        $charge = $this->charge($patient, 300.0, 'Consulta');
        $payment = $this->payment($patient, 100.0);

        $this->postJson("/api/transactions/{$payment->id}/allocations", [
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 100.0],
            ],
        ])->assertCreated();

        app(TransactionService::class)->voidTransaction($payment->id, 'Cobro duplicado');

        $statement = $this->statement($patient);

        $this->assertSame(0.0, (float) $statement['charge_balances'][0]['allocated']);
        $this->assertSame(300.0, (float) $statement['charge_balances'][0]['balance']);
        $this->assertSame(0.0, (float) $statement['unallocated_payments']);
        $this->assertSame(300.0, (float) $statement['closing_balance']);
    }

    public function test_a_refund_releases_the_coverage_of_its_payment_proportionally(): void
    {
        $patient = $this->patient();
        $charge = $this->charge($patient, 300.0, 'Consulta');
        $payment = $this->payment($patient, 200.0);

        $this->postJson("/api/transactions/{$payment->id}/allocations", [
            'allocations' => [
                ['patient_charge_id' => $charge->id, 'amount' => 200.0],
            ],
        ])->assertCreated();

        app(TransactionService::class)->createTransaction([
            'patient_id' => $patient->id,
            'payment_method_id' => $this->method->id,
            'type' => 'refund',
            'parent_transaction_id' => $payment->id,
            'amount' => 100.0,
            'description' => 'Devolución parcial',
            'authorized_by' => $this->admin->id,
            'authorization_reason' => 'Paciente no recibió el tratamiento',
        ]);

        $statement = $this->statement($patient);

        $this->assertSame(100.0, (float) $statement['charge_balances'][0]['allocated']);
        $this->assertSame(200.0, (float) $statement['charge_balances'][0]['balance']);
        $this->assertSame(0.0, (float) $statement['unallocated_payments']);
        $this->assertSame(200.0, (float) $statement['closing_balance']);
    }

    public function test_a_clinical_role_cannot_impute_a_payment(): void
    {
        $patient = $this->patient();
        $charge = $this->charge($patient, 100.0, 'Consulta');
        $payment = $this->payment($patient, 100.0);

        $clinician = User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);

        $this->actingAs($clinician, 'sanctum')
            ->postJson("/api/transactions/{$payment->id}/allocations", [
                'allocations' => [
                    ['patient_charge_id' => $charge->id, 'amount' => 100.0],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('patient_charge_allocations', 0);
    }

    public function test_the_charge_balance_is_the_one_the_service_publishes(): void
    {
        $patient = $this->patient();
        $charge = $this->charge($patient, 120.0, 'Consulta');
        $payment = $this->payment($patient, 120.0);

        app(\App\Services\PatientChargeAllocationService::class)->allocate($payment, [
            ['patient_charge_id' => $charge->id, 'amount' => 120.0],
        ]);

        $statement = app(PatientStatementService::class)->forPatient($patient);

        $this->assertSame('CHG-'.str_pad((string) $charge->id, 6, '0', STR_PAD_LEFT), $statement['charge_balances'][0]['reference']);
        $this->assertSame(0.0, (float) $statement['charge_balances'][0]['balance']);
    }
}
