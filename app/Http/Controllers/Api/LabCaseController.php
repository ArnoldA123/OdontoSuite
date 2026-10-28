<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLabCaseRequest;
use App\Http\Requests\TransitionLabCaseRequest;
use App\Http\Requests\UpdateLabCaseRequest;
use App\Http\Resources\LabCaseResource;
use App\Models\LabCase;
use App\Services\LabCaseService;
use App\Support\PerPage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * F5 (#285, S1/S2/S3) — the prosthetic laboratory surface.
 *
 * The desk lists and reads the work orders of its scope, reception reads them
 * to answer for the patient, and the phase moves through its own door. The
 * controller applies filters and delegates the domain to `LabCaseService`,
 * which owns the forward-only machine and the ready notification.
 */
class LabCaseController extends Controller
{
    private const RELATIONS = ['patient', 'professional', 'technician', 'dentalPiece', 'createdBy'];

    public function __construct(private readonly LabCaseService $cases) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(LabCase::PHASES)],
            'technician_id' => ['sometimes', 'nullable', 'regex:/^(me|\d+)$/'],
            'professional_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'patient_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'overdue' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'nullable', 'numeric'],
        ], [
            'status.in' => 'El estado indicado no existe.',
            'technician_id.regex' => 'El técnico debe ser un identificador numérico o «me».',
            'professional_id.integer' => 'El profesional debe ser un identificador válido.',
            'patient_id.integer' => 'El paciente debe ser un identificador válido.',
        ]);

        try {
            $technicianId = $this->technicianFilter($request);

            $page = LabCase::query()
                ->with(self::RELATIONS)
                ->visibleToBranch($request->user())
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
                ->when($technicianId !== null, fn ($query) => $query->where('technician_id', $technicianId))
                ->when($request->filled('professional_id'), fn ($query) => $query->where('professional_id', (int) $request->input('professional_id')))
                ->when($request->filled('patient_id'), fn ($query) => $query->where('patient_id', (int) $request->input('patient_id')))
                ->when($request->boolean('overdue'), fn ($query) => $query->overdue())
                ->orderBy('promised_at')
                ->orderBy('id')
                ->paginate(PerPage::resolve($request->input('per_page')));

            return response()->json([
                'data' => LabCaseResource::collection($page->items())->resolve($request),
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
            Log::error('LabCaseController@index: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al listar los casos de laboratorio'], 500);
        }
    }

    public function show(LabCase $labCase): JsonResponse
    {
        try {
            return response()->json([
                'data' => (new LabCaseResource($labCase->load(self::RELATIONS)))->resolve(request()),
            ]);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('LabCaseController@show: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al obtener el caso de laboratorio'], 500);
        }
    }

    public function store(StoreLabCaseRequest $request): JsonResponse
    {
        try {
            $case = $this->cases->create($request->validated(), $request->user());

            return response()->json([
                'data' => (new LabCaseResource($case->load(self::RELATIONS)))->resolve($request),
                'meta' => ['message' => 'Caso de laboratorio registrado exitosamente'],
            ], 201);
        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('LabCaseController@store: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al registrar el caso de laboratorio'], 500);
        }
    }

    public function update(UpdateLabCaseRequest $request, LabCase $labCase): JsonResponse
    {
        // #449: the middleware owns the role and this owns the work order. The
        // policy runs before validation so a foreign case is a 403 with no
        // hint about which fields the payload would have needed.
        Gate::authorize('update', $labCase);

        try {
            $case = $this->cases->update($labCase, $request->validated());

            return response()->json([
                'data' => (new LabCaseResource($case->load(self::RELATIONS)))->resolve($request),
                'meta' => ['message' => 'Caso de laboratorio actualizado exitosamente'],
            ]);
        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('LabCaseController@update: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al actualizar el caso de laboratorio'], 500);
        }
    }

    public function transition(TransitionLabCaseRequest $request, LabCase $labCase): JsonResponse
    {
        // #449: the same work order answers to the same owner on both doors.
        // Moving the phase is a write to the case, so the technician who cannot
        // correct it cannot advance it either.
        Gate::authorize('update', $labCase);

        try {
            $case = $this->cases->transition($labCase, (string) $request->validated()['phase']);

            return response()->json([
                'data' => (new LabCaseResource($case->load(self::RELATIONS)))->resolve($request),
                'meta' => ['message' => 'Fase del caso de laboratorio actualizada exitosamente'],
            ]);
        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('LabCaseController@transition: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['message' => 'Error al actualizar la fase del caso de laboratorio'], 500);
        }
    }

    /**
     * `technician_id=me` is the laboratory desk asking for its own bench.
     */
    private function technicianFilter(Request $request): ?int
    {
        if (! $request->filled('technician_id')) {
            return null;
        }

        return $request->input('technician_id') === 'me'
            ? (int) $request->user()->id
            : (int) $request->input('technician_id');
    }
}
