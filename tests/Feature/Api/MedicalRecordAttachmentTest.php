<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalAttachment;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * RED test (slice 01, T-01.9) for API-001:
 *  - DELETE /api/medical-records/attachments/{attachment} returns 204.
 *  - Non-clinical role returns 403.
 */
class MedicalRecordAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function clinician(): User
    {
        return User::factory()->create([
            'role' => 'odontologo',
            'is_active' => true,
        ]);
    }

    protected function receptionist(): User
    {
        return User::factory()->create([
            'role' => 'recepcionista',
            'is_active' => true,
        ]);
    }

    public function test_clinician_can_delete_attachment_returns_204(): void
    {
        $user = $this->clinician();

        $medicalRecord = MedicalRecord::factory()->create();
        // There is no `medical_record_id` on `clinical_attachments`: a file
        // reaches a medical record through a clinical evolution, which is what
        // MedicalRecord::attachments() (hasManyThrough) and uploadAttachment()
        // both encode. The delete route resolves the attachment by id alone.
        // Factories run inside Model::unguarded(), so this key used to reach the
        // INSERT instead of being discarded by mass-assignment protection.
        $attachment = ClinicalAttachment::factory()->create([
            'patient_id' => $medicalRecord->patient_id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/medical-records/attachments/{$attachment->id}");

        $response->assertNoContent();
    }

    /**
     * #244: deleting an attachment owns the whole contract, so the stored
     * binary goes away too. A file left behind is unreachable from the app
     * and still served by the public disk. The row survives as the archive
     * marker for the clinical audit trail.
     */
    public function test_deleting_an_attachment_removes_the_stored_file(): void
    {
        Storage::fake('public');

        $attachment = $this->attachmentWithStoredFile();

        $this->actingAs($this->clinician(), 'sanctum')
            ->deleteJson("/api/medical-records/attachments/{$attachment->id}")
            ->assertNoContent();

        Storage::disk('public')->assertMissing($attachment->file_path);
        $this->assertDatabaseHas('clinical_attachments', [
            'id' => $attachment->id,
            'is_active' => false,
        ]);
    }

    /**
     * Deleting an already deleted attachment is a no-op: the row is still
     * there, the binary is already gone, and a second call must not fail.
     */
    public function test_deleting_an_already_deleted_attachment_returns_204(): void
    {
        Storage::fake('public');

        $attachment = $this->attachmentWithStoredFile();
        $user = $this->clinician();
        $url = "/api/medical-records/attachments/{$attachment->id}";

        $this->actingAs($user, 'sanctum')->deleteJson($url)->assertNoContent();
        $this->actingAs($user, 'sanctum')->deleteJson($url)->assertNoContent();

        Storage::disk('public')->assertMissing($attachment->file_path);
        $this->assertDatabaseHas('clinical_attachments', [
            'id' => $attachment->id,
            'is_active' => false,
            'deleted_at' => null,
        ]);
    }

    /**
     * Contract that already held before the endpoint delegated to the service:
     * an unknown id is a 404 with the same envelope, not a 500.
     */
    public function test_deleting_a_missing_attachment_returns_404(): void
    {
        $this->actingAs($this->clinician(), 'sanctum')
            ->deleteJson('/api/medical-records/attachments/999999')
            ->assertNotFound()
            ->assertJson(['message' => 'Adjunto no encontrado']);
    }

    protected function attachmentWithStoredFile(): ClinicalAttachment
    {
        $medicalRecord = MedicalRecord::factory()->create();
        $attachment = ClinicalAttachment::factory()->create([
            'patient_id' => $medicalRecord->patient_id,
        ]);
        Storage::disk('public')->put($attachment->file_path, 'contenido clinico');

        return $attachment;
    }

    public function test_upload_without_a_category_uses_the_general_default(): void
    {
        Storage::fake('public');

        $medicalRecord = MedicalRecord::factory()->create();

        $response = $this->actingAs($this->clinician(), 'sanctum')
            ->post('/api/medical-records/attachments', [
                'patient_id' => $medicalRecord->patient_id,
                'file' => UploadedFile::fake()->create('informe.pdf', 120, 'application/pdf'),
            ]);

        $response->assertCreated();
        $this->assertSame('general', $response->json('data.category'));
    }

    /**
     * `general` is the service default and the category filter already accepts
     * it, so the upload must accept it explicitly too instead of rejecting a
     * value the rest of the contract already uses.
     */
    public function test_the_general_category_is_accepted_explicitly(): void
    {
        Storage::fake('public');

        $medicalRecord = MedicalRecord::factory()->create();

        $response = $this->actingAs($this->clinician(), 'sanctum')
            ->post('/api/medical-records/attachments', [
                'patient_id' => $medicalRecord->patient_id,
                'category' => 'general',
                'file' => UploadedFile::fake()->create('informe.pdf', 120, 'application/pdf'),
            ]);

        $response->assertCreated();
        $this->assertSame('general', $response->json('data.category'));
    }

    public function test_non_clinical_role_returns_403(): void
    {
        $user = $this->receptionist();

        $medicalRecord = MedicalRecord::factory()->create();
        // See test_clinician_can_delete_attachment_returns_204 for why the
        // attachment carries no `medical_record_id`.
        $attachment = ClinicalAttachment::factory()->create([
            'patient_id' => $medicalRecord->patient_id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/medical-records/attachments/{$attachment->id}");

        $response->assertForbidden();
    }
}
