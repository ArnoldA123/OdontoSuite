<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\HasCrudRules;
use App\Models\AuditLog;
use App\Models\DentalChair;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DentalChairController extends Controller
{
    use HasCrudRules;

    /**
     * Rules shared by the store and update actions.
     */
    protected function storeRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'equipment' => 'nullable|string',
            'status' => 'required|in:active,inactive,maintenance',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:active,inactive,maintenance',
            'archived' => 'sometimes|in:exclude,include,only',
        ]);

        try {
            $query = DentalChair::query();

            $archived = $validated['archived'] ?? 'exclude';

            if ($archived === 'exclude') {
                $query->where('is_active', true);
            } elseif ($archived === 'only') {
                $query->where('is_active', false);
            }

            if (isset($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            $chairs = $query->orderBy('name')->get();

            return response()->json([
                'data' => $chairs,
                'meta' => [
                    'total' => $chairs->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener ambientes: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al obtener ambientes',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate($this->storeRules());

            $validated['is_active'] = $validated['is_active'] ?? true;

            $chair = $this->createWithGeneratedCode($validated);

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'dental_chair_created',
                    $chair,
                    [],
                    [
                        'name' => $chair->name,
                        'code' => $chair->code,
                        'description' => $chair->description,
                        'equipment' => $chair->equipment,
                        'status' => $chair->status,
                        'is_active' => $chair->is_active,
                    ]
                );
            }

            return response()->json([
                'data' => $chair,
                'meta' => [
                    'message' => 'Ambiente creado exitosamente',
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear ambiente: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al crear ambiente',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $chair = DentalChair::findOrFail($id);

            return response()->json([
                'data' => $chair,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ambiente no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al obtener ambiente: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al obtener ambiente',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $chair = DentalChair::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ambiente no encontrado',
            ], 404);
        }

        try {
            $validated = $request->validate($this->updateRules($this->storeRules()));

            // Capture old values for audit (only relevant fields)
            $oldValues = [
                'name' => $chair->name,
                'code' => $chair->code,
                'description' => $chair->description,
                'equipment' => $chair->equipment,
                'status' => $chair->status,
                'is_active' => $chair->is_active,
            ];

            $chair->update($validated);
            $chair->refresh();

            // Capture new values for audit (only relevant fields)
            $newValues = [
                'name' => $chair->name,
                'code' => $chair->code,
                'description' => $chair->description,
                'equipment' => $chair->equipment,
                'status' => $chair->status,
                'is_active' => $chair->is_active,
            ];

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'dental_chair_updated',
                    $chair,
                    $oldValues,
                    $newValues
                );
            }

            return response()->json([
                'data' => $chair,
                'meta' => [
                    'message' => 'Ambiente actualizado exitosamente',
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar ambiente: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al actualizar ambiente',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $chair = DentalChair::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ambiente no encontrado',
            ], 404);
        }

        if ($this->hasSchedulingHistory($chair)) {
            return response()->json([
                'message' => 'No se puede eliminar el ambiente porque tiene citas o bloqueos asociados.',
            ], 409);
        }

        try {
            // Capture values for audit before deletion
            $chairData = [
                'name' => $chair->name,
                'code' => $chair->code,
                'description' => $chair->description,
                'equipment' => $chair->equipment,
                'status' => $chair->status,
                'is_active' => $chair->is_active,
            ];

            $chair->delete();

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'dental_chair_deleted',
                    $chair,
                    $chairData,
                    []
                );
            }

            return response()->json([
                'meta' => [
                    'message' => 'Ambiente eliminado exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar ambiente: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al eliminar ambiente',
            ], 500);
        }
    }

    /**
     * Whether the environment is referenced by appointments or blocks.
     *
     * Soft-deleted appointments still count: the foreign key cascade would
     * remove their rows permanently and bypass the model soft delete.
     */
    private function hasSchedulingHistory(DentalChair $chair): bool
    {
        return $chair->appointments()->withTrashed()->exists()
            || $chair->appointmentBlocks()->exists();
    }

    /**
     * Create the environment with the next free AMB-### code.
     *
     * The code is derived from the highest existing numeric suffix instead of
     * the row count, so deletions never make the next code collide with a live
     * row. The retry covers a concurrent insert between the lock and the write.
     */
    private function createWithGeneratedCode(array $attributes, int $attempts = 3): DentalChair
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes) {
                    $attributes['code'] = $this->nextCode(
                        DentalChair::query()->lockForUpdate()->pluck('code')
                    );

                    return DentalChair::create($attributes);
                });
            } catch (QueryException $e) {
                if ($attempt >= $attempts || ! $this->isDuplicateCode($e)) {
                    throw $e;
                }
            }
        }
    }

    private function nextCode(iterable $codes): string
    {
        $max = 0;

        foreach ($codes as $code) {
            if (preg_match('/^AMB-(\d+)$/', $code, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'AMB-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    private function isDuplicateCode(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'Duplicate entry');
    }

    /**
     * Get active dental chairs.
     */
    public function active(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:active,inactive,maintenance',
        ]);

        try {
            $query = DentalChair::where('is_active', true);

            if (isset($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            $chairs = $query
                ->select('id', 'name', 'code', 'description', 'status')
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $chairs,
                'meta' => [
                    'total' => $chairs->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en DentalChairController@active: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al obtener sillas dentales activas',
            ], 500);
        }
    }

    /**
     * Search dental chairs.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'archived' => 'sometimes|in:exclude,include,only',
        ]);

        try {
            $query = $request->get('q', '');
            $archived = $validated['archived'] ?? 'exclude';

            $chairs = DentalChair::query()
                ->when($archived === 'exclude', fn ($q) => $q->where('is_active', true))
                ->when($archived === 'only', fn ($q) => $q->where('is_active', false))
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhere('equipment', 'like', "%{$query}%");
                })
                ->orderBy('name')
                ->get();

            return response()->json([
                'data' => $chairs,
                'meta' => [
                    'total' => $chairs->count(),
                    'query' => $query,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al buscar ambientes: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al buscar ambientes',
            ], 500);
        }
    }
}
