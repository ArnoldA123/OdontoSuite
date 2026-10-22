<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #290 S7 — the consolidated report with drill-down by sede and by professional,
 * and its acceptance criterion: «el reporte consolidado cuadra con la suma de
 * las sedes».
 *
 * Money is net (completed payments minus completed refunds) and every movement
 * lands in exactly one row of each cut, so both cuts add up to the summary. The
 * population includes money that does not resolve to a sede or to an appointment
 * and those rows are named, never silently dropped.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class ConsolidatedReportTest extends TestCase
{
    use RefreshDatabase;

    private function user(?Branch $branch, string $role = 'finanzas'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function cashSession(Branch $branch): CashRegisterSession
    {
        return CashRegisterSession::factory()->create([
            'user_id' => $this->user($branch, 'recepcionista')->id,
            'branch_id' => $branch->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    private function appointment(Branch $branch, User $professional): Appointment
    {
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        return Appointment::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
            'branch_id' => $branch->id,
            'scheduled_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function money(array $attributes): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'created_by' => User::factory()->create()->id,
            'status' => 'completed',
            'created_at' => now(),
        ], $attributes));
    }

    /**
     * @return array{branches: array<string, Branch>, professionals: array<string, User>}
     */
    private function scenario(): array
    {
        $a = Branch::factory()->create(['name' => 'Sede Lima']);
        $b = Branch::factory()->create(['name' => 'Sede Trujillo']);

        $proA = $this->user($a, 'odontologo');
        $proB = $this->user($b, 'odontologo');

        $sessionA = $this->cashSession($a);
        $sessionB = $this->cashSession($b);

        $appointmentA = $this->appointment($a, $proA);
        $appointmentB = $this->appointment($b, $proB);

        // Sede Lima: 100 collected, 20 refunded. Sede Trujillo: 50 collected.
        $this->money(['type' => 'payment', 'amount' => 100.0, 'cash_register_session_id' => $sessionA->id, 'appointment_id' => $appointmentA->id]);
        $this->money(['type' => 'refund', 'amount' => 20.0, 'cash_register_session_id' => $sessionA->id, 'appointment_id' => $appointmentA->id]);
        $this->money(['type' => 'payment', 'amount' => 50.0, 'cash_register_session_id' => $sessionB->id, 'appointment_id' => $appointmentB->id]);

        // Money of a drawer without an appointment (still a sede) and money with
        // no drawer at all (no sede): both must survive the drill-down.
        $this->money(['type' => 'payment', 'amount' => 10.0, 'cash_register_session_id' => $sessionA->id, 'appointment_id' => null]);
        $this->money(['type' => 'payment', 'amount' => 30.0, 'cash_register_session_id' => null, 'appointment_id' => null]);

        return ['branches' => ['a' => $a, 'b' => $b], 'professionals' => ['a' => $proA, 'b' => $proB]];
    }

    public function test_both_cuts_add_up_to_the_summary(): void
    {
        $this->scenario();

        $response = $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->getJson('/api/reports/consolidated');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertSame(170.0, (float) $data['summary']['net']);
        $this->assertSame(20.0, (float) $data['summary']['refunds']);

        foreach (['by_branch', 'by_professional'] as $cut) {
            $this->assertEqualsWithDelta(
                (float) $data['summary']['net'],
                array_sum(array_map(static fn (array $row) => (float) $row['net'], $data[$cut])),
                0.001,
                "The [{$cut}] cut does not add up to the summary net.",
            );
            $this->assertEqualsWithDelta(
                (float) $data['summary']['refunds'],
                array_sum(array_map(static fn (array $row) => (float) $row['refunds'], $data[$cut])),
                0.001,
                "The [{$cut}] cut does not add up to the summary refunds.",
            );
        }
    }

    public function test_the_sede_breakdown_names_every_row(): void
    {
        $scenario = $this->scenario();

        $response = $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->getJson('/api/reports/consolidated');

        $byBranch = collect($response->json('data.by_branch'))->keyBy('branch');

        $this->assertSame(90.0, (float) $byBranch['Sede Lima']['net']);
        $this->assertSame(50.0, (float) $byBranch['Sede Trujillo']['net']);
        $this->assertSame(30.0, (float) $byBranch['Sin sede']['net']);
        $this->assertSame($scenario['branches']['a']->id, $byBranch['Sede Lima']['branch_id']);
    }

    public function test_the_professional_breakdown_attributes_money_without_an_appointment(): void
    {
        $scenario = $this->scenario();

        $response = $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->getJson('/api/reports/consolidated');

        $byProfessional = collect($response->json('data.by_professional'))->keyBy('professional');

        $this->assertSame(80.0, (float) $byProfessional[$scenario['professionals']['a']->name]['net']);
        $this->assertSame(50.0, (float) $byProfessional[$scenario['professionals']['b']->name]['net']);
        $this->assertSame(40.0, (float) $byProfessional['Sin cita asociada']['net']);
    }

    public function test_a_restricted_finance_user_is_answered_only_their_sede(): void
    {
        $scenario = $this->scenario();

        $response = $this->actingAs($this->user($scenario['branches']['a'], 'finanzas'), 'sanctum')
            ->getJson('/api/reports/consolidated');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertSame(90.0, (float) $data['summary']['net']);
        $this->assertSame(
            [$scenario['branches']['a']->id],
            collect($data['by_branch'])->pluck('branch_id')->filter()->values()->all(),
        );
    }

    public function test_naming_another_sede_in_the_consolidated_report_is_forbidden(): void
    {
        $scenario = $this->scenario();

        $this->actingAs($this->user($scenario['branches']['a'], 'finanzas'), 'sanctum')
            ->getJson('/api/reports/consolidated?branch_id='.$scenario['branches']['b']->id)
            ->assertForbidden();
    }

    public function test_the_report_respects_the_period(): void
    {
        $this->scenario();

        $response = $this->actingAs($this->user(null, 'administrador'), 'sanctum')
            ->getJson('/api/reports/consolidated?start_date='
                .now()->addDay()->toDateString().'&end_date='.now()->addDays(3)->toDateString());

        $response->assertOk();
        $this->assertSame(0.0, (float) $response->json('data.summary.net'));
        $this->assertSame([], $response->json('data.by_branch'));
    }
}
