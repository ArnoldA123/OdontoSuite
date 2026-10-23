<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\PatientCharge;
use App\Services\PatientChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #287 (T2, S3/S4) — `charges:backfill`.
 *
 * Closed acts already documented before the ledger existed receive the charge
 * they are missing. The command is idempotent and its dry run writes nothing.
 */
class BackfillPatientChargesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function closedEncounter(Patient $patient, array $attributes = []): ClinicalEncounter
    {
        return ClinicalEncounter::create(array_merge([
            'patient_id' => $patient->id,
            'mode' => ClinicalEncounter::MODE_EXECUTION,
            'status' => ClinicalEncounter::STATUS_CLOSED,
            'opened_at' => now()->subHours(3),
            'closed_at' => now()->subHour(),
            'final_amount' => 120,
        ], $attributes));
    }

    public function test_it_creates_the_charge_of_every_closed_encounter_with_an_amount(): void
    {
        $patient = Patient::factory()->create();
        $first = $this->closedEncounter($patient);
        $second = $this->closedEncounter($patient, ['final_amount' => 80]);
        $withoutAmount = $this->closedEncounter($patient, ['final_amount' => 0]);
        $open = $this->closedEncounter($patient, [
            'status' => ClinicalEncounter::STATUS_OPEN,
            'closed_at' => null,
        ]);

        $this->artisan('charges:backfill')
            ->expectsOutput('Atenciones cerradas revisadas: 2')
            ->expectsOutput('Cargos creados: 2')
            ->assertSuccessful();

        $firstCharge = PatientCharge::where('clinical_encounter_id', $first->id)->sole();
        $this->assertSame(PatientCharge::SOURCE_ENCOUNTER, $firstCharge->source);
        $this->assertSame('120.00', $firstCharge->amount);
        $this->assertSame($patient->id, $firstCharge->patient_id);
        $this->assertSame(
            $first->closed_at?->toDateTimeString(),
            $firstCharge->charged_at?->toDateTimeString(),
        );

        $this->assertSame('80.00', PatientCharge::where('clinical_encounter_id', $second->id)->sole()->amount);
        $this->assertSame(0, PatientCharge::where('clinical_encounter_id', $withoutAmount->id)->count());
        $this->assertSame(0, PatientCharge::where('clinical_encounter_id', $open->id)->count());
    }

    public function test_a_second_run_does_not_duplicate_charges(): void
    {
        $patient = Patient::factory()->create();
        $encounter = $this->closedEncounter($patient);

        $this->artisan('charges:backfill')->assertSuccessful();
        $chargeId = PatientCharge::where('clinical_encounter_id', $encounter->id)->sole()->id;

        $this->artisan('charges:backfill')
            ->expectsOutput('Atenciones cerradas revisadas: 1')
            ->expectsOutput('Cargos creados: 0')
            ->assertSuccessful();

        $this->assertSame(1, PatientCharge::where('clinical_encounter_id', $encounter->id)->count());
        $this->assertSame($chargeId, PatientCharge::where('clinical_encounter_id', $encounter->id)->sole()->id);
    }

    public function test_it_leaves_the_charge_the_closure_already_wrote(): void
    {
        $patient = Patient::factory()->create();
        $encounter = $this->closedEncounter($patient);

        $written = app(PatientChargeService::class)->chargeForEncounter($encounter);

        $this->artisan('charges:backfill')
            ->expectsOutput('Cargos creados: 0')
            ->assertSuccessful();

        $this->assertSame(1, PatientCharge::count());
        $this->assertSame($written->id, PatientCharge::sole()->id);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $patient = Patient::factory()->create();
        $this->closedEncounter($patient);
        $this->closedEncounter($patient, ['final_amount' => 80]);

        $this->artisan('charges:backfill', ['--dry-run' => true])
            ->expectsOutput('Atenciones cerradas revisadas: 2')
            ->expectsOutput('Cargos creados: 2')
            ->assertSuccessful();

        $this->assertSame(0, PatientCharge::count());
    }
}
