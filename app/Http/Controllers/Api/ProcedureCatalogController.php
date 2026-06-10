<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProcedureCatalogResource;
use App\Models\AuditLog;
use App\Models\ProcedureCatalog;
use App\Services\ProcedureCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProcedureCatalogController extends Controller
{
    public function __construct(private readonly ProcedureCatalogService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['q', 'specialty', 'is_active', 'per_page']);
            $page = $this->service->paginate($filters);

            return response()->json([
                'data' => ProcedureCatalogResource::collection($page->items()),
                'meta' => [
                    'total' => $page->total(),
                    'per_page' => $page->perPage(),
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcedureCatalogController@index: ' . $e->getMessage());
            return response()->json(['message' => 'Error al obtener el catálogo de procedimientos'], 500);
        }
    }

    public function active(): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->service->activeList(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcedureCatalogController@active: ' . $e->getMessage());
            return response()->json(['message' => 'Error al obtener procedimientos activos'], 500);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $term = (string) $request->get('q', '');
            $limit = (int) $request->get('limit', 15);

            return response()->json([
                'data' => $this->service->search($term, $limit),
                'meta' => [
                    'query' => $term,
                    'count' => count($this->service->search($term, $limit)),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcedureCatalogController@search: ' . $e->getMessage());
            return response()->json(['message' => 'Error al buscar en el catálogo'], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $procedure = $this->service->findOrFail((int) $id);
            $procedure->load('specialty');
            return response()->json(['data' => new ProcedureCatalogResource($procedure)]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Procedimiento no encontrado'], 404);
        } catch (\Throwable $e) {
            Log::error('ProcedureCatalogController@show: ' . $e->getMessage());
            return response()->json(['message' => 'Error al obtener el procedimiento'], 500);
        }
    }

    public function forMe(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $filters = $request->only(['q', 'specialty', 'per_page']);
            $page = $this->service->forUser($user, $filters);

            return response()->json([
                'data' => ProcedureCatalogResource::collection($page->items()),
                'meta' => [
                    'total' => $page->total(),
                    'per_page' => $page->perPage(),
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcedureCatalogController@forMe: ' . $e->getMessage());
            return response()->json(['message' => 'Error al obtener procedimientos del usuario'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateProcedure($request, isUpdate: false);
            $procedure = $this->service->create($validated);

            $this->logAudit('procedure_catalog_created', $procedure, [], $procedure->only([
                'code', 'name', 'specialty_id', 'legacy_specialty', 'default_cost',
            ]));

            return response()->json([
                'data' => new ProcedureCatalogResource($procedure),
                'meta' => ['message' => 'Procedimiento creado exitosamente'],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('ProcedureCatalogController@store: ' . $e->getMessage());
            return response()->json(['message' => 'Error al crear el procedimiento'], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $procedure = $this->service->findOrFail((int) $id);
            $old = $procedure->only(['code', 'name', 'specialty_id', 'legacy_specialty', 'default_cost', 'is_active']);

            $validated = $this->validateProcedure($request, isUpdate: true);
            $procedure = $this->service->update($procedure, $validated);

            $this->logAudit('procedure_catalog_updated', $procedure, $old, $procedure->only([
                'code', 'name', 'specialty_id', 'legacy_specialty', 'default_cost', 'is_active',
            ]));

            return response()->json([
                'data' => new ProcedureCatalogResource($procedure),
                'meta' => ['message' => 'Procedimiento actualizado exitosamente'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Error de validación', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Procedimiento no encontrado'], 404);
        } catch (\Throwable $e) {
            Log::error('ProcedureCatalogController@update: ' . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar el procedimiento'], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $procedure = $this->service->findOrFail((int) $id);
            $old = $procedure->only(['code', 'name']);

            $this->service->deactivate($procedure);

            $this->logAudit('procedure_catalog_deactivated', $procedure, $old, ['is_active' => false]);

            return response()->json([
                'meta' => ['message' => 'Procedimiento desactivado'],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Procedimiento no encontrado'], 404);
        } catch (\Throwable $e) {
            Log::error('ProcedureCatalogController@destroy: ' . $e->getMessage());
            return response()->json(['message' => 'Error al desactivar el procedimiento'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProcedure(Request $request, bool $isUpdate): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';
        $requiredNullable = $isUpdate ? 'sometimes' : 'nullable';

        $codeRule = $isUpdate
            ? ['sometimes', 'string', 'max:20', Rule::unique('procedure_catalog', 'code')->ignore($request->route('id'))]
            : ['required', 'string', 'max:20', 'unique:procedure_catalog,code'];

        return $request->validate([
            'code' => $codeRule,
            'name' => "{$required}|string|max:200",
            'description' => "{$requiredNullable}|string",
            'specialty_id' => "{$requiredNullable}|integer|exists:specialties,id",
            'legacy_specialty' => "{$requiredNullable}|string|max:50",
            'default_cost' => "{$required}|numeric|min:0",
            'default_duration_minutes' => "{$requiredNullable}|integer|min:5|max:600",
            'requirements' => "{$requiredNullable}|string",
            'materials_needed' => "{$requiredNullable}|string",
            'requires_anesthesia' => "{$requiredNullable}|boolean",
            'requires_radiographs' => "{$requiredNullable}|boolean",
            'steps' => "{$requiredNullable}|array",
            'steps.*' => 'string|max:500',
            'contraindications' => "{$requiredNullable}|string",
            'post_procedure_care' => "{$requiredNullable}|string",
            'is_active' => "{$requiredNullable}|boolean",
        ]);
    }

    private function logAudit(string $action, ProcedureCatalog $procedure, array $old, array $new): void
    {
        if (!Auth::check()) {
            return;
        }
        try {
            AuditLog::log(Auth::user(), $action, $procedure, $old, $new);
        } catch (\Throwable $e) {
            Log::warning('Audit log failed for procedure catalog: ' . $e->getMessage());
        }
    }
}
