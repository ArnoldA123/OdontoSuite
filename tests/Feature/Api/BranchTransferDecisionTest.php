<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchTransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * #305 (S3/S4/S8/S9/S10) — the decision of a transfer.
 *
 * Approving is the only write of `users.branch_id` outside the administrative
 * edit and it happens in the same transaction as the state change (S3): the
 * request closes with who and when, the affiliation moves, and exactly one
 * `user_branch_assigned` audit row keeps the old and the new sede. A failure
 * afterwards rolls everything back. Rejecting records the decision and leaves
 * the affiliation untouched (S9). Nobody decides their own request (S4) and a
 * sede administrator decides nothing (D6).
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class BranchTransferDecisionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $attributes
     */
    private function staff(?Branch $branch, string $role = 'odontologo', array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branch?->id,
        ], $attributes));
    }

    private function transfer(User $requester, ?Branch $from, Branch $to): BranchTransferRequest
    {
        return BranchTransferRequest::factory()->create([
            'user_id' => $requester->id,
            'from_branch_id' => $from?->id,
            'to_branch_id' => $to->id,
        ]);
    }

    #[Test]
    public function approving_moves_the_affiliation_and_audits_the_change(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $manager = $this->staff(null, 'administrador');
        $professional = $this->staff($origin);
        $request = $this->transfer($professional, $origin, $destination);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/approve", [
                'decision_notes' => 'Aprobado por necesidad de servicio.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', BranchTransferRequest::STATUS_APPROVED)
            ->assertJsonPath('data.decided_by', $manager->id)
            ->assertJsonPath('meta.message', 'Solicitud de traslado aprobada');

        $request->refresh();

        $this->assertSame(BranchTransferRequest::STATUS_APPROVED, $request->status);
        $this->assertNotNull($request->decided_at);
        $this->assertSame($manager->id, (int) $request->decided_by);
        $this->assertSame('Aprobado por necesidad de servicio.', $request->decision_notes);

        $this->assertSame($destination->id, (int) $professional->fresh()->branch_id);

        $audit = AuditLog::query()
            ->where('action', 'user_branch_assigned')
            ->where('auditable_id', $professional->id)
            ->sole();

        $this->assertSame($origin->id, $audit->old_values['branch_id']);
        $this->assertSame($destination->id, $audit->new_values['branch_id']);
        $this->assertSame('branch_transfer_approved', $audit->metadata['reason']);
        $this->assertSame($request->id, $audit->metadata['branch_transfer_request_id']);
    }

    #[Test]
    public function approving_a_group_scope_request_restricts_the_user(): void
    {
        $destination = Branch::factory()->create();
        $manager = $this->staff(null, 'administrador');
        $professional = $this->staff(null);
        $request = $this->transfer($professional, null, $destination);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', BranchTransferRequest::STATUS_APPROVED);

        $this->assertSame($destination->id, (int) $professional->fresh()->branch_id);

        $audit = AuditLog::query()
            ->where('action', 'user_branch_assigned')
            ->where('auditable_id', $professional->id)
            ->sole();

        $this->assertNull($audit->old_values['branch_id']);
        $this->assertSame($destination->id, $audit->new_values['branch_id']);
    }

    #[Test]
    public function a_failure_after_the_decision_leaves_the_branch_unchanged(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $manager = $this->staff(null, 'administrador');
        $professional = $this->staff($origin);
        $request = $this->transfer($professional, $origin, $destination);

        // Self-limiting on purpose: the hook stays registered for the rest of
        // the test, and only the first audit write must fail.
        $thrown = false;
        AuditLog::creating(function (AuditLog $log) use (&$thrown): void {
            if ($thrown) {
                return;
            }

            $thrown = true;

            throw new RuntimeException('Audit write failed after the decision.');
        });

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/approve")
            ->assertStatus(500);

        $this->assertSame($origin->id, (int) $professional->fresh()->branch_id);
        $this->assertSame(BranchTransferRequest::STATUS_PENDING, $request->fresh()->status);
        $this->assertSame(0, AuditLog::query()->where('action', 'user_branch_assigned')->count());
    }

    #[Test]
    public function rejecting_leaves_the_affiliation_intact_and_records_the_decision(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $manager = $this->staff(null, 'administrador');
        $professional = $this->staff($origin);
        $request = $this->transfer($professional, $origin, $destination);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/reject", [
                'decision_notes' => 'No hay plaza disponible en esa sede.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', BranchTransferRequest::STATUS_REJECTED)
            ->assertJsonPath('data.decided_by', $manager->id)
            ->assertJsonPath('meta.message', 'Solicitud de traslado rechazada');

        $request->refresh();

        $this->assertSame(BranchTransferRequest::STATUS_REJECTED, $request->status);
        $this->assertNotNull($request->decided_at);
        $this->assertSame($manager->id, (int) $request->decided_by);
        $this->assertSame('No hay plaza disponible en esa sede.', $request->decision_notes);

        // S9: rejecting writes no affiliation.
        $this->assertSame($origin->id, (int) $professional->fresh()->branch_id);
        $this->assertSame(0, AuditLog::query()->where('action', 'user_branch_assigned')->count());
    }

    #[Test]
    public function a_group_scope_administrator_cannot_decide_their_own_request(): void
    {
        $destination = Branch::factory()->create();
        $manager = $this->staff(null, 'administrador');
        $request = $this->transfer($manager, null, $destination);

        // The 403 body is the platform envelope (bootstrap/app.php answers a
        // generic message for AccessDeniedHttpException); what this door owes
        // is the refusal itself.
        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/approve")
            ->assertForbidden();

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/reject")
            ->assertForbidden();

        $request->refresh();

        $this->assertSame(BranchTransferRequest::STATUS_PENDING, $request->status);
        $this->assertNull($request->decided_by);
        $this->assertNull($manager->fresh()->branch_id);
    }

    #[Test]
    public function a_sede_administrator_cannot_decide(): void
    {
        $sede = Branch::factory()->create();
        $elsewhere = Branch::factory()->create();
        $sedeAdministrator = $this->staff($sede, 'administrador');
        $professional = $this->staff($elsewhere);
        $request = $this->transfer($professional, $elsewhere, $sede);

        $this->actingAs($sedeAdministrator, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/approve")
            ->assertForbidden();

        $this->actingAs($sedeAdministrator, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/reject")
            ->assertForbidden();

        $request->refresh();

        $this->assertSame(BranchTransferRequest::STATUS_PENDING, $request->status);
        $this->assertNull($request->decided_by);
        $this->assertSame($elsewhere->id, (int) $professional->fresh()->branch_id);
    }

    #[Test]
    public function approving_a_request_whose_origin_no_longer_matches_is_rejected(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $third = Branch::factory()->create();
        $manager = $this->staff(null, 'administrador');
        $professional = $this->staff($origin);
        $request = $this->transfer($professional, $origin, $destination);

        // The administrative edit moved the affiliation after the request.
        $professional->update(['branch_id' => $third->id]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/approve")
            ->assertStatus(409)
            ->assertJsonPath('message', 'La sede de origen de la solicitud ya no coincide con su afiliación vigente.');

        $request->refresh();

        $this->assertSame(BranchTransferRequest::STATUS_PENDING, $request->status);
        $this->assertSame($third->id, (int) $professional->fresh()->branch_id);
        $this->assertSame(0, AuditLog::query()->where('action', 'user_branch_assigned')->count());
    }

    #[Test]
    public function approving_a_request_whose_destination_was_deactivated_is_rejected(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $manager = $this->staff(null, 'administrador');
        $professional = $this->staff($origin);
        $request = $this->transfer($professional, $origin, $destination);

        $destination->update(['is_active' => false]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/approve")
            ->assertStatus(409)
            ->assertJsonPath('message', 'La sede de destino ya no está activa.');

        $request->refresh();

        $this->assertSame(BranchTransferRequest::STATUS_PENDING, $request->status);
        $this->assertSame($origin->id, (int) $professional->fresh()->branch_id);
    }

    #[Test]
    public function deciding_an_already_decided_request_is_rejected(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $manager = $this->staff(null, 'administrador');
        $professional = $this->staff($origin);
        $approved = $this->transfer($professional, $origin, $destination);
        $approved->update([
            'status' => BranchTransferRequest::STATUS_APPROVED,
            'decided_at' => now(),
            'decided_by' => $manager->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/branch-transfers/{$approved->id}/approve")
            ->assertStatus(409)
            ->assertJsonPath('message', 'La solicitud ya fue resuelta.');

        $rejected = $this->transfer($this->staff($origin), $origin, $destination);
        $rejected->update([
            'status' => BranchTransferRequest::STATUS_REJECTED,
            'decided_at' => now(),
            'decided_by' => $manager->id,
        ]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/branch-transfers/{$rejected->id}/reject")
            ->assertStatus(409)
            ->assertJsonPath('message', 'La solicitud ya fue resuelta.');

        $this->assertSame(BranchTransferRequest::STATUS_APPROVED, $approved->fresh()->status);
        $this->assertSame(BranchTransferRequest::STATUS_REJECTED, $rejected->fresh()->status);
    }

    #[Test]
    public function a_group_scope_administrator_rejects_someone_elses_request(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $manager = $this->staff(null, 'administrador');
        $professional = $this->staff($origin);
        $request = $this->transfer($professional, $origin, $destination);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', BranchTransferRequest::STATUS_REJECTED)
            ->assertJsonPath('meta.message', 'Solicitud de traslado rechazada');

        $this->assertSame($origin->id, (int) $professional->fresh()->branch_id);
    }
}
