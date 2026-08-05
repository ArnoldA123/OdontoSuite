<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\ClinicalAttachment;
use App\Models\MedicalRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $attachment = ClinicalAttachment::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'patient_id' => $medicalRecord->patient_id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/medical-records/attachments/{$attachment->id}");

        $response->assertNoContent();
    }

    public function test_non_clinical_role_returns_403(): void
    {
        $user = $this->receptionist();

        $medicalRecord = MedicalRecord::factory()->create();
        $attachment = ClinicalAttachment::factory()->create([
            'medical_record_id' => $medicalRecord->id,
            'patient_id' => $medicalRecord->patient_id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/medical-records/attachments/{$attachment->id}");

        $response->assertForbidden();
    }
}
