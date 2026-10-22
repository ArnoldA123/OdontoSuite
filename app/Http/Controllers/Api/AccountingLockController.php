<?php

namespace App\Http\Controllers\Api;

use App\Authorization\BranchAccess;
use App\Http\Controllers\Controller;
use App\Models\AccountingLock;
use App\Models\User;
use App\Services\AccountingLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F3 (#283, S4/S5) — administration of the accounting freeze date.
 *
 * One row per module and sede; the global row (`branch_id` null) freezes every
 * sede that has no row of its own. A sede administrator only writes their own
 * sede: a body without `branch_id` defaults to it, and an explicit null (the
 * global row) is refused because the group row is group scope.
 *
 * No `try/catch` here on purpose: an authorization failure raised in the body
 * must reach the exception handler as 403, and this API's controllers answer 500
 * for anything they catch themselves.
 */
class AccountingLockController extends Controller
{
    public function __construct(private readonly AccountingLockService $locks) {}

    /**
     * The effective lock of every module for one sede.
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranch($request, $request->user());

        $data = [];

        foreach (AccountingLockService::MODULES as $module) {
            $data[] = $this->present($module, $this->locks->effectiveLock($module, $branchId));
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Freeze a module through `locked_through`.
     */
    public function update(Request $request, string $module): JsonResponse
    {
        $validated = $request->validate([
            'locked_through' => 'required|date',
        ]);

        $branchId = $this->resolveBranch($request, $request->user());

        $lock = $this->locks->setLock(
            $module,
            (string) $validated['locked_through'],
            $branchId,
            $request->user(),
        );

        return response()->json([
            'data' => $this->present($module, $lock),
            'meta' => [
                'message' => 'Bloqueo contable registrado exitosamente',
            ],
        ]);
    }

    /**
     * Remove the lock of a module for one sede.
     */
    public function destroy(Request $request, string $module): JsonResponse
    {
        $branchId = $this->resolveBranch($request, $request->user());

        $removed = $this->locks->clearLock($module, $branchId);

        return response()->json([
            'data' => [
                'module' => $module,
                'branch_id' => $branchId,
                'removed' => $removed,
            ],
            'meta' => [
                'message' => $removed
                    ? 'Bloqueo contable eliminado exitosamente'
                    : 'El módulo no tenía bloqueo contable para esa sede',
            ],
        ]);
    }

    /**
     * Resolve the sede the request operates on.
     *
     * Absent `branch_id` means "my sede" for a restricted administrator and "the
     * group row" for group scope; an explicit null always asks for the group row
     * and therefore requires group scope.
     */
    private function resolveBranch(Request $request, ?User $user): ?int
    {
        $requested = $request->input('branch_id');

        if (! $request->exists('branch_id') || $requested === '') {
            return BranchAccess::effectiveFilter($user, null);
        }

        if ($requested === null) {
            BranchAccess::assertGlobal($user);

            return null;
        }

        return BranchAccess::effectiveFilter($user, (int) $requested);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(string $module, ?AccountingLock $lock): array
    {
        return [
            'module' => $module,
            'locked_through' => $lock?->locked_through?->toDateString(),
            'source' => $lock?->source() ?? AccountingLock::SOURCE_NONE,
            'branch_id' => $lock?->branch_id,
            'set_by' => $lock?->set_by,
            'set_at' => $lock?->updated_at?->toIso8601String(),
        ];
    }
}
