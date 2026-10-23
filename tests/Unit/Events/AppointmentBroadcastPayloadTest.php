<?php

namespace Tests\Unit\Events;

use App\Events\AppointmentCheckedIn;
use App\Events\AppointmentCompleted;
use App\Events\AppointmentCreated;
use App\Events\AppointmentUpdated;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * F5 (#314, D2) — no appointment event carries the clinical conduct.
 *
 * The same bytes travel on `appointments`, `appointment.{id}` and
 * `dashboard-updates`, and the last channel authorizes the seven roles, so
 * `treatment_notes` never reaches the wire. The rest of the payload —the
 * relations the consumers read and each event's own keys— stays.
 *
 * The suite runs without a database: the appointment arrives with its
 * relations already set and the only ledger read of the four payloads
 * (`AppointmentCompleted`'s payment flag) is answered in memory.
 */
class AppointmentBroadcastPayloadTest extends TestCase
{
    private const CONDUCT = 'Obturación con resina compuesta.';

    private function appointment(): Appointment
    {
        $appointment = new class extends Appointment
        {
            public bool $paymentsOnTheLedger = false;

            public function getHasPaymentAttribute()
            {
                return $this->paymentsOnTheLedger;
            }
        };

        $appointment->forceFill([
            'id' => 42,
            'patient_id' => 7,
            'user_id' => 8,
            'dental_chair_id' => 9,
            'appointment_type_id' => 10,
            'treatment_plan_id' => 11,
            'scheduled_at' => now(),
            'duration_minutes' => 60,
            'status' => 'completed',
            'notes' => 'Llamar antes de la cita.',
            'treatment_notes' => self::CONDUCT,
            'final_amount' => 180,
            'consultation_mode' => 'execution',
            'checked_in_at' => now(),
        ]);

        $patient = new Patient;
        $patient->forceFill(['id' => 7, 'first_name' => 'Ana', 'last_name' => 'Torres']);
        $appointment->setRelation('patient', $patient);

        $professional = new User;
        $professional->forceFill(['id' => 8, 'name' => 'Dra. Rojas', 'specialty' => 'general']);
        $appointment->setRelation('user', $professional);

        $type = new AppointmentType;
        $type->forceFill(['id' => 10, 'name' => 'Restauración']);
        $appointment->setRelation('appointmentType', $type);

        $chair = new DentalChair;
        $chair->forceFill(['id' => 9, 'name' => 'Sillón 1', 'code' => 'S1']);
        $appointment->setRelation('dentalChair', $chair);

        $plan = new TreatmentPlan;
        $plan->forceFill(['id' => 11, 'plan_number' => 'PLAN-0011', 'title' => 'Restauración 16']);
        $appointment->setRelation('treatmentPlan', $plan);

        return $appointment;
    }

    /**
     * @param array<string, mixed> $appointment
     */
    private function assertPublishedAppointment(array $appointment, string $context): void
    {
        $this->assertArrayNotHasKey(
            'treatment_notes',
            $appointment,
            "{$context} MUST NOT publish the conduct: the same payload reaches reception and the laboratory."
        );

        // Everything the agenda consumers read survives the cut.
        $this->assertSame(42, $appointment['id']);
        $this->assertSame('Llamar antes de la cita.', $appointment['notes']);
        $this->assertSame('Ana', $appointment['patient']['first_name']);
        $this->assertSame('Dra. Rojas', $appointment['user']['name']);
        $this->assertSame('Restauración', $appointment['appointment_type']['name']);
        $this->assertSame('S1', $appointment['dental_chair']['code']);
    }

    #[Test]
    public function the_created_event_keeps_the_agenda_and_drops_the_conduct(): void
    {
        $payload = (new AppointmentCreated($this->appointment()))->broadcastWith();

        $this->assertSame(['appointment'], array_keys($payload));
        $this->assertPublishedAppointment($payload['appointment'], 'AppointmentCreated');
    }

    #[Test]
    public function the_updated_event_keeps_the_agenda_and_drops_the_conduct(): void
    {
        $payload = (new AppointmentUpdated($this->appointment(), ['status' => 'confirmed']))->broadcastWith();

        $this->assertSame(['appointment'], array_keys($payload));
        $this->assertPublishedAppointment($payload['appointment'], 'AppointmentUpdated');
    }

    #[Test]
    public function the_completed_event_keeps_its_operational_keys_and_drops_the_conduct(): void
    {
        $appointment = $this->appointment();

        $payload = (new AppointmentCompleted($appointment))->broadcastWith();

        $this->assertSame(
            ['appointment', 'final_amount', 'consultation_mode', 'treatment_plan_id', 'has_payment'],
            array_keys($payload)
        );
        $this->assertPublishedAppointment($payload['appointment'], 'AppointmentCompleted');

        // The key the closure listeners read is published on the appointment
        // object as their consumers expect it.
        $this->assertArrayHasKey('treatment_plan', $payload['appointment']);
        $this->assertSame('PLAN-0011', $payload['appointment']['treatment_plan']['plan_number']);
        $this->assertSame('180.00', $payload['final_amount']);
        $this->assertSame('execution', $payload['consultation_mode']);
        $this->assertSame(11, $payload['treatment_plan_id']);
        $this->assertFalse($payload['has_payment']);
    }

    #[Test]
    public function the_checked_in_event_keeps_the_arrival_and_drops_the_conduct(): void
    {
        $appointment = $this->appointment();

        $payload = (new AppointmentCheckedIn($appointment))->broadcastWith();

        $this->assertSame(['appointment', 'checked_in_at'], array_keys($payload));
        $this->assertPublishedAppointment($payload['appointment'], 'AppointmentCheckedIn');
        $this->assertSame(
            $appointment->checked_in_at?->toIso8601String(),
            $payload['checked_in_at']
        );
    }
}
