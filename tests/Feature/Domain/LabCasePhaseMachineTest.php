<?php

namespace Tests\Feature\Domain;

use App\Events\LabCaseReady;
use App\Models\DentalPiece;
use App\Models\LabCase;
use App\Models\Patient;
use App\Models\User;
use App\Services\LabCaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * F5 (#285, S2/S4) — the phase machine only moves forward.
 *
 * A case advances to any later phase (a simple job skips the try-in) and never
 * goes back. Entering `delivered` means the work came back from the workshop:
 * that is the phase that leaves `delivered_at` and notifies the professional
 * and reception. Every other phase is silent.
 */
class LabCasePhaseMachineTest extends TestCase
{
    use RefreshDatabase;

    private LabCaseService $cases;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([LabCaseReady::class]);

        $this->cases = app(LabCaseService::class);
    }

    private function staff(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    private ?DentalPiece $piece = null;

    private function piece(): DentalPiece
    {
        return $this->piece ??= DentalPiece::firstOrCreate(['fdi_number' => '36'], [
            'name' => 'Primer molar inferior izquierdo',
            'type' => 'molar',
            'quadrant' => 'inferior_izquierdo',
            'position' => 6,
            'is_permanent' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(User $professional, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => Patient::factory()->create()->id,
            'professional_id' => $professional->id,
            'technician_id' => $this->staff('tecnico_dental')->id,
            'dental_piece_id' => $this->piece()->id,
            'work' => 'Corona metal-cerámica',
            'material' => 'Zirconia',
            'sent_at' => '2026-10-01',
            'promised_at' => '2026-10-10',
            'notes' => 'Margen subgingival en mesial.',
        ], $overrides);
    }

    private function caseInPhase(string $phase): LabCase
    {
        $professional = $this->staff('odontologo');
        $case = $this->cases->create($this->payload($professional), $professional);
        $case->forceFill(['status' => $phase])->save();

        return $case->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionFailure(LabCase $case, string $phase): array
    {
        try {
            $this->cases->transition($case, $phase);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail("The transition to [{$phase}] was expected to be rejected.");
    }

    #[Test]
    public function the_machine_advances_to_a_later_phase_and_allows_skipping_one(): void
    {
        $case = $this->caseInPhase(LabCase::PHASE_IN_PREPARATION);

        $this->assertSame(LabCase::PHASE_IN_LABORATORY, $this->cases->transition($case, LabCase::PHASE_IN_LABORATORY)->status);
        $this->assertSame(LabCase::PHASE_DELIVERED, $this->cases->transition($case->refresh(), LabCase::PHASE_DELIVERED)->status);
        $this->assertSame(LabCase::PHASE_PLACED, $this->cases->transition($case->refresh(), LabCase::PHASE_PLACED)->status);
    }

    #[Test]
    public function the_same_phase_is_rejected_and_the_status_is_untouched(): void
    {
        $case = $this->caseInPhase(LabCase::PHASE_IN_LABORATORY);

        $errors = $this->transitionFailure($case, LabCase::PHASE_IN_LABORATORY);

        $this->assertArrayHasKey('phase', $errors);
        $this->assertStringContainsString(LabCase::PHASE_IN_LABORATORY, $errors['phase'][0]);
        $this->assertSame(LabCase::PHASE_IN_LABORATORY, $case->fresh()->status);
    }

    #[Test]
    public function an_earlier_phase_is_rejected_and_names_both_phases(): void
    {
        $case = $this->caseInPhase(LabCase::PHASE_TRY_IN);

        $errors = $this->transitionFailure($case, LabCase::PHASE_IN_LABORATORY);

        $this->assertArrayHasKey('phase', $errors);
        $this->assertStringContainsString(LabCase::PHASE_TRY_IN, $errors['phase'][0]);
        $this->assertStringContainsString(LabCase::PHASE_IN_LABORATORY, $errors['phase'][0]);
        $this->assertSame(LabCase::PHASE_TRY_IN, $case->fresh()->status);
    }

    #[Test]
    public function an_unknown_phase_is_rejected_and_the_status_is_untouched(): void
    {
        $case = $this->caseInPhase(LabCase::PHASE_IN_PREPARATION);

        $errors = $this->transitionFailure($case, 'cancelled');

        $this->assertArrayHasKey('phase', $errors);
        $this->assertStringContainsString('cancelled', $errors['phase'][0]);
        $this->assertSame(LabCase::PHASE_IN_PREPARATION, $case->fresh()->status);
    }

    #[Test]
    public function entering_delivered_stamps_the_delivery_and_notifies(): void
    {
        $case = $this->caseInPhase(LabCase::PHASE_TRY_IN);

        $delivered = $this->cases->transition($case, LabCase::PHASE_DELIVERED);

        $this->assertSame(LabCase::PHASE_DELIVERED, $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
        $this->assertNull($delivered->placed_at);

        Event::assertDispatched(
            LabCaseReady::class,
            fn (LabCaseReady $event) => $event->case->id === $delivered->id,
        );
    }

    #[Test]
    public function entering_placed_stamps_the_placement_and_does_not_notify(): void
    {
        $case = $this->caseInPhase(LabCase::PHASE_DELIVERED);

        $placed = $this->cases->transition($case, LabCase::PHASE_PLACED);

        $this->assertSame(LabCase::PHASE_PLACED, $placed->status);
        $this->assertNotNull($placed->placed_at);

        Event::assertNotDispatched(LabCaseReady::class);
    }

    #[Test]
    public function any_other_phase_stays_silent(): void
    {
        $case = $this->caseInPhase(LabCase::PHASE_IN_PREPARATION);

        $this->cases->transition($case, LabCase::PHASE_IN_LABORATORY);

        Event::assertNotDispatched(LabCaseReady::class);
    }

    #[Test]
    public function the_assigned_technician_must_be_an_active_laboratory_technician(): void
    {
        $professional = $this->staff('odontologo');
        $notATechnician = $this->staff('asistente');

        try {
            $this->cases->create($this->payload($professional, ['technician_id' => $notATechnician->id]), $professional);
            $this->fail('A case was expected to reject a technician who is not a laboratory technician.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('technician_id', $e->errors());
            $this->assertStringContainsString('técnico', $e->errors()['technician_id'][0]);
        }

        $inactive = $this->staff('tecnico_dental', ['is_active' => false]);

        try {
            $this->cases->create($this->payload($professional, ['technician_id' => $inactive->id]), $professional);
            $this->fail('A case was expected to reject an inactive technician.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('technician_id', $e->errors());
        }

        $this->assertSame(0, LabCase::query()->count());
    }

    #[Test]
    public function the_professional_must_be_an_active_dentist_or_implantologist(): void
    {
        $professional = $this->staff('odontologo');
        $receptionist = $this->staff('recepcionista');

        try {
            $this->cases->create($this->payload($professional, ['professional_id' => $receptionist->id]), $professional);
            $this->fail('A case was expected to reject a professional who does not prescribe prosthetic work.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('professional_id', $e->errors());
            $this->assertStringContainsString('odontólogo', $e->errors()['professional_id'][0]);
        }

        $inactive = $this->staff('implantologo', ['is_active' => false]);

        try {
            $this->cases->create($this->payload($professional, ['professional_id' => $inactive->id]), $professional);
            $this->fail('A case was expected to reject an inactive professional.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('professional_id', $e->errors());
        }

        $this->assertSame(0, LabCase::query()->count());
    }

    #[Test]
    public function a_case_can_be_created_without_a_technician_assigned(): void
    {
        $professional = $this->staff('odontologo');

        $case = $this->cases->create($this->payload($professional, ['technician_id' => null]), $professional);

        $this->assertNull($case->technician_id);
        $this->assertSame($professional->id, $case->professional_id);
    }

    #[Test]
    public function the_ready_event_publishes_on_the_laboratory_and_professional_private_channels(): void
    {
        $professional = $this->staff('odontologo');
        $technician = $this->staff('tecnico_dental');
        $case = $this->cases->create($this->payload($professional, [
            'technician_id' => $technician->id,
        ]), $professional);
        $case = $this->cases->transition($case, LabCase::PHASE_DELIVERED);

        $event = new LabCaseReady($case);

        $this->assertSame('caso_laboratorio_listo', $event->broadcastAs());

        $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

        $this->assertContains('private-lab-cases', $channels);
        $this->assertContains("private-user.{$professional->id}", $channels);

        $payload = $event->broadcastWith();

        $this->assertSame($case->id, $payload['case']['id']);
        $this->assertSame('Corona metal-cerámica', $payload['case']['work']);
        $this->assertSame('Zirconia', $payload['case']['material']);
        $this->assertSame(LabCase::PHASE_DELIVERED, $payload['case']['status']);
        $this->assertNotNull($payload['case']['delivered_at']);
        $this->assertSame('36', $payload['piece']['fdi_number']);
        $this->assertSame($professional->id, $payload['professional']['id']);
        $this->assertSame($professional->name, $payload['professional']['name']);
        $this->assertSame($technician->id, $payload['technician']['id']);
        $this->assertArrayHasKey('full_name', $payload['patient']);
        $this->assertArrayNotHasKey('notes', $payload, 'The laboratory notice must not carry clinical content.');
    }

    #[Test]
    public function the_ready_event_carries_no_piece_when_the_case_names_none(): void
    {
        $professional = $this->staff('odontologo');
        $case = $this->cases->create($this->payload($professional, [
            'dental_piece_id' => null,
            'technician_id' => null,
        ]), $professional);

        $payload = (new LabCaseReady($case))->broadcastWith();

        $this->assertNull($payload['piece']);
        $this->assertNull($payload['technician']);
    }
}
