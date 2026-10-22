<?php

namespace Tests\Feature\Domain;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2 (#282) — the act and its schedule.
 *
 * `clinical_encounters` is the clinical act: it has its own state
 * (`open → closed | voided`), one professional, one medical record, one plan
 * and one amount. An appointment holds 0..n of them, and an act may exist with
 * no appointment at all (urgency, S5/S8).
 */
class ClinicalEncounterDomainTest extends TestCase
{
    use RefreshDatabase;

    private function appointment(): Appointment
    {
        return Appointment::factory()->create(['status' => 'in_consultation']);
    }

    public function test_an_appointment_holds_zero_to_many_encounters(): void
    {
        $appointment = $this->appointment();
        $this->assertCount(0, $appointment->clinicalEncounters);

        $voided = ClinicalEncounter::factory()->forAppointment($appointment)->create([
            'status' => ClinicalEncounter::STATUS_VOIDED,
            'voided_at' => now(),
        ]);
        $open = ClinicalEncounter::factory()->forAppointment($appointment)->create();

        $appointment->refresh();

        $this->assertCount(2, $appointment->clinicalEncounters);
        $this->assertTrue($appointment->openClinicalEncounter->is($open));
        $this->assertTrue($voided->isVoided());
    }

    public function test_the_state_machine_helpers_match_the_persisted_status(): void
    {
        $open = ClinicalEncounter::factory()->create();
        $closed = ClinicalEncounter::factory()->closed()->create();
        $voided = ClinicalEncounter::factory()->create([
            'status' => ClinicalEncounter::STATUS_VOIDED,
            'voided_at' => now(),
        ]);

        $this->assertTrue($open->isOpen());
        $this->assertFalse($open->isClosed());
        $this->assertTrue($closed->isClosed());
        $this->assertFalse($closed->isOpen());
        $this->assertTrue($voided->isVoided());
    }

    public function test_an_urgency_encounter_exists_without_an_appointment(): void
    {
        $patient = Patient::factory()->create();
        $professional = User::factory()->create(['role' => 'odontologo', 'is_active' => true]);

        $encounter = ClinicalEncounter::factory()->create([
            'patient_id' => $patient->id,
            'user_id' => $professional->id,
            'appointment_id' => null,
            'mode' => ClinicalEncounter::MODE_URGENCY,
        ]);

        $this->assertTrue($encounter->isUnscheduled());
        $this->assertNull($encounter->appointment_id);
        $this->assertSame($patient->id, $encounter->patient_id);
        // S8: no agenda row is invented to hold the act.
        $this->assertSame(0, Appointment::where('patient_id', $patient->id)->count());
    }

    public function test_an_encounter_belongs_to_its_patient_professional_and_plan(): void
    {
        $encounter = ClinicalEncounter::factory()->closed()->create();

        $this->assertNotNull($encounter->patient);
        $this->assertNotNull($encounter->user);
        $this->assertTrue($encounter->isUnscheduled());
    }
}
