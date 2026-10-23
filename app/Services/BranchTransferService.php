<?php

namespace App\Services;

use App\Exceptions\Transfer\BranchTransferException;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchTransferRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * #305 (S3/S4/S8/S9) — the branch transfer request.
 *
 * `users.branch_id` is the affiliation and an administrative edit is not the
 * only way to move it any more: the user asks, gerencia decides, and approval
 * is the only write of the affiliation outside `PUT /api/users/{id}` (S3). The
 * decision and the move happen in the same transaction, together with the
 * `user_branch_assigned` audit row, so a failure never leaves the sede half
 * changed (S8).
 *
 * A request is not a second affiliation: while it is `pending` the user keeps
 * operating in the sede they already have (S7), and cancelling it is the
 * requester's own door (D4). Rejecting is gerencia's, and it writes no
 * affiliation (S9).
 */
class BranchTransferService
{
    /**
     * @param array<string, mixed> $data
     */
    public function open(User $requester, array $data): BranchTransferRequest
    {
        return DB::transaction(function () use ($requester, $data): BranchTransferRequest {
            // D2: MySQL has no partial unique index on `status`, so two
            // simultaneous requests of the same user are serialised by locking
            // the user row before counting the open ones.
            $locked = User::query()->whereKey($requester->id)->lockForUpdate()->firstOrFail();

            $open = BranchTransferRequest::query()
                ->pending()
                ->where('user_id', $locked->id)
                ->exists();

            if ($open) {
                throw BranchTransferException::alreadyOpen();
            }

            return BranchTransferRequest::create([
                'user_id' => $locked->id,
                'from_branch_id' => self::branchId($locked->branch_id),
                'to_branch_id' => (int) $data['to_branch_id'],
                'status' => BranchTransferRequest::STATUS_PENDING,
                'reason' => (string) $data['reason'],
                'requested_at' => now(),
            ]);
        });
    }

    /**
     * The requester withdraws their own pending request. Every outcome closes
     * with the same pair — who and when — so the closure never has to be read
     * from `updated_at` (D4).
     */
    public function cancel(BranchTransferRequest $transfer, User $actor): BranchTransferRequest
    {
        return DB::transaction(function () use ($transfer, $actor): BranchTransferRequest {
            $locked = $this->lockRequest($transfer);

            $this->assertVisible($locked, $actor);

            if ((int) $locked->user_id !== (int) $actor->id) {
                throw new AuthorizationException('Solo el solicitante puede cancelar su solicitud de traslado.');
            }

            if (! $locked->isPending()) {
                throw BranchTransferException::alreadyResolved();
            }

            $locked->fill([
                'status' => BranchTransferRequest::STATUS_CANCELLED,
                'decided_at' => now(),
                'decided_by' => $actor->id,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * Approve: the request closes and the affiliation moves, in one
     * transaction with the audit row (S3/S8).
     */
    public function approve(BranchTransferRequest $transfer, User $actor, ?string $decisionNotes = null): BranchTransferRequest
    {
        return DB::transaction(function () use ($transfer, $actor, $decisionNotes): BranchTransferRequest {
            [$locked, $requester] = $this->lockDecidableRequester($transfer, $actor, true);

            $destination = Branch::query()->whereKey($locked->to_branch_id)->first();

            if ($destination === null || ! $destination->is_active) {
                throw BranchTransferException::destinationInactive();
            }

            $previousBranchId = self::branchId($requester->branch_id);
            $newBranchId = (int) $locked->to_branch_id;

            $locked->fill([
                'status' => BranchTransferRequest::STATUS_APPROVED,
                'decided_at' => now(),
                'decided_by' => $actor->id,
                'decision_notes' => $decisionNotes,
            ])->save();

            $requester->branch_id = $newBranchId;
            $requester->save();

            AuditLog::log(
                $actor,
                'user_branch_assigned',
                $requester,
                ['branch_id' => $previousBranchId],
                ['branch_id' => $newBranchId],
                [
                    'reason' => 'branch_transfer_approved',
                    'branch_transfer_request_id' => $locked->id,
                ],
            );

            return $locked->refresh();
        });
    }

    /**
     * Reject: the decision is recorded and the affiliation is left alone (S9).
     * Rejecting is not the requester's door — withdrawing is `cancel()` — so
     * nobody rejects their own request either (D3).
     */
    public function reject(BranchTransferRequest $transfer, User $actor, ?string $decisionNotes = null): BranchTransferRequest
    {
        return DB::transaction(function () use ($transfer, $actor, $decisionNotes): BranchTransferRequest {
            [$locked] = $this->lockDecidableRequester($transfer, $actor, false);

            $locked->fill([
                'status' => BranchTransferRequest::STATUS_REJECTED,
                'decided_at' => now(),
                'decided_by' => $actor->id,
                'decision_notes' => $decisionNotes,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * The shared rules of the two decision doors, in the order the issue names
     * them: reach, self-decision, state and origin.
     *
     * The row is re-read with a write lock inside the transaction, so the state
     * checked is the state committed against: a decision that commits while
     * this one waits is not overridden (the requester row is locked after the
     * request row, which is the one lock order every door follows).
     *
     * @return array{0: BranchTransferRequest, 1: User}
     */
    private function lockDecidableRequester(BranchTransferRequest $transfer, User $actor, bool $approving): array
    {
        $locked = $this->lockRequest($transfer);

        $this->assertVisible($locked, $actor);

        if ((int) $locked->user_id === (int) $actor->id) {
            throw new AuthorizationException(
                $approving
                    ? 'No puede aprobar su propia solicitud de traslado.'
                    : 'No puede decidir su propia solicitud de traslado. Para retirarla, cancélela.',
            );
        }

        if (! $locked->isPending()) {
            throw BranchTransferException::alreadyResolved();
        }

        $requester = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();

        if (self::branchId($requester->branch_id) !== self::branchId($locked->from_branch_id)) {
            throw BranchTransferException::originMismatch();
        }

        return [$locked, $requester];
    }

    /**
     * Re-read the request inside the transaction. Every write door takes this
     * lock before the requester's, so concurrent decisions serialise instead of
     * racing the state check.
     */
    private function lockRequest(BranchTransferRequest $transfer): BranchTransferRequest
    {
        return BranchTransferRequest::query()
            ->whereKey($transfer->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * A row outside the actor's reach is an operation on a foreign sede, which
     * answers 403 and not the 404 a read would get (D5/S10).
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function assertVisible(BranchTransferRequest $transfer, User $actor): void
    {
        $visible = BranchTransferRequest::query()
            ->visibleTo($actor)
            ->whereKey($transfer->getKey())
            ->exists();

        if (! $visible) {
            throw new AuthorizationException('No puede operar sobre una solicitud de otra sede.');
        }
    }

    private static function branchId(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
