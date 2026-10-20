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
 * Contract tests for the two patient-scoped endpoints the Estadísticas and
 * Adjuntos surfaces consume:
 *   GET /api/medical-records/patient/{patient}/stats
 *   GET /api/medical-records/patient/{patient}/attachments.
 *
 * Both used to describe a different population than the one the interface
 * shows: stats read only the first active record of the patient, and the
 * attachment list demanded a category the filters never send for "todas".
 */
class MedicalRecordStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    public function test_stats_aggregate_every_active_record_of_the_patient(): void
    {
        $user = $this->clinician();
        $patient = Patient::factory()->create();
        $first = MedicalRecord::factory()->create(['patient_id' => $patient->id]);
        $second = MedicalRecord::factory()->create(['patient_id' => $patient->id]);

        ClinicalEvolution::factory()->count(2)->forRecord($first)->create();
        ClinicalEvolution::factory()->forRecord($second)->create([
            'specialty' => 'implantologia',
        ]);

        ClinicalAttachment::factory()->create([
            'patient_id' => $patient->id,
            'is_active' => true,
        ]);
        ClinicalAttachment::factory()->create([
            'patient_id' => $patient->id,
            'is_active' => true,
        ]);
        ClinicalAttachment::factory()->create([
            'patient_id' => $patient->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/patient/{$patient->id}/stats");

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total_records'));
        $this->assertSame(3, $response->json('data.total_evolutions'));
        $this->assertSame(2, $response->json('data.total_attachments'));
    }

    public function test_stats_report_latest_evolution_and_unique_specialties(): void
    {
        $user = $this->clinician();
        $record = MedicalRecord::factory()->create();
        $patientId = $record->patient_id;

        ClinicalEvolution::factory()->forRecord($record)->create([
            'evolution_date' => now()->subMonths(2)->toDateString(),
            'specialty' => 'endodoncia',
        ]);
        $latest = ClinicalEvolution::factory()->forRecord($record)->create([
            'evolution_date' => now()->subDay()->toDateString(),
            'specialty' => 'endodoncia',
        ]);
        ClinicalEvolution::factory()->forRecord($record)->create([
            'evolution_date' => now()->subMonth()->toDateString(),
            'specialty' => 'ortodoncia',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/patient/{$patientId}/stats");

        $response->assertOk();
        $this->assertSame($latest->id, $response->json('data.last_evolution.id'));
        $this->assertEqualsCanonicalizing(
            ['endodoncia', 'ortodoncia'],
            $response->json('data.specialties_involved')
        );
    }

    public function test_stats_ignore_deactivated_records(): void
    {
        $user = $this->clinician();
        $patient = Patient::factory()->create();
        $inactive = MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'is_active' => false,
        ]);
        ClinicalEvolution::factory()->count(3)->forRecord($inactive)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/patient/{$patient->id}/stats");

        $response->assertOk();
        $this->assertSame(0, $response->json('data.total_evolutions'));
        $this->assertSame(0, $response->json('data.total_records'));
    }

    public function test_stats_are_zeroed_for_a_patient_without_records(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->actingAs($this->clinician(), 'sanctum')
            ->getJson("/api/medical-records/patient/{$patient->id}/stats");

        $response->assertOk();
        $this->assertSame(0, $response->json('data.total_evolutions'));
        $this->assertSame(0, $response->json('data.total_attachments'));
        $this->assertNull($response->json('data.last_evolution'));
        $this->assertSame([], $response->json('data.specialties_involved'));
    }

    public function test_attachments_list_returns_every_active_attachment_without_a_category(): void
    {
        $user = $this->clinician();
        $patient = Patient::factory()->create();

        $xray = ClinicalAttachment::factory()->create([
            'patient_id' => $patient->id,
            'category' => 'radiografia',
            'is_active' => true,
        ]);
        $document = ClinicalAttachment::factory()->create([
            'patient_id' => $patient->id,
            'category' => 'documento',
            'is_active' => true,
        ]);
        ClinicalAttachment::factory()->create([
            'patient_id' => $patient->id,
            'category' => 'otro',
            'is_active' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/patient/{$patient->id}/attachments");

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$xray->id, $document->id], $ids);
    }

    public function test_attachments_list_filters_by_category(): void
    {
        $user = $this->clinician();
        $patient = Patient::factory()->create();
        $xray = ClinicalAttachment::factory()->create([
            'patient_id' => $patient->id,
            'category' => 'radiografia',
        ]);
        ClinicalAttachment::factory()->create([
            'patient_id' => $patient->id,
            'category' => 'documento',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/patient/{$patient->id}/attachments?category=radiografia");

        $response->assertOk();
        $this->assertSame([$xray->id], array_column($response->json('data'), 'id'));
    }

    public function test_attachments_list_rejects_an_unknown_category(): void
    {
        $patient = Patient::factory()->create();

        $this->actingAs($this->clinician(), 'sanctum')
            ->getJson("/api/medical-records/patient/{$patient->id}/attachments?category=inventada")
            ->assertStatus(422);
    }
}
