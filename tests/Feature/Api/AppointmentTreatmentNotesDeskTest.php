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
        foreach (self::RECORD_READERS as $role) {
            $reader = $this->staff($role);
            // T1: la agenda del rol clínico es la suya. Administración lee
            // toda la sede, así que su cita puede ser de otro profesional.
            $appointment = $this->appointmentFor(
                $role === 'administrador' ? $this->staff('odontologo') : $reader
            );

            $index = $this->actingAs($reader, 'sanctum')->getJson('/api/appointments')->assertOk();
            $rows = collect($index->json('data'))->keyBy('id');

            $this->assertArrayHasKey(
                $appointment->id,
                $rows,
                "{$role} must find the appointment in the agenda."
            );
            $this->assertSame(
                self::CONDUCT,
                $rows[$appointment->id]['treatment_notes'],
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
        $foreign = $this->appointmentFor($this->staff('odontologo'));

        // Recepción lee toda la agenda de su sede.
        $reception = $this->staff('recepcionista');

        $index = $this->actingAs($reception, 'sanctum')->getJson('/api/appointments')->assertOk();
        $row = collect($index->json('data'))->firstWhere('id', $foreign->id);

        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('treatment_notes', $row, 'recepcionista must not receive the conduct.');
        $this->assertSame(self::AGENDA_NOTE, $row['notes']);

        $show = $this->actingAs($reception, 'sanctum')
            ->getJson("/api/appointments/{$foreign->id}")
            ->assertOk();

        $this->assertArrayNotHasKey('treatment_notes', $show->json('data'));
        $show->assertJsonMissingPath('data.treatment_notes');
        $this->assertSame(self::AGENDA_NOTE, $show->json('data.notes'));

        // El laboratorio lee su propia agenda (T1): la conducta no viaja a su
        // escritorio aunque la cita sea suya, porque no lee la historia.
        $technician = $this->staff('tecnico_dental');
        $own = $this->appointmentFor($technician, 'confirmed', '10:00');

        $labIndex = $this->actingAs($technician, 'sanctum')->getJson('/api/appointments')->assertOk();
        $labRow = collect($labIndex->json('data'))->firstWhere('id', $own->id);

        $this->assertIsArray($labRow);
        $this->assertArrayNotHasKey('treatment_notes', $labRow, 'tecnico_dental must not receive the conduct.');
        $this->assertSame(self::AGENDA_NOTE, $labRow['notes']);

        $labShow = $this->actingAs($technician, 'sanctum')
            ->getJson("/api/appointments/{$own->id}")
            ->assertOk();

        $this->assertArrayNotHasKey('treatment_notes', $labShow->json('data'));
        $this->assertSame(self::AGENDA_NOTE, $labShow->json('data.notes'));
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

    public function test_the_dashboard_lists_are_retired_and_the_agenda_keeps_the_boundary(): void
    {
        // U2 - the dashboard stopped publishing appointment lists, for the
        // clinical desks and for finance alike.
        foreach (['administrador', 'finanzas'] as $role) {
            $desk = $this->staff($role);

            $this->actingAs($desk, 'sanctum')
                ->getJson('/api/dashboard/appointments-today')
                ->assertNotFound();

            $this->actingAs($desk, 'sanctum')
                ->getJson('/api/dashboard/upcoming')
                ->assertNotFound();
        }

        // The agenda is the surface that still publishes the appointment, and
        // it keeps the same boundary: the conduct only reaches the desk that
        // reads the clinical record.
        $appointment = $this->appointmentFor($this->staff('odontologo'));

        $receptionIndex = $this->actingAs($this->staff('recepcionista'), 'sanctum')
            ->getJson('/api/appointments')
            ->assertOk();
        $receptionIndex->assertJsonMissingPath('data.0.treatment_notes');
        $this->assertSame(self::AGENDA_NOTE, $receptionIndex->json('data.0.notes'));

        $clinical = $this->staff('odontologo');
        $this->appointmentFor($clinical, 'confirmed', '08:00');
        $clinicalIndex = $this->actingAs($clinical, 'sanctum')
            ->getJson('/api/appointments')
            ->assertOk();
        $this->assertSame(self::CONDUCT, $clinicalIndex->json('data.0.treatment_notes'));

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
