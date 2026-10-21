<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalAttachment;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract test for #265 + #266: the clinical attachment lifecycle has exactly
 * one public shape.
 *
 * Since #244 an attachment delete removes the binary from the `public` disk and
 * keeps the row as the archive marker (`is_active = false`). A relation that
 * still offers that row hands the caller a `/storage/...` URL that answers 404,
 * so both `MedicalRecord::attachments()` and `ClinicalEvolution::attachments()`
 * must exclude archived rows by default. The two endpoints that eagerly load
 * them today keep answering with the active set.
 */
class ClinicalAttachmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    /**
     * One record with one evolution carrying an active attachment and an
     * archived one — the same patient, because the relation must discriminate
     * on the attachment state, not on the patient.
     *
     * @return array{0: User, 1: MedicalRecord, 2: ClinicalEvolution, 3: ClinicalAttachment, 4: ClinicalAttachment}
     */
    protected function recordWithArchivedAttachment(): array
    {
        $user = $this->clinician();
        $patient = Patient::factory()->create();
        $record = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $user->id,
        ]);
        $evolution = ClinicalEvolution::factory()
            ->forRecord($record)
            ->create(['created_by' => $user->id]);

        $active = ClinicalAttachment::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $user->id,
            'clinical_evolution_id' => $evolution->id,
        ]);
        $archived = ClinicalAttachment::factory()->create([
            'patient_id' => $patient->id,
            'created_by' => $user->id,
            'clinical_evolution_id' => $evolution->id,
            'is_active' => false,
        ]);

        return [$user, $record, $evolution, $active, $archived];
    }

    public function test_medical_record_relation_returns_only_active_attachments(): void
    {
        [, $record, , $active, $archived] = $this->recordWithArchivedAttachment();

        $ids = $record->attachments()->get()->pluck('id')->all();

        $this->assertSame([$active->id], $ids);
        $this->assertNotContains($archived->id, $ids);
    }

    public function test_evolution_relation_returns_only_active_attachments(): void
    {
        [, , $evolution, $active, $archived] = $this->recordWithArchivedAttachment();

        $ids = $evolution->attachments()->get()->pluck('id')->all();

        $this->assertSame([$active->id], $ids);
        $this->assertNotContains($archived->id, $ids);
    }

    public function test_evolutions_endpoint_stops_offering_archived_attachments(): void
    {
        [$user, $record, $evolution, $active, $archived] = $this->recordWithArchivedAttachment();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/{$record->id}/evolutions");

        $response->assertOk();

        $evolutions = $response->json('data');
        $this->assertCount(1, $evolutions);
        $this->assertSame($evolution->id, $evolutions[0]['id']);

        $attachmentIds = array_column($evolutions[0]['attachments'], 'id');
        $this->assertSame([$active->id], $attachmentIds);
        $this->assertNotContains($archived->id, $attachmentIds);
    }

    public function test_record_detail_keeps_its_active_attachment_contract(): void
    {
        [$user, $record, , $active, $archived] = $this->recordWithArchivedAttachment();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/{$record->id}");

        $response->assertOk();

        $attachmentIds = array_column($response->json('data.attachments'), 'id');
        $this->assertSame([$active->id], $attachmentIds);
        $this->assertNotContains($archived->id, $attachmentIds);
    }
}
