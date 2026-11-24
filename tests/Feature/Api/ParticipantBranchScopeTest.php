<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * citas-privacidad (T4) — el alta de cita no cruza sedes.
 *
 * Un actor restringido solo asigna profesionales y ambientes de su sede: nombrar
 * otro es 403, porque la sede es una dimensión de autorización (ADR-0010) y el
 * guard de escritura del modelo responde lo mismo. El 422 queda para el alcance
 * de grupo, que sí puede elegir cualquier participante y aun así no puede unir
 * dos sedes en una sola cita. La edición parcial completa la comparación con los
 * participantes ya guardados.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class ParticipantBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, ?Branch $branch = null, string $suffix = ''): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
            'email' => 'participant.branch'.($suffix === '' ? '' : '.'.$suffix).'@example.com',
        ]);
    }

    private function chair(?Branch $branch, string $suffix = ''): DentalChair
    {
        return DentalChair::factory()->create([
            'branch_id' => $branch?->id,
            'code' => 'AMB-T4-PART'.($suffix === '' ? '' : '-'.$suffix),
            'name' => 'Ambiente participante'.($suffix === '' ? '' : ' '.$suffix),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $professional, DentalChair $chair, string $time = '10:00'): array
    {
        return [
            'patient_id' => Patient::factory()->create()->id,
            'user_id' => $professional->id,
            'dental_chair_id' => $chair->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'scheduled_at' => Carbon::now()->addDays(3)->startOfDay()
                ->setTimeFromTimeString($time)->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
        ];
    }

    private function appointmentIn(Branch $branch, User $professional, DentalChair $chair): Appointment
    {
        $scheduledAt = Carbon::now()->addDay()->startOfDay()->setTime(9, 0);

        return Appointment::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $professional->id,
            'dental_chair_id' => $chair->id,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->copy()->addMinutes(30),
            'duration_minutes' => 30,
        ]);
    }

    public function test_reception_cannot_assign_a_professional_of_another_branch(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $actor = $this->staff('recepcionista', $trujillo);
        $foreign = $this->staff('odontologo', $lima, 'foreign');

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/appointments', $this->payload($foreign, $this->chair($trujillo)));

        // La sede del participante es autorización, no un campo mal formado.
        $response->assertForbidden();
        $this->assertSame(0, Appointment::count());
    }

    public function test_reception_cannot_assign_an_environment_of_another_branch(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $actor = $this->staff('recepcionista', $trujillo);
        $own = $this->staff('odontologo', $trujillo, 'own');
        // La sede ajena se prepara antes de autenticar: el guard de escritura
        // del modelo rechaza creación de filas de otra sede para el actor.
        $foreignChair = $this->chair($lima);

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/appointments', $this->payload($own, $foreignChair));

        $response->assertForbidden();
        $this->assertSame(0, Appointment::count());
    }

    public function test_reception_creates_the_appointment_when_both_participants_share_their_branch(): void
    {
        $trujillo = Branch::factory()->create();

        $actor = $this->staff('recepcionista', $trujillo);
        $own = $this->staff('odontologo', $trujillo, 'own');

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/appointments', $this->payload($own, $this->chair($trujillo)));

        $response->assertCreated();
        $this->assertSame(1, Appointment::count());
    }

    public function test_group_scope_still_cannot_pair_participants_of_two_branches(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $actor = $this->staff('administrador');
        $professional = $this->staff('odontologo', $trujillo, 'trujillo');

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/appointments', $this->payload($professional, $this->chair($lima)));

        $response->assertStatus(422)->assertJsonValidationErrors(['dental_chair_id']);
        $this->assertSame(
            'El ambiente y el profesional deben pertenecer a la misma sede.',
            $response->json('errors.dental_chair_id.0')
        );
        $this->assertSame(0, Appointment::count());
    }

    public function test_a_partial_update_completes_the_comparison_with_the_stored_professional(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $actor = $this->staff('recepcionista', $trujillo);
        $professional = $this->staff('odontologo', $trujillo, 'own');
        $appointment = $this->appointmentIn($trujillo, $professional, $this->chair($trujillo, 'own'));
        $foreignChair = $this->chair($lima, 'lima');

        $response = $this->actingAs($actor, 'sanctum')
            ->putJson("/api/appointments/{$appointment->id}", [
                'dental_chair_id' => $foreignChair->id,
            ]);

        $response->assertForbidden();
        $this->assertNotSame(
            DentalChair::where('code', 'AMB-T4-PART-lima')->value('id'),
            $appointment->refresh()->dental_chair_id
        );
    }

    public function test_a_partial_update_that_only_changes_the_professional_is_compared_too(): void
    {
        $trujillo = Branch::factory()->create();
        $lima = Branch::factory()->create();

        $actor = $this->staff('recepcionista', $trujillo);
        $professional = $this->staff('odontologo', $trujillo, 'own');
        $appointment = $this->appointmentIn($trujillo, $professional, $this->chair($trujillo));

        $foreign = $this->staff('odontologo', $lima, 'foreign');

        $response = $this->actingAs($actor, 'sanctum')
            ->putJson("/api/appointments/{$appointment->id}", ['user_id' => $foreign->id]);

        $response->assertForbidden();
        $this->assertSame($professional->id, $appointment->refresh()->user_id);
    }

    public function test_a_partial_update_that_keeps_the_pair_intact_passes(): void
    {
        $trujillo = Branch::factory()->create();

        $actor = $this->staff('recepcionista', $trujillo);
        $professional = $this->staff('odontologo', $trujillo, 'own');
        $appointment = $this->appointmentIn($trujillo, $professional, $this->chair($trujillo));

        $response = $this->actingAs($actor, 'sanctum')
            ->putJson("/api/appointments/{$appointment->id}", ['duration_minutes' => 45]);

        $response->assertOk();
        $this->assertSame(45, $appointment->refresh()->duration_minutes);
    }

    public function test_a_professional_without_sede_does_not_contradict_the_rule(): void
    {
        // Dato heredado: la migración de datos lo rellena, y el límite HTTP no
        // convierte un flujo que hoy funciona en un 422 nuevo.
        $trujillo = Branch::factory()->create();

        $actor = $this->staff('recepcionista', $trujillo);
        $legacy = $this->staff('odontologo', null, 'legacy');

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/appointments', $this->payload($legacy, $this->chair($trujillo)));

        $response->assertCreated();
        $this->assertSame(1, Appointment::count());
    }
}
