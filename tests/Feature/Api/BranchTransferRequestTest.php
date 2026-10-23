<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\BranchTransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * #305 (S5/S7/S10) — asking for a transfer, cancelling it and the reach of the
 * reads.
 *
 * Asking is self-service for every role, and while the request is `pending`
 * nothing moves: the affiliation stays where it was and no answer widens (S7).
 * The two sedes the row mentions decide who reads it — the requester, the sede
 * administrator whose sede enters or leaves, and gerencia (S5) — and crossing
 * that line answers 404 on a read and 403 on an operation (S10).
 *
 * Must run against the MySQL harness (AGENTS.md §6).
 */
class BranchTransferRequestTest extends TestCase
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

    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(Branch $destination, array $overrides = []): array
    {
        return array_merge([
            'to_branch_id' => $destination->id,
            'reason' => 'Me mudé cerca de la sede de destino.',
        ], $overrides);
    }

    private function transfer(User $requester, ?Branch $from, Branch $to, string $status = BranchTransferRequest::STATUS_PENDING): BranchTransferRequest
    {
        return BranchTransferRequest::factory()->create([
            'user_id' => $requester->id,
            'from_branch_id' => $from?->id,
            'to_branch_id' => $to->id,
            'status' => $status,
        ]);
    }

    #[Test]
    public function a_user_requests_a_transfer_and_keeps_their_branch(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $professional = $this->staff($origin);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/branch-transfers', $this->payload($destination))
            ->assertCreated()
            ->assertJsonPath('data.status', BranchTransferRequest::STATUS_PENDING)
            ->assertJsonPath('data.from_branch_id', $origin->id)
            ->assertJsonPath('data.to_branch_id', $destination->id)
            ->assertJsonPath('meta.message', 'Solicitud de traslado registrada exitosamente');

        $request = BranchTransferRequest::query()->sole();

        $this->assertSame($professional->id, (int) $request->user_id);
        $this->assertSame($origin->id, (int) $request->from_branch_id);
        $this->assertSame($destination->id, (int) $request->to_branch_id);
        $this->assertSame(BranchTransferRequest::STATUS_PENDING, $request->status);
        $this->assertNotNull($request->requested_at);
        $this->assertNull($request->decided_at);
        $this->assertNull($request->decided_by);

        // S7: asking never moves anybody.
        $this->assertSame($origin->id, (int) $professional->fresh()->branch_id);
    }

    #[Test]
    public function a_user_with_group_scope_requests_from_null(): void
    {
        $destination = Branch::factory()->create();
        $manager = $this->staff(null, 'administrador');

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/branch-transfers', $this->payload($destination))
            ->assertCreated()
            ->assertJsonPath('data.from_branch_id', null);

        $this->assertNull(BranchTransferRequest::query()->sole()->from_branch_id);
        $this->assertNull($manager->fresh()->branch_id);
    }

    #[Test]
    public function a_pending_request_does_not_widen_the_branch_scope(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $administrator = $this->staff($origin, 'administrador');
        $peer = $this->staff($origin);
        $elsewhere = $this->staff($destination);

        $this->actingAs($administrator, 'sanctum')
            ->postJson('/api/branch-transfers', $this->payload($destination))
            ->assertCreated();

        $users = $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/users')
            ->assertOk()
            ->json('data');

        $ids = array_map(static fn (array $user): int => (int) $user['id'], $users);

        $this->assertContains($administrator->id, $ids);
        $this->assertContains($peer->id, $ids);
        $this->assertNotContains($elsewhere->id, $ids);
    }

    #[Test]
    public function a_second_open_request_is_rejected(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $alternative = Branch::factory()->create();
        $professional = $this->staff($origin);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/branch-transfers', $this->payload($destination))
            ->assertCreated();

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/branch-transfers', $this->payload($alternative))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ya tiene una solicitud de traslado en curso.');

        $this->assertSame(1, BranchTransferRequest::query()->pending()->count());
        $this->assertSame($destination->id, (int) BranchTransferRequest::query()->sole()->to_branch_id);
    }

    #[Test]
    public function the_destination_branch_must_be_active(): void
    {
        $origin = Branch::factory()->create();
        $inactive = Branch::factory()->create(['is_active' => false]);
        $professional = $this->staff($origin);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/branch-transfers', $this->payload($inactive))
            ->assertStatus(422)
            ->assertJsonValidationErrors('to_branch_id');

        $this->assertSame(0, BranchTransferRequest::query()->count());
    }

    #[Test]
    public function the_destination_branch_must_differ_from_the_current_one(): void
    {
        $origin = Branch::factory()->create();
        $professional = $this->staff($origin);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/branch-transfers', $this->payload($origin))
            ->assertStatus(422)
            ->assertJsonValidationErrors('to_branch_id');

        $this->assertSame(0, BranchTransferRequest::query()->count());
    }

    #[Test]
    public function the_requester_cancels_their_pending_request(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $professional = $this->staff($origin);
        $request = $this->transfer($professional, $origin, $destination);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', BranchTransferRequest::STATUS_CANCELLED)
            ->assertJsonPath('data.decided_by', $professional->id)
            ->assertJsonPath('meta.message', 'Solicitud de traslado cancelada');

        $request->refresh();

        $this->assertSame(BranchTransferRequest::STATUS_CANCELLED, $request->status);
        $this->assertNotNull($request->decided_at);
        $this->assertSame($professional->id, (int) $request->decided_by);
        $this->assertSame($origin->id, (int) $professional->fresh()->branch_id);
    }

    #[Test]
    public function another_user_cannot_cancel_someone_elses_request(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $owner = $this->staff($origin);
        $intruder = $this->staff($origin);
        $request = $this->transfer($owner, $origin, $destination);

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/cancel")
            ->assertForbidden();

        $request->refresh();

        $this->assertSame(BranchTransferRequest::STATUS_PENDING, $request->status);
        $this->assertNull($request->decided_by);
    }

    #[Test]
    public function cancelling_a_resolved_request_is_rejected(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $professional = $this->staff($origin);
        $request = $this->transfer($professional, $origin, $destination, BranchTransferRequest::STATUS_APPROVED);

        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('message', 'La solicitud ya fue resuelta.');

        $this->assertSame(BranchTransferRequest::STATUS_APPROVED, $request->fresh()->status);
    }

    #[Test]
    public function the_index_shows_only_their_own_requests_to_a_plain_user(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $professional = $this->staff($origin);
        $peer = $this->staff($origin);

        $own = $this->transfer($professional, $origin, $destination);
        $this->transfer($peer, $origin, $destination);

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/branch-transfers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.to_branch.id', $destination->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure([
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    #[Test]
    public function a_sede_administrator_sees_incoming_and_outgoing_requests(): void
    {
        $sede = Branch::factory()->create();
        $elsewhere = Branch::factory()->create();
        $third = Branch::factory()->create();

        $administrator = $this->staff($sede, 'administrador');
        $arriving = $this->staff($elsewhere);
        $leaving = $this->staff($sede);
        $unrelated = $this->staff($third);

        $incoming = $this->transfer($arriving, $elsewhere, $sede);
        $outgoing = $this->transfer($leaving, $sede, $elsewhere);
        $own = $this->transfer($administrator, $sede, $third);
        $foreign = $this->transfer($unrelated, $third, $elsewhere);

        $ids = array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->actingAs($administrator, 'sanctum')
                ->getJson('/api/branch-transfers')
                ->assertOk()
                ->json('data'),
        );

        sort($ids);
        $expected = [$incoming->id, $outgoing->id, $own->id];
        sort($expected);

        $this->assertSame($expected, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    #[Test]
    public function a_group_scope_administrator_sees_every_request(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $manager = $this->staff(null, 'administrador');

        $this->transfer($this->staff($origin), $origin, $destination);
        $this->transfer($this->staff($destination), $destination, $origin);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/branch-transfers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    public function a_group_scope_user_without_the_decision_grant_sees_only_their_own(): void
    {
        $destination = Branch::factory()->create();
        $somewhere = Branch::factory()->create();
        $finance = $this->staff(null, 'finanzas');
        $other = $this->staff($somewhere);

        $own = $this->transfer($finance, null, $destination);
        $this->transfer($other, $somewhere, $destination);

        $this->actingAs($finance, 'sanctum')
            ->getJson('/api/branch-transfers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('meta.total', 1);
    }

    #[Test]
    public function reading_a_foreign_request_is_not_found(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $owner = $this->staff($origin);
        $stranger = $this->staff(Branch::factory()->create());
        $request = $this->transfer($owner, $origin, $destination);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/branch-transfers/{$request->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $request->id)
            ->assertJsonPath('data.status', BranchTransferRequest::STATUS_PENDING);

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/branch-transfers/{$request->id}")
            ->assertNotFound();

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/branch-transfers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function operating_on_a_foreign_request_is_forbidden(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $owner = $this->staff($origin);
        $stranger = $this->staff(Branch::factory()->create());
        $request = $this->transfer($owner, $origin, $destination);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/branch-transfers/{$request->id}/cancel")
            ->assertForbidden();

        $this->assertSame(BranchTransferRequest::STATUS_PENDING, $request->fresh()->status);
    }

    #[Test]
    public function the_index_filters_by_status(): void
    {
        $origin = Branch::factory()->create();
        $destination = Branch::factory()->create();
        $professional = $this->staff($origin);

        $this->transfer($professional, $origin, $destination, BranchTransferRequest::STATUS_CANCELLED);
        $pending = $this->transfer($professional, $origin, $destination);

        $this->actingAs($professional, 'sanctum')
            ->getJson('/api/branch-transfers?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id);
    }
}
