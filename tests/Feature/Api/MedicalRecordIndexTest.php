<?php

namespace Tests\Feature\Api;

use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for GET /api/medical-records (the records index behind the
 * Historias clínicas workspace).
 *
 * The index is the only endpoint that can list a patient's records without
 * knowing the id, so its filters are the difference between an interface that
 * hides deactivated records and one that offers a clinician a record they can
 * no longer open.
 */
class MedicalRecordIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    public function test_index_hides_deactivated_records_by_default(): void
    {
        $user = $this->clinician();
        $patient = Patient::factory()->create();
        $active = MedicalRecord::factory()->create(['patient_id' => $patient->id]);
        MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records?patient_id={$patient->id}");

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');

        $this->assertSame([$active->id], $ids);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_index_status_all_includes_deactivated_records(): void
    {
        $user = $this->clinician();
        $patient = Patient::factory()->create();
        MedicalRecord::factory()->create(['patient_id' => $patient->id]);
        MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records?patient_id={$patient->id}&status=all");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_searches_by_record_number_and_patient_name(): void
    {
        $user = $this->clinician();
        $target = MedicalRecord::factory()->create([
            'patient_id' => Patient::factory()->create(['last_name' => 'Quispe'])->id,
            'record_number' => 'HC-2026-ABC123',
        ]);
        MedicalRecord::factory()->create([
            'patient_id' => Patient::factory()->create(['last_name' => 'Ramos'])->id,
        ]);

        $byNumber = $this->actingAs($user, 'sanctum')
            ->getJson('/api/medical-records?search=ABC123');
        $byNumber->assertOk();
        $this->assertSame([$target->id], array_column($byNumber->json('data'), 'id'));

        $byName = $this->actingAs($user, 'sanctum')
            ->getJson('/api/medical-records?search=Quispe');
        $byName->assertOk();
        $this->assertSame([$target->id], array_column($byName->json('data'), 'id'));
    }

    public function test_index_honours_per_page_and_caps_it(): void
    {
        $user = $this->clinician();
        MedicalRecord::factory()->count(3)->create();

        $limited = $this->actingAs($user, 'sanctum')
            ->getJson('/api/medical-records?per_page=2');
        $limited->assertOk();
        $this->assertCount(2, $limited->json('data'));
        $this->assertSame(2, $limited->json('meta.per_page'));

        $capped = $this->actingAs($user, 'sanctum')
            ->getJson('/api/medical-records?per_page=500');
        $capped->assertOk();
        $this->assertSame(50, $capped->json('meta.per_page'));
    }

    public function test_index_reports_evolution_and_active_attachment_counts(): void
    {
        $user = $this->clinician();
        $record = MedicalRecord::factory()->create();

        $evolutions = ClinicalEvolution::factory()->count(2)->forRecord($record)->create();
        ClinicalEvolution::factory()->count(1)->forRecord($record)
            ->create(['deleted_at' => now()]);

        \App\Models\ClinicalAttachment::factory()->create([
            'patient_id' => $record->patient_id,
            'clinical_evolution_id' => $evolutions->first()->id,
            'is_active' => true,
        ]);
        \App\Models\ClinicalAttachment::factory()->create([
            'patient_id' => $record->patient_id,
            'clinical_evolution_id' => $evolutions->last()->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/medical-records?patient_id='.$record->patient_id);

        $response->assertOk();
        $row = $response->json('data.0');

        $this->assertSame(2, $row['evolutions_count']);
        $this->assertSame(1, $row['attachments_count']);
        $this->assertSame($record->patient_id, $row['patient']['id']);
    }
}
