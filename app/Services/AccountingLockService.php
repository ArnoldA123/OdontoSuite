<?php

namespace App\Services;

use App\Authorization\Permissions;
use App\Exceptions\AccountingLockBlockedException;
use App\Models\AccountingLock;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * F3 (#283, S4/S5) — the accounting freeze date, per module and sede.
 *
 * The lock protects the mutation of a historical row: voiding, editing or
 * deleting money whose accounting date is at or before `locked_through`. Money
 * created today is never locked, because the guard is only reached from a
 * mutation of an existing row.
 *
 * Resolution order is branch row, then global row (`branch_id` null), then no
 * lock; a sede that freezes earlier than the group keeps its own date. The two
 * outcomes are recorded in `audit_logs` and they are written BEFORE the caller
 * opens its own transaction: the rejected write never leaves a trail that rolls
 * back with it.
 *
 *   - `accounting_lock_blocked`: no registered authorization, answered as 403.
 *   - `accounting_lock_overridden`: the actor holds `override.accounting_lock`
 *     and states a reason of at least REASON_MIN_LENGTH characters. Stating it
 *     is what requests the override: an attempt that sends a reason shorter than
 *     that is answered 422, while an attempt that sends none is a plain
 *     mutation and falls into the blocked branch.
 */
class AccountingLockService
{
    public const CASH_TRANSACTIONS = 'cash_transactions';

    public const CASH_MOVEMENTS = 'cash_movements';

    public const QUOTATIONS = 'quotations';

    public const TREATMENT_PLANS = 'treatment_plans';

    /** @var list<string> */
    public const MODULES = [
        self::CASH_TRANSACTIONS,
        self::CASH_MOVEMENTS,
        self::QUOTATIONS,
        self::TREATMENT_PLANS,
    ];

    public const ACTION_BLOCKED = 'accounting_lock_blocked';

    public const ACTION_OVERRIDDEN = 'accounting_lock_overridden';

    /** An override needs a reason the audit trail can justify. */
    public const REASON_MIN_LENGTH = 10;

    /**
     * The lock that applies to a sede: its own row first, then the global one.
     */
    public function effectiveLock(string $module, ?int $branchId): ?AccountingLock
    {
        $this->assertKnownModule($module);

        if ($branchId !== null) {
            $branchLock = AccountingLock::query()
                ->where('module', $module)
                ->where('branch_id', $branchId)
                ->first();

            if ($branchLock !== null) {
                return $branchLock;
            }
        }

        return AccountingLock::query()
            ->where('module', $module)
            ->whereNull('branch_id')
            ->first();
    }

    /**
     * Freeze a module through `$date` for one sede, or for the whole group when
     * `$branchId` is null. updateOrCreate keeps a single row per (module,
     * branch), including the global row whose null branch MySQL would not
     * deduplicate with a unique index.
     */
    public function setLock(string $module, string $date, ?int $branchId, ?User $actor): AccountingLock
    {
        $this->assertKnownModule($module);

        return AccountingLock::updateOrCreate(
            ['module' => $module, 'branch_id' => $branchId],
            ['locked_through' => $date, 'set_by' => $actor?->id],
        );
    }

    /**
     * Remove the lock of that (module, branch). Returns whether a row existed.
     */
    public function clearLock(string $module, ?int $branchId): bool
    {
        $this->assertKnownModule($module);

        $query = AccountingLock::query()
            ->where('module', $module)
            ->where('branch_id', $branchId);

        return $query->delete() > 0;
    }

    /**
     * Refuse the mutation of a row whose accounting date is inside the lock.
     *
     * Must be called before the caller opens its transaction: the blocked
     * audit entry is evidence of the attempt, not part of the rejected write.
     *
     * @throws ValidationException            when an attempted override states a reason shorter than the minimum
     * @throws AccountingLockBlockedException when the actor cannot override the lock
     */
    public function guard(
        string $module,
        CarbonInterface $accountingDate,
        ?int $branchId,
        Model $auditable,
        string $action,
        ?string $overrideReason = null
    ): void {
        $lock = $this->effectiveLock($module, $branchId);

        if ($lock === null) {
            return;
        }

        $lockedThrough = $lock->locked_through->toDateString();

        if ($accountingDate->toDateString() > $lockedThrough) {
            return;
        }

        $actor = Auth::user();
        $context = [
            'module' => $module,
            'accounting_date' => $accountingDate->toDateString(),
            'locked_through' => $lockedThrough,
            'action' => $action,
        ];

        if ($actor instanceof User && $actor->hasPermission(Permissions::OVERRIDE_ACCOUNTING_LOCK) && $overrideReason !== null) {
            $reason = trim($overrideReason);

            if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
                throw ValidationException::withMessages([
                    'lock_override_reason' => [sprintf(
                        'Autorizar la operación [%s] sobre el módulo [%s] bloqueado hasta el %s requiere un motivo de al menos %d caracteres.',
                        $action,
                        $module,
                        $lockedThrough,
                        self::REASON_MIN_LENGTH,
                    )],
                ]);
            }

            AuditLog::log($actor, self::ACTION_OVERRIDDEN, $auditable, [], [], [
                'reason' => $reason,
                'module' => $module,
                'accounting_date' => $accountingDate->toDateString(),
                'locked_through' => $lockedThrough,
                'action' => $action,
            ]);

            return;
        }

        if ($actor instanceof User) {
            AuditLog::log($actor, self::ACTION_BLOCKED, $auditable, [], [], $context + [
                'branch_id' => $branchId,
            ]);
        }

        throw new AccountingLockBlockedException(sprintf(
            'El módulo [%s] está bloqueado contablemente hasta el %s: la operación [%s] sobre un registro de esa fecha requiere autorización registrada.',
            $module,
            $lockedThrough,
            $action,
        ));
    }

    private function assertKnownModule(string $module): void
    {
        if (in_array($module, self::MODULES, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'module' => [sprintf(
                'Módulo de bloqueo contable desconocido: [%s]. Válidos: %s.',
                $module,
                implode(', ', self::MODULES),
            )],
        ]);
    }
}
