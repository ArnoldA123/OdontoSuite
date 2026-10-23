<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F5 (#314, D2) — the conduct the closure copies is clinical content.
 *
 * `appointments.treatment_notes` is the `plan` of the evolution, not an
 * operational summary of the schedule. It used to reach every desk that reads
 * the agenda —reception and the laboratory included— and now the projection
 * publishes it only to the desk that reads the clinical record
 * (`manage.medical_record`). The historical rows keep their value: what is
 * filtered is the surface, not the ledger.
 */
class AppointmentTreatmentNotesDeskTest extends TestCase
{
    use RefreshDatabase;

    private const CONDUCT = 'Obturación con resina compuesta.';

    private const AGENDA_NOTE = 'Llamar antes de la cita.';

    /** @var list<string> */
    private const RECORD_READERS = ['administrador', 'odontologo', 'implantologo', 'asistente'];

    /** @var list<string> */
    private const NON_CLINICAL_DESKS = ['recepcionista', 'tecnico_dental'];

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function appointmentFor(User $professional, string $status = 'confirmed', string $time = '09:00'): Appointment
    {
        return Appointment::factory()->create([
            'user_id' => $professional->id,
            'status' => $status,
            'scheduled_at' => now()->startOfDay()->setTimeFromTimeString($time),
            'notes' => self::AGENDA_NOTE,
            'treatment_notes' => self::CONDUCT,
        ]);
    }

    public function test_the_clinical_desk_reads_the_conduct_on_the_agenda_and_the_detail(): void
    {
        $appointment = $this->appointmentFor($this->staff('odontologo'));

        foreach (self::RECORD_READERS as $role) {
            $reader = $this->staff($role);

            $index = $this->actingAs($reader, 'sanctum')->getJson('/api/appointments')->assertOk();
            $this->assertSame(
                self::CONDUCT,
                $index->json('data.0.treatment_notes'),
                "{$role} reads the clinical record, so the agenda must ship the conduct."
            );

            $show = $this->actingAs($reader, 'sanctum')
                ->getJson("/api/appointments/{$appointment->id}")
                ->assertOk();
            $this->assertSame(
                self::CONDUCT,
                $show->json('data.treatment_notes'),
                "{$role} reads the clinical record, so the detail must ship the conduct."
            );
        }
    }

    public function test_reception_and_the_laboratory_receive_the_appointment_without_the_conduct(): void
    {
        $appointment = $this->appointmentFor($this->staff('odontologo'));

        foreach (self::NON_CLINICAL_DESKS as $role) {
            $desk = $this->staff($role);

            $index = $this->actingAs($desk, 'sanctum')->getJson('/api/appointments')->assertOk();
            $row = $index->json('data.0');

            $this->assertIsArray($row);
            $this->assertArrayNotHasKey('treatment_notes', $row, "{$role} must not receive the conduct.");
            $index->assertJsonMissingPath('data.0.treatment_notes');
            // The agenda annotation is not clinical content and stays.
            $this->assertSame(self::AGENDA_NOTE, $row['notes']);

            $show = $this->actingAs($desk, 'sanctum')
                ->getJson("/api/appointments/{$appointment->id}")
                ->assertOk();

            $this->assertArrayNotHasKey('treatment_notes', $show->json('data'));
            $show->assertJsonMissingPath('data.treatment_notes');
            $this->assertSame(self::AGENDA_NOTE, $show->json('data.notes'));
        }
    }

    public function test_finance_still_cannot_read_the_agenda_at_all(): void
    {
        $appointment = $this->appointmentFor($this->staff('odontologo'));
        $finance = $this->staff('finanzas');

        $this->actingAs($finance, 'sanctum')
            ->getJson('/api/appointments')
            ->assertForbidden();

        $this->actingAs($finance, 'sanctum')
            ->getJson("/api/appointments/{$appointment->id}")
            ->assertForbidden();
    }

    public function test_my_day_follows_the_same_boundary(): void
    {
        $professional = $this->staff('odontologo');
        $this->appointmentFor($professional, 'arrived');

        $clinicalDay = $this->actingAs($professional, 'sanctum')->getJson('/api/my-day')->assertOk();
        $this->assertSame(self::CONDUCT, $clinicalDay->json('data.waiting.0.treatment_notes'));

        // The laboratory desk reads its own day, and its queue carries no
        // clinical narrative.
        $technician = $this->staff('tecnico_dental');
        $this->appointmentFor($technician, 'arrived', '10:00');

        $labDay = $this->actingAs($technician, 'sanctum')->getJson('/api/my-day')->assertOk();
        $labDay->assertJsonMissingPath('data.waiting.0.treatment_notes');
        $this->assertArrayNotHasKey('treatment_notes', $labDay->json('data.waiting.0'));
        $this->assertSame(self::AGENDA_NOTE, $labDay->json('data.waiting.0.notes'));
    }

    public function test_the_dashboard_and_the_consultation_context_follow_the_same_boundary(): void
    {
        $appointment = $this->appointmentFor($this->staff('odontologo'));

        $adminToday = $this->actingAs($this->staff('administrador'), 'sanctum')
            ->getJson('/api/dashboard/appointments-today')
            ->assertOk();
        $this->assertSame(self::CONDUCT, $adminToday->json('data.0.treatment_notes'));

        // The dashboard is the one agenda surface finance reaches, and it
        // carries no conduct either.
        foreach (['recepcionista', 'tecnico_dental', 'finanzas'] as $role) {
            $today = $this->actingAs($this->staff($role), 'sanctum')
                ->getJson('/api/dashboard/appointments-today')
                ->assertOk();

            $this->assertArrayNotHasKey('treatment_notes', $today->json('data.0'));
            $today->assertJsonMissingPath('data.0.treatment_notes');
        }

        // `/consultation-context` is opened by reception too: the wizard gets
        // the appointment without the conduct.
        $receptionContext = $this->actingAs($this->staff('recepcionista'), 'sanctum')
            ->getJson("/api/appointments/{$appointment->id}/consultation-context")
            ->assertOk();
        $receptionContext->assertJsonMissingPath('data.appointment.treatment_notes');

        $clinicalContext = $this->actingAs($this->staff('odontologo'), 'sanctum')
            ->getJson("/api/appointments/{$appointment->id}/consultation-context")
            ->assertOk();
        $this->assertSame(self::CONDUCT, $clinicalContext->json('data.appointment.treatment_notes'));
    }
}
