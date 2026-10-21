<?php

namespace Tests\Feature\Api;

use App\Events\ClinicalEvolutionDeleted;
use App\Events\ClinicalEvolutionUpdated;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Realtime contract for evolution writes (issue #223).
 *
 * The Evoluciones surface already listens for `medical-record.created/updated`
 * and `clinical-evolution.created`, so editing or deleting an evolution left a
 * second tab (or a second professional) on stale data until a manual refresh.
 * These tests pin the two missing broadcasts and the deletion payload the
 * client needs to decide whether it owns the row: `medical_record_id` and
 * `patient_id`.
 */
class MedicalRecordEvolutionEventsTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    public function test_updating_an_evolution_broadcasts_the_updated_event(): void
    {
        Event::fake([ClinicalEvolutionUpdated::class]);

        $user = $this->clinician();
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/medical-records/evolutions/{$evolution->id}", [
                'subjective' => 'Dolor agudo al masticar',
            ])
            ->assertOk();

        Event::assertDispatched(ClinicalEvolutionUpdated::class, function ($event) use ($evolution, $record) {
            $payload = $event->broadcastWith();
            $serialized = json_decode($payload['evolution']->toJson(), true);

            return $event->evolution->id === $evolution->id
                && $event->evolution->medical_record_id === $record->id
                && $event->broadcastAs() === 'clinical-evolution.updated'
                && $event->broadcastOn()[0]->name === 'medical-records'
                && $serialized['medical_record']['id'] === $record->id
                && $serialized['medical_record']['patient_id'] === $record->patient_id;
        });
    }

    public function test_deleting_an_evolution_broadcasts_its_record_and_patient(): void
    {
        Event::fake([ClinicalEvolutionDeleted::class]);

        $user = $this->clinician();
        $record = MedicalRecord::factory()->create();
        $evolution = ClinicalEvolution::factory()->forRecord($record)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/medical-records/evolutions/{$evolution->id}")
            ->assertOk();

        Event::assertDispatched(ClinicalEvolutionDeleted::class, function ($event) use ($evolution, $record) {
            $payload = $event->broadcastWith();

            return $event->evolution->id === $evolution->id
                && $event->broadcastAs() === 'clinical-evolution.deleted'
                && $event->broadcastOn()[0]->name === 'medical-records'
                && $payload['evolution_id'] === $evolution->id
                && $payload['medical_record_id'] === $record->id
                && $payload['patient_id'] === $record->patient_id;
        });
    }

    public function test_a_rejected_update_does_not_broadcast(): void
    {
        Event::fake([ClinicalEvolutionUpdated::class]);

        $evolution = ClinicalEvolution::factory()->create();

        $this->actingAs($this->clinician(), 'sanctum')
            ->putJson("/api/medical-records/evolutions/{$evolution->id}", [
                'clinical_measurements' => ['plaque_index_percent' => 150],
            ])
            ->assertUnprocessable();

        Event::assertNotDispatched(ClinicalEvolutionUpdated::class);
    }
}
