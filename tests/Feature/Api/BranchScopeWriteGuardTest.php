<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * #283 S2/S3 and #290 S6 — the sede authorizes writes, it does not only filter
 * reads. The guard lives on the model (App\Models\Concerns\ScopesToBranch), so
 * every write path is covered no matter which controller reaches it.
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class BranchScopeWriteGuardTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function appointmentPayload(Branch $branch, ?Branch $payloadBranch = null): array
    {
        return [
            'patient_id' => Patient::factory()->create(['branch_id' => $branch->id])->id,
            'user_id' => User::factory()->create(['role' => 'odontologo', 'is_active' => true])->id,
            'dental_chair_id' => DentalChair::factory()->create(['branch_id' => $branch->id])->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'branch_id' => $payloadBranch?->id,
            'scheduled_at' => Carbon::now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'duration_minutes' => 30,
        ];
    }

    public function test_a_restricted_user_cannot_create_an_appointment_in_another_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/appointments', $this->appointmentPayload($own, $other))
            ->assertForbidden();

        $this->assertSame(0, Appointment::count());
    }

    public function test_a_restricted_user_appointment_without_branch_is_stamped_with_their_own(): void
    {
        $own = Branch::factory()->create();
        $actor = $this->user($own);

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/appointments', $this->appointmentPayload($own));

        $response->assertCreated();
        $this->assertSame(
            $own->id,
            Appointment::findOrFail($response->json('data.id'))->branch_id,
        );
    }

    public function test_an_unassigned_user_keeps_the_requested_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user(null, 'administrador');

        $response = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/appointments', $this->appointmentPayload($own, $other));

        $response->assertCreated();
        $this->assertSame($other->id, Appointment::findOrFail($response->json('data.id'))->branch_id);
    }

    public function test_a_restricted_admin_cannot_move_a_user_out_of_their_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own, 'administrador');
        $target = User::factory()->create(['branch_id' => $own->id, 'is_active' => true, 'role' => 'odontologo']);

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'username' => $target->username,
                'role' => $target->role,
                'branch_id' => $other->id,
            ])
            ->assertForbidden();

        $this->assertSame($own->id, $target->fresh()->branch_id);
    }

    public function test_a_restricted_admin_cannot_reach_a_user_of_another_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $actor = $this->user($own, 'administrador');
        $target = User::factory()->create(['branch_id' => $other->id, 'is_active' => true, 'role' => 'odontologo']);

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/users/{$target->id}")
            ->assertNotFound();
    }
}
