<?php

namespace Tests\Feature\Api;

use App\Events\ClinicalAddendumCreated;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Realtime contract for the correction surface (F3 #292).
 *
 * The evolution *edit* broadcast is gone with the PUT route it belonged to:
 * another tab refreshes when a correction is added, not when a row is rewritten.
 * The addendum travels privately on the same `medical-records` channel the
 * timeline already subscribes to, and carries the history and patient the
 * client needs to decide whether the event belongs to the open record.
 */
class MedicalRecordEvolutionEventsTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    public function test_creating_an_addendum_broadcasts_it_privately(): void
    {
        Event::fake([ClinicalAddendumCreated::class]);

        $user = $this->clinician();
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/medical-records/evolutions/{$evolution->id}/addenda", [
                'reason' => 'Hallazgo adicional',
                'content' => 'La lesión comprometía la cúspide vestibular.',
            ])
            ->assertCreated();

        Event::assertDispatched(ClinicalAddendumCreated::class, function ($event) use ($user, $evolution, $record) {
            $payload = $event->broadcastWith();
            $serialized = json_decode($payload['addendum']->toJson(), true);

            return $event->addendum->clinical_evolution_id === $evolution->id
                && $event->addendum->medical_record_id === $record->id
                && $event->addendum->user_id === $user->id
                && $event->broadcastAs() === 'clinical-addendum.created'
                && $event->broadcastOn()[0]->name === 'private-medical-records'
                && $payload['medical_record_id'] === $record->id
                && $payload['patient_id'] === $record->patient_id
                && $serialized['id'] === $event->addendum->id
                && $serialized['author']['id'] === $user->id;
        });
    }

    public function test_a_rejected_addendum_does_not_broadcast(): void
    {
        Event::fake([ClinicalAddendumCreated::class]);

        $record = MedicalRecord::factory()->create();

        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson("/api/medical-records/{$record->id}/addenda", [
                'content' => 'Sin motivo declarado.',
            ])
            ->assertUnprocessable();

        Event::assertNotDispatched(ClinicalAddendumCreated::class);
    }
}
