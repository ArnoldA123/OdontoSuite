<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #283 S3 — «Un usuario restringido a sede no lee ni escribe datos de otra
 * sede», and #290 S9 — the patient file stays shared across sedes.
 *
 * Reads are scoped at route-model binding and at the query builder, so another
 * sede's operational record is unreachable (404) while the patient's clinical
 * file keeps answering from any sede.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class BranchScopeReadGuardTest extends TestCase
{
    use RefreshDatabase;

    private function user(?Branch $branch, string $role = 'recepcionista'): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ]);
    }

    private function appointmentIn(Branch $branch): Appointment
    {
        return Appointment::factory()->create(['branch_id' => $branch->id]);
    }

    public function test_the_appointment_index_lists_only_the_assigned_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mine = $this->appointmentIn($own);
        $theirs = $this->appointmentIn($other);

        $response = $this->actingAs($this->user($own), 'sanctum')
            ->getJson('/api/appointments?start_date='
                .now()->subDays(2)->toDateString().'&end_date='
                .now()->addDays(5)->toDateString());

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_naming_a_foreign_branch_in_the_index_is_forbidden(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();

        $this->actingAs($this->user($own), 'sanctum')
            ->getJson("/api/appointments?branch_id={$other->id}")
            ->assertForbidden();
    }

    public function test_another_branch_appointment_is_not_reachable_by_id(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $theirs = $this->appointmentIn($other);

        $this->actingAs($this->user($own), 'sanctum')
            ->getJson("/api/appointments/{$theirs->id}")
            ->assertNotFound();
    }

    public function test_the_own_appointment_is_reachable_by_id(): void
    {
        $own = Branch::factory()->create();
        $mine = $this->appointmentIn($own);

        $this->actingAs($this->user($own), 'sanctum')
            ->getJson("/api/appointments/{$mine->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id);
    }

    public function test_an_other_branch_cash_session_is_not_reachable(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own, 'administrador');
        $session = CashRegisterSession::factory()->create([
            'user_id' => $this->user($other, 'finanzas')->id,
            'branch_id' => $other->id,
            'status' => 'open',
        ]);

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/cash-register-sessions/{$session->id}")
            ->assertNotFound();
    }

    public function test_the_branch_list_answers_only_the_assigned_branch(): void
    {
        $own = Branch::factory()->create(['is_active' => true]);
        $other = Branch::factory()->create(['is_active' => true]);
        $actor = $this->user($own);

        $response = $this->actingAs($actor, 'sanctum')->getJson('/api/branches/active');

        $response->assertOk();
        $this->assertSame([$own->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_group_scope_still_reads_every_branch(): void
    {
        $own = Branch::factory()->create(['is_active' => true]);
        $other = Branch::factory()->create(['is_active' => true]);
        $actor = $this->user(null, 'administrador');

        $response = $this->actingAs($actor, 'sanctum')->getJson('/api/branches/active');

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$own->id, $other->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    public function test_the_patient_file_stays_readable_across_branches(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $other->id]);

        $this->actingAs($this->user($own), 'sanctum')
            ->getJson("/api/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $patient->id);
    }
}
