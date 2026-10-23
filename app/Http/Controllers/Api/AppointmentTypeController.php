<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\HasCrudRules;
use App\Models\AppointmentType;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AppointmentTypeController extends Controller
{
    use HasCrudRules;

    /**
     * Rules shared by the store and update actions.
     *
     * `duration_minutes` is the write alias of the `default_duration_minutes`
     * column, and every catalog rule the domain consumes
     * (`requires_confirmation`, `requires_materials`, `is_consultation_mode`,
     * `default_procedure_catalog_id`) is part of the contract: a flag that no
     * write path accepts is a rule the operator cannot set.
     */
    protected function storeRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'price' => 'nullable|numeric|min:0',
            'default_procedure_catalog_id' => 'nullable|integer|exists:procedure_catalog,id',
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'requires_confirmation' => 'boolean',
            'requires_materials' => 'boolean',
            'is_consultation_mode' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Accept the resource field `default_duration_minutes` as the duration on
     * write. Without this, a client that echoes a resource back (the shape the
     * API itself returns) gets a 200 and an unchanged duration.
     *
     * The explicit `duration_minutes` alias always wins when both are sent.
     */
    private function normalizeDurationInput(Request $request): void
    {
        if ($request->has('duration_minutes')) {
            return;
        }

        if ($request->has('default_duration_minutes')) {
            $request->merge([
                'duration_minutes' => $request->input('default_duration_minutes'),
            ]);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $status = $request->query('status', 'active');

            $query = AppointmentType::with('defaultProcedureCatalog.materialRecipe');

            if ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status !== 'all') {
                $query->where('is_active', true);
            }

            $types = $query->get();

            return response()->json([
                // #295 (S7): el eager loading de la receta evita una consulta
                // por fila al publicar `requires_materials` derivado.
                'data' => $types->map(fn (AppointmentType $type) => $type->toResponseArray())->all(),
                'meta' => [
                    'total' => $types->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener tipos de cita',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $this->normalizeDurationInput($request);

            $validated = $request->validate($this->storeRules());

            $validated['is_active'] = $validated['is_active'] ?? true;

            // Map duration_minutes to default_duration_minutes if needed
            if (isset($validated['duration_minutes'])) {
                $validated['default_duration_minutes'] = $validated['duration_minutes'];
                unset($validated['duration_minutes']);
            }

            $type = AppointmentType::create($validated);
            // The response is the persisted resource: fields omitted by the
            // caller (the flags) carry their schema default, not null.
            $type->refresh();

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'appointment_type_created',
                    $type,
                    [],
                    [
                        'name' => $type->name,
                        'description' => $type->description,
                        'default_duration_minutes' => $type->default_duration_minutes,
                        'price' => $type->price,
                        'default_procedure_catalog_id' => $type->default_procedure_catalog_id,
                        'color' => $type->color,
                        'requires_confirmation' => $type->requires_confirmation,
                        'requires_materials' => $type->requires_materials,
                        'is_consultation_mode' => $type->is_consultation_mode,
                        'is_active' => $type->is_active,
                    ]
                );
            }

            return response()->json([
                'data' => $type->toResponseArray(),
                'meta' => [
                    'message' => 'Tipo de cita creado exitosamente',
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear tipo de cita',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $type = AppointmentType::with([
            'defaultProcedureCatalog.materialRecipe',
            'auditLogs' => function ($query) {
                $query->with('user:id,name,email')
                    ->orderBy('created_at', 'desc')
                    ->limit(50);
            },
        ])->find($id);

        if (! $type) {
            return $this->notFoundResponse();
        }

        return response()->json([
            'data' => $type->toResponseArray(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $type = AppointmentType::find($id);

        if (! $type) {
            return $this->notFoundResponse();
        }

        try {
            $this->normalizeDurationInput($request);

            $validated = $request->validate($this->updateRules($this->storeRules()));

            // Map duration_minutes to default_duration_minutes if needed
            if (isset($validated['duration_minutes'])) {
                $validated['default_duration_minutes'] = $validated['duration_minutes'];
                unset($validated['duration_minutes']);
            }

            // Capture old values for audit (only relevant fields)
            $oldValues = [
                'name' => $type->name,
                'description' => $type->description,
                'default_duration_minutes' => $type->default_duration_minutes,
                'price' => $type->price,
                'default_procedure_catalog_id' => $type->default_procedure_catalog_id,
                'color' => $type->color,
                'requires_confirmation' => $type->requires_confirmation,
                'requires_materials' => $type->requires_materials,
                'is_consultation_mode' => $type->is_consultation_mode,
                'is_active' => $type->is_active,
            ];

            $type->update($validated);
            $type->refresh();

            // Capture new values for audit (only relevant fields)
            $newValues = [
                'name' => $type->name,
                'description' => $type->description,
                'default_duration_minutes' => $type->default_duration_minutes,
                'price' => $type->price,
                'default_procedure_catalog_id' => $type->default_procedure_catalog_id,
                'color' => $type->color,
                'requires_confirmation' => $type->requires_confirmation,
                'requires_materials' => $type->requires_materials,
                'is_consultation_mode' => $type->is_consultation_mode,
                'is_active' => $type->is_active,
            ];

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'appointment_type_updated',
                    $type,
                    $oldValues,
                    $newValues
                );
            }

            return response()->json([
                'data' => $type->toResponseArray(),
                'meta' => [
                    'message' => 'Tipo de cita actualizado exitosamente',
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar tipo de cita',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $type = AppointmentType::find($id);

        if (! $type) {
            return $this->notFoundResponse();
        }

        // `appointments.appointment_type_id` deletes on cascade at the FK
        // level, so a hard delete here would wipe every appointment of the
        // type. Deactivation is the supported path once appointments exist.
        $appointmentsCount = $type->appointments()->count();

        if ($appointmentsCount > 0) {
            $appointmentsLabel = $appointmentsCount === 1
                ? '1 cita asociada'
                : "{$appointmentsCount} citas asociadas";

            return response()->json([
                'message' => "No se puede eliminar el tipo de cita: tiene {$appointmentsLabel}. Desactívalo en su lugar.",
                'meta' => [
                    'appointments_count' => $appointmentsCount,
                ],
            ], 409);
        }

        try {
            // Capture values for audit before deletion
            $typeData = [
                'name' => $type->name,
                'description' => $type->description,
                'default_duration_minutes' => $type->default_duration_minutes,
                'price' => $type->price,
                'default_procedure_catalog_id' => $type->default_procedure_catalog_id,
                'color' => $type->color,
                'requires_confirmation' => $type->requires_confirmation,
                'requires_materials' => $type->requires_materials,
                'is_consultation_mode' => $type->is_consultation_mode,
                'is_active' => $type->is_active,
            ];

            $type->delete();

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'appointment_type_deleted',
                    $type,
                    $typeData,
                    []
                );
            }

            return response()->json([
                'meta' => [
                    'message' => 'Tipo de cita eliminado exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar tipo de cita',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Standard 404 payload for an unknown appointment type.
     */
    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Tipo de cita no encontrado',
        ], 404);
    }

    /**
     * Get active appointment types.
     */
    public function active(): JsonResponse
    {
        try {
            $types = AppointmentType::where('is_active', true)
                ->select('id', 'name', 'default_duration_minutes', 'price', 'color')
                ->get();

            return response()->json([
                'data' => $types,
                'meta' => [
                    'total' => $types->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en AppointmentTypeController@active: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al obtener tipos de cita activos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search appointment types.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');

            $types = AppointmentType::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })->get();

            return response()->json([
                'data' => $types,
                'meta' => [
                    'total' => $types->count(),
                    'query' => $query,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al buscar tipos de cita',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
