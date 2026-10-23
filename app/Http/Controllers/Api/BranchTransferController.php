<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Transfer\BranchTransferException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DecideBranchTransferRequest;
use App\Http\Requests\StoreBranchTransferRequest;
use App\Models\Branch;
use App\Models\BranchTransferRequest;
use App\Services\BranchTransferService;
use App\Support\PerPage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * #305 (S2/S5/S7/S10) — the doors of the branch transfer request.
 *
 * Asking is self-service for every role: the user reads their own request,
 * opens it and withdraws it while it is pending. Reading is decided by the
 * model's `visibleTo()` scope — the requester, the two sedes the row mentions
 * and gerencia — while the operations answer 403 for the same crossing, which
 * is why the row is re-resolved through the scope instead of trusting the
 * binding (D5).
 *
 * The two decision doors are group scope and delegate the whole decision to
 * `BranchTransferService`, which owns the transaction that moves the
 * affiliation and writes the audit row (S3/S8).
 */
class BranchTransferController extends Controller
{
    private const RELATIONS = ['user', 'fromBranch', 'toBranch', 'decider'];

    public function __construct(private readonly BranchTransferService $transfers) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(BranchTransferRequest::STATUSES)],
            'per_page' => ['sometimes', 'nullable', 'numeric'],
        ], [
            'status.in' => 'El estado indicado no existe.',
        ]);

        try {
            $page = BranchTransferRequest::query()
                ->with(self::RELATIONS)
                ->visibleTo($request->user())
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
                ->orderByDesc('requested_at')
                ->orderByDesc('id')
                ->paginate(PerPage::resolve($request->input('per_page')));

            return response()->json([
                'data' => $page->items(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ]);
        } catch (AuthorizationException|ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('BranchTransferController@index: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al listar las solicitudes de traslado'], 500);
        }
    }

    public function show(Request $request, BranchTransferRequest $branchTransfer): JsonResponse
    {
        try {
            $transfer = BranchTransferRequest::query()
                ->with(self::RELATIONS)
                ->visibleTo($request->user())
                ->findOrFail($branchTransfer->getKey());

            return response()->json(['data' => $transfer]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('BranchTransferController@show: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al obtener la solicitud de traslado'], 500);
        }
    }

    /**
     * The catalogue of destinations of the transfer form.
     *
     * `GET /api/branches/active` applies `BranchAccess::effectiveFilter`, so a
     * user affiliated to a sede receives only their own sede — the exact
     * population this feature exists for would open a form with no destination
     * while `POST /api/branch-transfers` would accept the same sede. A user
     * cannot ask for a transfer without knowing which sedes exist, and the
     * name of a sede is not sensitive: this door publishes the active catalog
     * of the group and leaves `BranchController@index` untouched. The sede
     * dimension still authorizes operations, and the store revalidates the
     * destination (active and different from the current one).
     */
    public function destinations(): JsonResponse
    {
        try {
            $branches = Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'code', 'name', 'is_active']);

            return response()->json([
                'data' => $branches,
                'meta' => ['message' => 'Sedes disponibles para el traslado'],
            ]);
        } catch (\Throwable $e) {
            Log::error('BranchTransferController@destinations: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al listar las sedes de destino'], 500);
        }
    }

    public function store(StoreBranchTransferRequest $request): JsonResponse
    {
        try {
            $transfer = $this->transfers->open($request->user(), $request->validated());

            return response()->json([
                'data' => $transfer->load(self::RELATIONS),
                'meta' => ['message' => 'Solicitud de traslado registrada exitosamente'],
            ], 201);
        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (BranchTransferException $e) {
            return $this->refused($e);
        } catch (\Throwable $e) {
            Log::error('BranchTransferController@store: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al registrar la solicitud de traslado'], 500);
        }
    }

    public function cancel(Request $request, BranchTransferRequest $branchTransfer): JsonResponse
    {
        try {
            $transfer = $this->transfers->cancel($branchTransfer, $request->user());

            return response()->json([
                'data' => $transfer->load(self::RELATIONS),
                'meta' => ['message' => 'Solicitud de traslado cancelada'],
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (BranchTransferException $e) {
            return $this->refused($e);
        } catch (\Throwable $e) {
            Log::error('BranchTransferController@cancel: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al cancelar la solicitud de traslado'], 500);
        }
    }

    public function approve(DecideBranchTransferRequest $request, BranchTransferRequest $branchTransfer): JsonResponse
    {
        try {
            $transfer = $this->transfers->approve(
                $branchTransfer,
                $request->user(),
                $request->validated()['decision_notes'] ?? null,
            );

            return response()->json([
                'data' => $transfer->load(self::RELATIONS),
                'meta' => ['message' => 'Solicitud de traslado aprobada'],
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (BranchTransferException $e) {
            return $this->refused($e);
        } catch (\Throwable $e) {
            Log::error('BranchTransferController@approve: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al aprobar la solicitud de traslado'], 500);
        }
    }

    public function reject(DecideBranchTransferRequest $request, BranchTransferRequest $branchTransfer): JsonResponse
    {
        try {
            $transfer = $this->transfers->reject(
                $branchTransfer,
                $request->user(),
                $request->validated()['decision_notes'] ?? null,
            );

            return response()->json([
                'data' => $transfer->load(self::RELATIONS),
                'meta' => ['message' => 'Solicitud de traslado rechazada'],
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (BranchTransferException $e) {
            return $this->refused($e);
        } catch (\Throwable $e) {
            Log::error('BranchTransferController@reject: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al rechazar la solicitud de traslado'], 500);
        }
    }

    /**
     * A domain refusal carries its own status (409); it is a conflict with the
     * stored state, not a server failure, so it is answered without logging.
     */
    private function refused(BranchTransferException $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage()], $e->httpStatus);
    }
}
