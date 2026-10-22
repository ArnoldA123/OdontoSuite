<?php

namespace Tests\Feature\Console;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\OdontogramRecord;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2 (#282) — `encounters:backfill` (S9).
 *
 * Completed appointments written before the encounter existed receive exactly
 * one closed encounter, and their clinical rows are linked to it. The command
 * is idempotent: a second run must report zero creations and zero links while
 * the rows stay the same.
 */
class BackfillClinicalEncountersCommandTest extends TestCase
{
    use RefreshDatabase;

    private function historicalAppointment(Patient $patient, array $attributes = []): Appointment
    {
        return Appointment::factory()->create(array_merge([
            'patient_id' => $patient->id,
            'status' => 'completed',
            'checked_in_at' => now()->subHours(3),
            'completed_at' => now()->subHour(),
            'final_amount' => 120,
            'consultation_mode' => 'execution',
        ], $attributes));
    }

    public function test_it_creates_one_closed_encounter_for_each_historical_completed_appointment(): void
    {
        $patient = Patient::factory()->create();
        $first = $this->historicalAppointment($patient);
        $second = $this->historicalAppointment($patient);
        $notCompleted = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'scheduled',
        ]);

        $this->artisan('encounters:backfill')
            ->expectsOutput('Atenciones creadas: 2')
            ->assertSuccessful();

        $encounter = ClinicalEncounter::where('appointment_id', $first->id)->sole();

        $this->assertTrue($encounter->isClosed());
        $this->assertSame($patient->id, $encounter->patient_id);
        $this->assertSame($first->user_id, $encounter->user_id);
        $this->assertSame('execution', $encounter->mode);
        $this->assertSame('120.00', $encounter->final_amount);
        $this->assertSame($first->checked_in_at?->toDateTimeString(), $encounter->opened_at?->toDateTimeString());
        $this->assertSame($first->completed_at?->toDateTimeString(), $encounter->closed_at?->toDateTimeString());
        $this->assertSame($first->treatment_plan_id, $encounter->treatment_plan_id);

        $this->assertSame(1, ClinicalEncounter::where('appointment_id', $second->id)->count());
        $this->assertSame(0, ClinicalEncounter::where('appointment_id', $notCompleted->id)->count());
    }

    public function test_a_second_run_does_not_duplicate_encounters(): void
    {
        $patient = Patient::factory()->create();
        $appointment = $this->historicalAppointment($patient);

        $this->artisan('encounters:backfill')->assertSuccessful();
        $encounterId = ClinicalEncounter::where('appointment_id', $appointment->id)->sole()->id;

        $this->artisan('encounters:backfill')
            ->expectsOutput('Atenciones creadas: 0')
            ->expectsOutput('Filas clínicas enlazadas: 0')
            ->assertSuccessful();

        $this->assertSame(1, ClinicalEncounter::where('appointment_id', $appointment->id)->count());
        $this->assertSame($encounterId, ClinicalEncounter::where('appointment_id', $appointment->id)->sole()->id);
    }

    public function test_it_links_the_clinical_rows_that_still_have_no_encounter(): void
    {
        $patient = Patient::factory()->create();
        $appointment = $this->historicalAppointment($patient);
        $record = MedicalRecord::factory()->create(['patient_id' => $patient->id]);

        $evolution = ClinicalEvolution::factory()->forRecord($record)->create([
            'appointment_id' => $appointment->id,
        ]);
        $finding = OdontogramRecord::create([
            'odontogram_id' => \App\Models\Odontogram::firstOrCreate(
                ['patient_id' => $patient->id, 'is_active' => true],
                ['created_by' => $appointment->user_id],
            )->id,
            'dental_piece_id' => \App\Models\DentalPiece::create([
                'fdi_number' => '11',
                'name' => 'Incisivo central superior derecho',
                'type' => 'incisor',
                'quadrant' => 'superior_derecho',
                'position' => 1,
            ])->id,
            'appointment_id' => $appointment->id,
            'condition_code' => 'caries',
            'condition_name' => 'Caries',
            'created_by' => $appointment->user_id,
        ]);

        $this->artisan('encounters:backfill')
            ->expectsOutput('Filas clínicas enlazadas: 2')
            ->assertSuccessful();

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();

        $this->assertSame($encounter->id, $evolution->fresh()->clinical_encounter_id);
        $this->assertSame($encounter->id, $finding->fresh()->clinical_encounter_id);
        $this->assertSame($record->id, $encounter->medical_record_id);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $patient = Patient::factory()->create();
        $appointment = $this->historicalAppointment($patient);
        $record = MedicalRecord::factory()->create(['patient_id' => $patient->id]);
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create([
            'appointment_id' => $appointment->id,
        ]);

        $this->artisan('encounters:backfill', ['--dry-run' => true])
            ->expectsOutput('Atenciones creadas: 1')
            ->expectsOutput('Filas clínicas enlazadas: 1')
            ->assertSuccessful();

        $this->assertSame(0, ClinicalEncounter::count());
        $this->assertNull($evolution->fresh()->clinical_encounter_id);
    }

    public function test_it_derives_the_close_from_the_appointment_when_completed_at_is_missing(): void
    {
        $patient = Patient::factory()->create();
        $appointment = $this->historicalAppointment($patient, [
            'checked_in_at' => null,
            'completed_at' => null,
        ]);
        $appointment->forceFill(['updated_by' => $appointment->user_id])->save();

        $this->artisan('encounters:backfill')->assertSuccessful();

        $encounter = ClinicalEncounter::where('appointment_id', $appointment->id)->sole();

        $this->assertSame($appointment->scheduled_at?->toDateTimeString(), $encounter->opened_at?->toDateTimeString());
        $this->assertSame($appointment->ends_at?->toDateTimeString(), $encounter->closed_at?->toDateTimeString());
        $this->assertSame($appointment->user_id, $encounter->closed_by);
    }

    public function test_it_does_not_move_the_timestamp_of_the_rows_it_links(): void
    {
        $patient = Patient::factory()->create();
        $appointment = $this->historicalAppointment($patient);
        $record = MedicalRecord::factory()->create(['patient_id' => $patient->id]);
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create([
            'appointment_id' => $appointment->id,
        ]);
        $evolution->forceFill(['updated_at' => now()->subYear()])->saveQuietly();
        $before = $evolution->fresh()->updated_at;

        $this->artisan('encounters:backfill')->assertSuccessful();

        $this->assertSame($before?->toDateTimeString(), $evolution->fresh()->updated_at?->toDateTimeString());
    }
}
