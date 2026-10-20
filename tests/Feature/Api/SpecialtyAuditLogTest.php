<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit contract for the specialties catalog (issue #213).
 *
 * The comparable admin masters (appointment types, dental chairs, procedure
 * catalog) already record who created or changed a row. The specialties catalog
 * did not, so a rename or a deactivation left no trace in a taxonomy that
 * procedures and professionals consume.
 *
 * The contract pinned here:
 *  - POST /api/specialties writes a `specialty_created` entry with the actor,
 *    the auditable pair and the created values.
 *  - PUT /api/specialties/{id} writes a `specialty_updated` entry carrying the
 *    previous and the new values of every audited field, including the
 *    `is_active` toggle.
 *  - The catalog's existing response contract and rejected-input behavior do
 *    not change: a 422 or a 404 still leaves audit_logs untouched.
 */
class SpecialtyAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private const AUDITED_FIELDS = ['code', 'name', 'description', 'is_active'];

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }

    private function auditEntries(string $action): \Illuminate\Support\Collection
    {
        return AuditLog::query()
            ->where('action', $action)
            ->where('auditable_type', Specialty::class)
            ->get();
    }

    public function test_store_records_the_actor_and_the_created_values(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/specialties', [
            'code' => 'periodoncia_avanzada',
            'name' => 'Periodoncia avanzada',
            'description' => 'Cirugía de encías y regeneración ósea.',
            'is_active' => true,
        ]);

        $response->assertCreated();

        $specialty = Specialty::query()->where('code', 'periodoncia_avanzada')->firstOrFail();
        $entries = $this->auditEntries('specialty_created');

        $this->assertCount(1, $entries, 'Creating a specialty MUST leave exactly one audit entry.');
        $entry = $entries->first();

        $this->assertSame($admin->id, $entry->user_id, 'The audit entry MUST name the actor.');
        $this->assertSame($specialty->id, $entry->auditable_id);
        $this->assertSame([], $entry->old_values, 'A creation has no previous values.');

        $this->assertSame('periodoncia_avanzada', $entry->new_values['code'] ?? null);
        $this->assertSame('Periodoncia avanzada', $entry->new_values['name'] ?? null);
        $this->assertSame(
            'Cirugía de encías y regeneración ósea.',
            $entry->new_values['description'] ?? null
        );
        $this->assertTrue($entry->new_values['is_active'] ?? null);
    }

    public function test_update_records_the_previous_and_new_values_including_the_status_toggle(): void
    {
        $admin = $this->admin();
        $specialty = Specialty::create([
            'code' => 'general',
            'name' => 'Odontología general',
            'description' => 'Atención integral, preventiva y diagnóstico general.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/specialties/{$specialty->id}", [
            'name' => 'Odontología integral',
            'is_active' => false,
        ]);

        $response->assertOk();

        $entries = $this->auditEntries('specialty_updated');
        $this->assertCount(1, $entries, 'Updating a specialty MUST leave exactly one audit entry.');
        $entry = $entries->first();

        $this->assertSame($admin->id, $entry->user_id);
        $this->assertSame($specialty->id, $entry->auditable_id);

        foreach (self::AUDITED_FIELDS as $field) {
            $this->assertArrayHasKey($field, $entry->old_values, "old_values MUST carry {$field}.");
            $this->assertArrayHasKey($field, $entry->new_values, "new_values MUST carry {$field}.");
        }

        $this->assertSame('Odontología general', $entry->old_values['name']);
        $this->assertSame('Odontología integral', $entry->new_values['name']);
        $this->assertTrue($entry->old_values['is_active']);
        $this->assertFalse($entry->new_values['is_active']);

        $changes = $entry->getChanges();
        $this->assertArrayHasKey('name', $changes);
        $this->assertArrayHasKey('is_active', $changes);
        $this->assertArrayNotHasKey('code', $changes, 'The code is immutable on edit.');
    }

    public function test_rejected_input_and_unknown_rows_leave_audit_logs_untouched(): void
    {
        $admin = $this->admin();
        $specialty = Specialty::create([
            'code' => 'estetica',
            'name' => 'Estética dental',
            'description' => null,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/specialties', ['code' => 'estetica', 'name' => 'Duplicada'])
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/specialties/999999', ['name' => 'Fantasma'])
            ->assertNotFound();

        $this->assertSame(
            0,
            AuditLog::query()->where('auditable_type', Specialty::class)->count(),
            'Rejected input MUST NOT leave an audit entry behind.'
        );
    }

    public function test_the_catalog_response_contract_is_unchanged(): void
    {
        $admin = $this->admin();
        $specialty = Specialty::create([
            'code' => 'ortodoncia',
            'name' => 'Ortodoncia',
            'description' => null,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/specialties/{$specialty->id}", [
                'code' => 'codigo_ignorado',
                'name' => 'Ortodoncia clínica',
            ])
            ->assertOk()
            ->assertJsonPath('data.code', 'ortodoncia')
            ->assertJsonPath('data.name', 'Ortodoncia clínica')
            ->assertJsonPath('meta.message', 'Especialidad actualizada exitosamente');
    }
}
