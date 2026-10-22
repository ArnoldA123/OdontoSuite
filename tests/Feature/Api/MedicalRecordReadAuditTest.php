<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicalRecordAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * F3 (#292, S3/S5) — every read of a clinical history is recorded.
 *
 * The five surfaces that expose clinical content write a `medical_record_viewed`
 * entry with the surface that produced it: the index logs one entry per record
 * on the page, the record and evolution views log the record, and the
 * patient-scoped stats/attachments log each active record of that patient. The
 * write is not silenced: a failing audit fails the read instead of handing out
 * unlogged clinical content.
 */
class MedicalRecordReadAuditTest extends TestCase
{
    use RefreshDatabase;

    private function clinician(): User
    {
        return User::factory()->create(['role' => 'odontologo', 'is_active' => true]);
    }

    private function receptionist(): User
    {
        return User::factory()->create(['role' => 'recepcionista', 'is_active' => true]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, AuditLog>
     */
    private function viewLogs()
    {
        return AuditLog::where('action', 'medical_record_viewed')->orderBy('id')->get();
    }

    public function test_index_audits_one_read_for_every_record_on_the_page(): void
    {
        $user = $this->clinician();
        $patient = Patient::factory()->create();
        MedicalRecord::factory()->count(3)->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records?patient_id={$patient->id}&per_page=2");

        $response->assertOk();
        $returned = array_column($response->json('data'), 'id');
        $this->assertCount(2, $returned);

        $logs = $this->viewLogs();
        $this->assertCount(2, $logs);
        $this->assertEqualsCanonicalizing($returned, $logs->pluck('auditable_id')->all());

        foreach ($logs as $log) {
            $this->assertSame($user->id, $log->user_id);
            $this->assertSame(MedicalRecord::class, $log->auditable_type);
            $this->assertSame(['surface' => 'index'], $log->metadata);
        }
    }

    public function test_show_audits_the_record_it_returns(): void
    {
        $user = $this->clinician();
        $record = MedicalRecord::factory()->create();
        MedicalRecord::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/{$record->id}")
            ->assertOk();

        $logs = $this->viewLogs();
        $this->assertCount(1, $logs);
        $this->assertSame($record->id, $logs->first()->auditable_id);
        $this->assertSame($user->id, $logs->first()->user_id);
        $this->assertSame(['surface' => 'record'], $logs->first()->metadata);
    }

    public function test_evolutions_audits_the_record_whose_timeline_it_returns(): void
    {
        $user = $this->clinician();
        $record = MedicalRecord::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/{$record->id}/evolutions")
            ->assertOk();

        $logs = $this->viewLogs();
        $this->assertCount(1, $logs);
        $this->assertSame($record->id, $logs->first()->auditable_id);
        $this->assertSame($user->id, $logs->first()->user_id);
        $this->assertSame(['surface' => 'evolutions'], $logs->first()->metadata);
    }

    public function test_stats_audits_each_active_record_of_the_patient(): void
    {
        $user = $this->clinician();
        $patient = Patient::factory()->create();
        $active = MedicalRecord::factory()->count(2)->create(['patient_id' => $patient->id]);
        MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'is_active' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/patient/{$patient->id}/stats")
            ->assertOk();

        $logs = $this->viewLogs();
        $this->assertEqualsCanonicalizing($active->pluck('id')->all(), $logs->pluck('auditable_id')->all());

        foreach ($logs as $log) {
            $this->assertSame($user->id, $log->user_id);
            $this->assertSame(['surface' => 'stats'], $log->metadata);
        }
    }

    public function test_attachments_audits_each_active_record_of_the_patient(): void
    {
        $user = $this->clinician();
        $patient = Patient::factory()->create();
        $active = MedicalRecord::factory()->count(2)->create(['patient_id' => $patient->id]);
        MedicalRecord::factory()->create([
            'patient_id' => $patient->id,
            'is_active' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/patient/{$patient->id}/attachments")
            ->assertOk();

        $logs = $this->viewLogs();
        $this->assertEqualsCanonicalizing($active->pluck('id')->all(), $logs->pluck('auditable_id')->all());

        foreach ($logs as $log) {
            $this->assertSame($user->id, $log->user_id);
            $this->assertSame(['surface' => 'attachments'], $log->metadata);
        }
    }

    public function test_reception_cannot_read_any_surface_and_writes_no_audit_row(): void
    {
        $patient = Patient::factory()->create();
        $record = MedicalRecord::factory()->create(['patient_id' => $patient->id]);
        $reception = $this->receptionist();

        $this->actingAs($reception, 'sanctum')->getJson('/api/medical-records')->assertForbidden();
        $this->actingAs($reception, 'sanctum')->getJson("/api/medical-records/{$record->id}")->assertForbidden();
        $this->actingAs($reception, 'sanctum')->getJson("/api/medical-records/{$record->id}/evolutions")->assertForbidden();
        $this->actingAs($reception, 'sanctum')->getJson("/api/medical-records/patient/{$patient->id}/stats")->assertForbidden();
        $this->actingAs($reception, 'sanctum')->getJson("/api/medical-records/patient/{$patient->id}/attachments")->assertForbidden();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_failing_audit_write_fails_the_read(): void
    {
        $record = MedicalRecord::factory()->create();
        $user = $this->clinician();

        $this->mock(ClinicalRecordAuditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('recordViewed')->andThrow(new \RuntimeException('audit unavailable'));
            $mock->shouldReceive('recordsViewed')->andThrow(new \RuntimeException('audit unavailable'));
            $mock->shouldReceive('patientRecordsViewed')->andThrow(new \RuntimeException('audit unavailable'));
        });

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/medical-records/{$record->id}")
            ->assertStatus(500);

        $this->assertDatabaseCount('audit_logs', 0);
    }
}
