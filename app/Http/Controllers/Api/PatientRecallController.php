<?php

namespace App\Http\Controllers\Api;

use App\Authorization\BranchAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\SchedulePatientRecallRequest;
use App\Http\Requests\StorePatientRecallRequest;
use App\Models\PatientRecall;
use App\Services\RecallService;
use App\Support\PerPage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * F4 (#284, S1/S4) — the recall surface.
 *
 * The controller only applies the read filters, resolves the branch the actor
 * may see and delegates the domain to `RecallService`: creation, scheduling
 * through the agenda door, closing with an outcome and the two reads reception
 * needs (queue and compliance metric).
 */
class PatientRecallController extends Controller
{
    private const STATUSES = [
        PatientRecall::STATUS_PENDING,
        PatientRecall::STATUS_SCHEDULED,
        PatientRecall::STATUS_COMPLETED,
        PatientRecall::STATUS_CANCELLED,
    ];

    public function __construct(private readonly RecallService $recalls) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', Rule::in(self::STATUSES)],
            'due' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'nullable', 'numeric'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        try {
            $branchId = BranchAccess::effectiveFilter(
                $request->user(),
                $request->filled('branch_id') ? (int) $request->input('branch_id') : null,
            );

            $page = PatientRecall::query()
                ->with(['patient', 'scheduledAppointment', 'createdBy'])
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->when($request->filled('patient_id'), fn ($query) => $query->where('patient_id', (int) $request->input('patient_id')))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
                ->when($request->boolean('due'), fn ($query) => $query->due())
                ->orderBy('due_date')
                ->orderBy('id')
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
            Log::error('PatientRecallController@index: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al listar los recalls'], 500);
        }
    }

    public function show(PatientRecall $recall): JsonResponse
    {
        try {
            $recall->load(['patient', 'scheduledAppointment', 'createdBy']);

            return response()->json(['data' => $recall]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PatientRecallController@show: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al obtener el recall'], 500);
        }
    }

    public function store(StorePatientRecallRequest $request): JsonResponse
    {
        try {
            $recall = $this->recalls->create($request->validated(), $request->user());

            return response()->json([
                'data' => $recall->load('patient'),
                'meta' => ['message' => 'Recall registrado exitosamente'],
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PatientRecallController@store: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al registrar el recall'], 500);
        }
    }

    public function schedule(SchedulePatientRecallRequest $request, PatientRecall $recall): JsonResponse
    {
        try {
            $result = $this->recalls->schedule($recall, $request->validated(), $request->user());

            return response()->json([
                'data' => [
                    'recall' => $result['recall']->load('patient'),
                    'appointment' => $result['appointment'],
                ],
                'meta' => ['message' => 'Cita de control agendada exitosamente'],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PatientRecallController@schedule: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al agendar la cita de control'], 500);
        }
    }

    public function complete(Request $request, PatientRecall $recall): JsonResponse
    {
        try {
            $recall = $this->recalls->complete($recall, $request->user());

            return response()->json([
                'data' => $recall->load('patient'),
                'meta' => ['message' => 'Recall marcado como completado'],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PatientRecallController@complete: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al completar el recall'], 500);
        }
    }

    public function cancel(Request $request, PatientRecall $recall): JsonResponse
    {
        try {
            $recall = $this->recalls->cancel($recall, $request->user());

            return response()->json([
                'data' => $recall->load('patient'),
                'meta' => ['message' => 'Recall cancelado exitosamente'],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PatientRecallController@cancel: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al cancelar el recall'], 500);
        }
    }

    public function metrics(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        try {
            $branchId = BranchAccess::effectiveFilter(
                $request->user(),
                $request->filled('branch_id') ? (int) $request->input('branch_id') : null,
            );

            return response()->json([
                'data' => $this->recalls->metrics(
                    $branchId,
                    $request->filled('from') ? (string) $request->input('from') : null,
                    $request->filled('to') ? (string) $request->input('to') : null,
                ),
            ]);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('PatientRecallController@metrics: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al obtener las métricas de recalls'], 500);
        }
    }
}
