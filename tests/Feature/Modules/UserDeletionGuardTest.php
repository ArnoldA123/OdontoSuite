<?php

namespace Tests\Feature\Modules;

use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T2 — professionals-functionality-audit (odd/tasks/professionals-functionality-audit.md).
 *
 * Contract for `DELETE /api/users/{id}` (S2 decision: "Bloquear y pedir
 * desactivar", 409 with the related-record counts, no schema change):
 *   - an account with clinical or financial history is never deleted: the
 *     request returns 409 with the counts and every related row survives
 *   - an account without history is deleted (behaviour kept)
 *   - nobody can delete or deactivate their own account
 *
 * Plus the stable ordering the list depends on (S4: "orden estable en el index").
 */
class UserDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'name' => 'Admin Root']);
    }

    public function test_deleting_a_professional_with_history_returns_conflict_and_keeps_every_record(): void
    {
        $admin = $this->admin();
        $professional = User::factory()->create(['role' => 'odontologo', 'name' => 'Wilmer Valderrama']);

        $appointment = Appointment::factory()->create([
            'user_id' => $professional->id,
            'created_by' => $professional->id,
        ]);
        $record = MedicalRecord::factory()->create(['created_by' => $professional->id]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/users/{$professional->id}");

        $response->assertStatus(409);
        $this->assertStringContainsString('Wilmer Valderrama', $response->json('message'));
        $this->assertStringContainsString('1 cita', $response->json('message'));
        $this->assertStringContainsString('1 historia clínica', $response->json('message'));
        $this->assertStringContainsString('Desactívalo en su lugar.', $response->json('message'));

        $this->assertDatabaseHas('users', ['id' => $professional->id]);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
        $this->assertDatabaseHas('medical_records', ['id' => $record->id]);
    }

    public function test_deleting_a_professional_without_history_deletes_the_account(): void
    {
        $admin = $this->admin();
        $professional = User::factory()->create(['role' => 'asistente']);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/users/{$professional->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $professional->id]);
    }

    public function test_an_administrator_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/users/{$admin->id}");

        $response->assertStatus(422);
        $this->assertStringContainsString('tu propia cuenta', $response->json('message'));
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_an_administrator_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$admin->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('tu propia cuenta', $response->json('message'));
        $this->assertTrue((bool) $admin->fresh()->is_active);
    }

    public function test_index_returns_the_team_in_a_stable_name_order(): void
    {
        $admin = $this->admin();

        User::factory()->create(['role' => 'odontologo', 'name' => 'Zulema Rojas']);
        User::factory()->create(['role' => 'odontologo', 'name' => 'Ana Torres']);
        User::factory()->create(['role' => 'odontologo', 'name' => 'Marco Diaz']);

        $names = collect($this->actingAs($admin, 'sanctum')->getJson('/api/users?role=odontologo')->json('data'))
            ->pluck('name')
            ->all();

        $again = collect($this->actingAs($admin, 'sanctum')->getJson('/api/users?role=odontologo')->json('data'))
            ->pluck('name')
            ->all();

        $ana = array_search('Ana Torres', $names, true);
        $marco = array_search('Marco Diaz', $names, true);
        $zulema = array_search('Zulema Rojas', $names, true);

        $this->assertNotFalse($ana);
        $this->assertNotFalse($marco);
        $this->assertNotFalse($zulema);
        $this->assertTrue($ana < $marco && $marco < $zulema, 'The team list must be ordered by name.');
        $this->assertSame($names, $again, 'The team list order must not change between requests.');
    }
}
