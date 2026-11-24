<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentCustodyLog;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * citas-privacidad (T1) — the clinical desk reads and operates only its own
 * agenda.
 *
 * The old policy only excluded finance, so a professional could read, move,
 * confirm and remove another professional's patients. The decision is strict
 * privacy: administration and reception keep the whole agenda of their sede,
 * every other appointment role only the rows where it is the professional. The
 * rule lives once, in `AppointmentPolicy::seesWholeAgenda()`, and the index, the
 * doors and these assertions all derive from it.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class AppointmentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, ?Branch $branch = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function appointmentFor(
        User $professional,
        string $status = 'confirmed',
        string $time = '09:00',
        ?Branch $branch = null,
    ): Appointment {
        return Appointment::factory()->create([
            'user_id' => $professional->id,
            'branch_id' => $branch?->id,
            'status' => $status,
            'scheduled_at' => now()->addDay()->startOfDay()->setTimeFromTimeString($time),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPayload(User $professional): array
    {
        return [
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $professional->id,
            'dental_chair_id' => DentalChair::factory()->create()->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'scheduled_at' => now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'duration_minutes' => 30,
        ];
    }

    public function test_a_clinical_desk_lists_only_its_own_appointments(): void
    {
        $professional = $this->staff('odontologo');
        $colleague = $this->staff('implantologo');

        $mine = $this->appointmentFor($professional, 'confirmed', '09:00');
        $theirs = $this->appointmentFor($colleague, 'confirmed', '10:00');

        $response = $this->actingAs($professional, 'sanctum')
            ->getJson('/api/appointments')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($theirs->id, $ids, 'Another professional\'s patient MUST NOT be listed.');
    }

    public function test_the_wide_desks_read_the_whole_agenda_of_their_sede(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();

        $first = $this->appointmentFor($this->staff('odontologo', $branch), 'confirmed', '09:00', $branch);
        $second = $this->appointmentFor($this->staff('implantologo', $branch), 'confirmed', '10:00', $branch);
        $foreign = $this->appointmentFor($this->staff('odontologo', $otherBranch), 'confirmed', '11:00', $otherBranch);

        foreach (['administrador', 'recepcionista'] as $role) {
            $reader = $this->staff($role, $branch);

            $ids = collect(
                $this->actingAs($reader, 'sanctum')->getJson('/api/appointments')->assertOk()->json('data')
            )->pluck('id')->all();

            $this->assertContains($first->id, $ids, "{$role} answers for every appointment of the sede.");
            $this->assertContains($second->id, $ids, "{$role} answers for every appointment of the sede.");
            $this->assertNotContains(
                $foreign->id,
                $ids,
                "{$role} keeps the sede dimension: another sede's agenda stays out."
            );
        }
    }

    public function test_a_clinical_desk_cannot_open_another_professionals_appointment(): void
    {
        $professional = $this->staff('odontologo');
        $theirs = $this->appointmentFor($this->staff('implantologo'));

        $this->actingAs($professional, 'sanctum')
            ->getJson("/api/appointments/{$theirs->id}")
            ->assertForbidden();

        $mine = $this->appointmentFor($professional, 'confirmed', '10:00');

        $this->actingAs($professional, 'sanctum')
            ->getJson("/api/appointments/{$mine->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id);
    }

    public function test_a_clinical_desk_cannot_edit_another_professionals_appointment(): void
    {
        $professional = $this->staff('odontologo');
        $theirs = $this->appointmentFor($this->staff('implantologo'));

        $this->actingAs($professional, 'sanctum')
            ->putJson("/api/appointments/{$theirs->id}", ['notes' => 'Intento ajeno'])
            ->assertForbidden();

        $this->assertNull($theirs->fresh()->notes, 'A forbidden edit MUST NOT write anything.');
    }

    public function test_the_foreign_appointment_is_checked_before_the_payload_is_validated(): void
    {
        $professional = $this->staff('odontologo');
        $theirs = $this->appointmentFor($this->staff('implantologo'));

        // Sin motivo de cancelación: si la política no corriera primero, la
        // respuesta sería un 422 que insinúa el estado de la cita ajena.
        $this->actingAs($professional, 'sanctum')
            ->patchJson("/api/appointments/{$theirs->id}/status", ['status' => 'cancelled'])
            ->assertForbidden();

        $this->assertSame('confirmed', $theirs->fresh()->status);
    }

    public function test_a_clinical_desk_cannot_change_the_status_of_a_foreign_appointment(): void
    {
        $professional = $this->staff('odontologo');
        $theirs = $this->appointmentFor($this->staff('implantologo'));

        $this->actingAs($professional, 'sanctum')
            ->patchJson("/api/appointments/{$theirs->id}/status", [
                'status' => 'cancelled',
                'cancellation_reason' => 'No es mi paciente.',
            ])
            ->assertForbidden();

        $this->assertSame('confirmed', $theirs->fresh()->status);
    }

    public function test_a_clinical_desk_cannot_reschedule_or_remove_a_foreign_appointment(): void
    {
        $professional = $this->staff('odontologo');
        $theirs = $this->appointmentFor($this->staff('implantologo'));
        $originalStart = $theirs->scheduled_at?->toDateTimeString();

        $this->actingAs($professional, 'sanctum')
            ->patchJson("/api/appointments/{$theirs->id}/reschedule", [
                'scheduled_at' => now()->addDays(3)->setTime(15, 0)->toDateTimeString(),
                'duration_minutes' => 30,
            ])
            ->assertForbidden();

        $this->actingAs($professional, 'sanctum')
            ->deleteJson("/api/appointments/{$theirs->id}")
            ->assertForbidden();

        $theirs->refresh();

        $this->assertSame($originalStart, $theirs->scheduled_at?->toDateTimeString());
        $this->assertNull($theirs->deleted_at);
    }

    public function test_a_clinical_desk_operates_its_own_appointment(): void
    {
        $professional = $this->staff('odontologo');
        $mine = $this->appointmentFor($professional, 'confirmed', '09:00');

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$mine->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.status', 'arrived');

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$mine->id}/start-attention")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_consultation');

        $this->assertSame(2, AppointmentCustodyLog::query()->where('appointment_id', $mine->id)->count());
    }

    public function test_a_clinical_desk_reschedules_its_own_appointment(): void
    {
        $professional = $this->staff('odontologo');
        $mine = $this->appointmentFor($professional, 'confirmed', '09:00');

        $this->actingAs($professional, 'sanctum')
            ->patchJson("/api/appointments/{$mine->id}/reschedule", [
                'scheduled_at' => now()->addDays(2)->setTime(16, 0)->toDateTimeString(),
                'duration_minutes' => 30,
            ])
            ->assertOk();

        $this->assertSame(
            '16:00',
            $mine->fresh()->scheduled_at->format('H:i'),
            'A professional keeps rescheduling their own rows.',
        );
    }

    public function test_the_custody_doors_refuse_a_foreign_appointment(): void
    {
        $professional = $this->staff('odontologo');
        $theirs = $this->appointmentFor($this->staff('implantologo'), 'arrived');

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$theirs->id}/check-in")
            ->assertForbidden();

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$theirs->id}/start-attention")
            ->assertForbidden();

        $this->assertSame('arrived', $theirs->fresh()->status);
        $this->assertSame(0, AppointmentCustodyLog::query()->count());
    }

    public function test_the_arrival_fallback_records_the_door_of_the_professional_who_owns_the_row(): void
    {
        $professional = $this->staff('odontologo');
        $mine = $this->appointmentFor($professional, 'scheduled', '09:00');

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/appointments/{$mine->id}/check-in")
            ->assertOk();

        $log = AppointmentCustodyLog::query()->sole();

        $this->assertSame(AppointmentCustodyLog::DOOR_PROFESSIONAL, $log->door);
        $this->assertSame($professional->id, $log->actor_id);
    }

    public function test_reception_operates_the_appointment_of_any_professional_of_its_sede(): void
    {
        $branch = Branch::factory()->create();
        $reception = $this->staff('recepcionista', $branch);
        $appointment = $this->appointmentFor($this->staff('odontologo', $branch), 'arrived', '09:00', $branch);

        $this->actingAs($reception, 'sanctum')
            ->getJson("/api/appointments/{$appointment->id}")
            ->assertOk();

        $this->actingAs($reception, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/announce")
            ->assertOk()
            ->assertJsonPath('data.announced_by', $reception->id);
    }

    public function test_finance_keeps_no_access_to_the_agenda(): void
    {
        $finance = $this->staff('finanzas');
        $appointment = $this->appointmentFor($this->staff('odontologo'));

        $this->actingAs($finance, 'sanctum')
            ->getJson('/api/appointments')
            ->assertForbidden();

        $this->actingAs($finance, 'sanctum')
            ->getJson("/api/appointments/{$appointment->id}")
            ->assertForbidden();
    }

    /**
     * Product decision: only reception and administration book appointments.
     * The professional keeps the agenda they operate but never creates a row
     * for another patient.
     */
    public function test_a_clinical_desk_cannot_book_an_appointment(): void
    {
        $professional = $this->staff('odontologo');

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/appointments', $this->bookingPayload($professional))
            ->assertForbidden();

        $this->assertSame(
            0,
            Appointment::query()->count(),
            'A forbidden booking MUST NOT write anything.',
        );
    }

    public function test_reception_and_administration_keep_booking_appointments(): void
    {
        foreach (['recepcionista', 'administrador'] as $role) {
            $actor = $this->staff($role);

            $response = $this->actingAs($actor, 'sanctum')
                ->postJson('/api/appointments', $this->bookingPayload($this->staff('odontologo')));

            $response->assertCreated();
            $this->assertNotNull(Appointment::query()->find($response->json('data.id')));
        }
    }
}
