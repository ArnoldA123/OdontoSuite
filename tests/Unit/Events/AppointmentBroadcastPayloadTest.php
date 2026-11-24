<?php

namespace Tests\Unit\Events;

use App\Events\AppointmentCheckedIn;
use App\Events\AppointmentCompleted;
use App\Events\AppointmentCreated;
use App\Events\AppointmentDeleted;
use App\Events\AppointmentUpdated;
use App\Events\ReminderSent;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\ReminderSchedule;
use App\Models\ReminderTemplate;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * citas-privacidad (T1) — the appointment events publish the operation, not
 * the patient.
 *
 * The same bytes travel on `appointments`, `appointment.{id}` and
 * `dashboard-updates`, and the last channel authorizes all seven roles, so an
 * event used to carry the patient's name, document and phone plus the clinical
 * conduct to desks that only need to know something moved. The payload is now
 * the five operational keys —`id`, `user_id`, `status`, `scheduled_at`,
 * `updated_at`— and everything else is read from the API with the caller's own
 * permissions.
 *
 * The suite runs without a database: the appointment arrives in memory and no
 * payload reads the ledger any more.
 */
class AppointmentBroadcastPayloadTest extends TestCase
{
    private const CONDUCT = 'Obturación con resina compuesta.';

    /** @var list<string> */
    private const OPERATIONAL_KEYS = ['id', 'user_id', 'status', 'scheduled_at', 'updated_at'];

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-11-24 10:15:00');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

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
            'updated_at' => now(),
        ]);

        $patient = new Patient;
        $patient->forceFill([
            'id' => 7,
            'first_name' => 'Ana',
            'last_name' => 'Torres',
            'document_number' => '45678912',
            'phone' => '987654321',
            'email' => 'ana.torres@test.com',
        ]);
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
     * The published appointment is exactly the operational envelope: no
     * clinical conduct, no patient identity and no relation at all.
     *
     * @param array<string, mixed> $appointment
     */
    private function assertOperationalPayload(array $appointment, string $context): void
    {
        $this->assertSame(
            self::OPERATIONAL_KEYS,
            array_keys($appointment),
            "{$context} MUST publish only the operational keys."
        );

        $this->assertSame(42, $appointment['id']);
        $this->assertSame(8, $appointment['user_id']);
        $this->assertSame('completed', $appointment['status']);
        $this->assertNotNull($appointment['scheduled_at']);
        $this->assertNotNull($appointment['updated_at']);

        $this->assertArrayNotHasKey('patient', $appointment, "{$context} MUST NOT publish the patient.");
        $this->assertArrayNotHasKey('patient_id', $appointment);
        $this->assertArrayNotHasKey('treatment_notes', $appointment, "{$context} MUST NOT publish the conduct.");
        $this->assertArrayNotHasKey('user', $appointment);
        $this->assertArrayNotHasKey('notes', $appointment);

        // Nothing of the patient rides along, not even inside another key.
        foreach (['Ana', 'Torres', '45678912', '987654321', 'ana.torres@test.com', self::CONDUCT] as $privateValue) {
            $this->assertStringNotContainsString(
                $privateValue,
                json_encode($appointment, JSON_THROW_ON_ERROR),
                "{$context} leaked '{$privateValue}'."
            );
        }
    }

    #[Test]
    public function the_created_event_publishes_the_operational_envelope(): void
    {
        $payload = (new AppointmentCreated($this->appointment()))->broadcastWith();

        $this->assertSame(['appointment'], array_keys($payload));
        $this->assertOperationalPayload($payload['appointment'], 'AppointmentCreated');
    }

    #[Test]
    public function the_updated_event_publishes_the_operational_envelope(): void
    {
        $payload = (new AppointmentUpdated($this->appointment(), ['status' => 'confirmed']))->broadcastWith();

        $this->assertSame(['appointment'], array_keys($payload));
        $this->assertOperationalPayload($payload['appointment'], 'AppointmentUpdated');
    }

    #[Test]
    public function the_completed_event_publishes_the_operational_envelope(): void
    {
        $payload = (new AppointmentCompleted($this->appointment()))->broadcastWith();

        $this->assertSame(['appointment'], array_keys($payload));
        $this->assertOperationalPayload($payload['appointment'], 'AppointmentCompleted');
    }

    #[Test]
    public function the_checked_in_event_publishes_the_operational_envelope(): void
    {
        $payload = (new AppointmentCheckedIn($this->appointment()))->broadcastWith();

        $this->assertSame(['appointment'], array_keys($payload));
        $this->assertOperationalPayload($payload['appointment'], 'AppointmentCheckedIn');
    }

    #[Test]
    public function the_deleted_event_keeps_the_pair_that_removes_the_row(): void
    {
        $appointment = $this->appointment();

        $payload = (new AppointmentDeleted(42, ['user_id' => 8], $appointment))->broadcastWith();

        $this->assertSame(['appointment_id', 'user_id'], array_keys($payload));
        $this->assertSame(42, $payload['appointment_id']);
        $this->assertSame(8, $payload['user_id']);
        $this->assertArrayNotHasKey('appointment', $payload);
        $this->assertArrayNotHasKey('patient', $payload);
        $this->assertStringNotContainsString('Ana', json_encode($payload, JSON_THROW_ON_ERROR));

        // The envelope survives an event built from the removed row alone.
        $withoutModel = (new AppointmentDeleted(42, ['user_id' => 8]))->broadcastWith();
        $this->assertSame(['appointment_id' => 42, 'user_id' => 8], $withoutModel);
    }

    /**
     * The reminder travels on `reminders` and `appointments`, both authorized
     * to reception and the laboratory, and it embeds the appointment. The
     * conduct never rides along, even when the row was written before the
     * closure stopped copying it.
     */
    #[Test]
    public function the_reminder_event_drops_the_conduct_of_the_embedded_appointment(): void
    {
        $reminder = new ReminderSchedule;
        $reminder->forceFill([
            'id' => 5,
            'appointment_id' => 42,
            'hours_before' => 24,
            'scheduled_at' => now(),
            'sent_at' => now(),
            'channel' => 'email',
            'status' => 'sent',
        ]);
        $reminder->setRelation('appointment', $this->appointment());
        $reminder->setRelation('reminderTemplate', new ReminderTemplate);

        $payload = (new ReminderSent($reminder))->broadcastWith();

        $this->assertSame(['reminder'], array_keys($payload));
        $this->assertArrayNotHasKey('treatment_notes', $payload['reminder']['appointment']);
        $this->assertSame(42, $payload['reminder']['appointment']['id']);
        $this->assertSame(5, $payload['reminder']['id']);
    }
}
