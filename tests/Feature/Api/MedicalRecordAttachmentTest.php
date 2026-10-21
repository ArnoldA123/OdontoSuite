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
