<?php

namespace App\Http\Controllers\Api;

use App\Events\UserCreated;
use App\Events\UserUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\HasCrudRules;
use App\Http\Resources\UserResource;
use App\Listeners\ClearDashboardCache;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    use HasCrudRules;

    /**
     * The roles the application actually authorizes (`routes/api.php` role
     * middleware, AGENTS.md section 7). `admin`, `recepcion` and `dentista` are
     * retired spellings that no middleware accepts.
     */
    protected const ROLES = [
        'administrador',
        'recepcionista',
        'finanzas',
        'odontologo',
        'implantologo',
        'tecnico_dental',
        'asistente',
    ];

    /**
     * Tables whose rows are destroyed with the account by ON DELETE CASCADE.
     * Every entry is clinical, billing or inventory history: losing it can not
     * be undone, so an account that holds any of it is never hard deleted.
     *
     * [table => [singular, plural, foreign columns]]
     */
    protected const HISTORY_TABLES = [
        'appointments' => ['cita', 'citas', ['user_id', 'created_by']],
        'appointment_blocks' => ['bloqueo de agenda', 'bloqueos de agenda', ['user_id', 'created_by']],
        'ai_image_analyses' => ['análisis de imagen', 'análisis de imagen', ['requested_by']],
        'cash_movements' => ['movimiento de caja', 'movimientos de caja', ['created_by']],
        'cash_register_sessions' => ['sesión de caja', 'sesiones de caja', ['user_id']],
        'clinical_attachments' => ['adjunto clínico', 'adjuntos clínicos', ['created_by']],
        'clinical_evolutions' => ['evolución clínica', 'evoluciones clínicas', ['created_by']],
        'endodontics_records' => ['registro de endodoncia', 'registros de endodoncia', ['created_by']],
        'implantology_records' => ['registro de implantología', 'registros de implantología', ['created_by']],
        'interconsultations' => ['interconsulta', 'interconsultas', ['from_specialist_id', 'to_specialist_id']],
        'medical_records' => ['historia clínica', 'historias clínicas', ['created_by']],
        'odontograms' => ['odontograma', 'odontogramas', ['created_by']],
        'odontogram_records' => ['registro de odontograma', 'registros de odontograma', ['created_by']],
        'oral_surgery_records' => ['registro de cirugía oral', 'registros de cirugía oral', ['created_by']],
        'orthodontics_records' => ['registro de ortodoncia', 'registros de ortodoncia', ['created_by']],
        'payment_plans' => ['plan de pago', 'planes de pago', ['created_by']],
        'procedure_materials' => ['material de procedimiento', 'materiales de procedimiento', ['created_by']],
        'purchase_orders' => ['orden de compra', 'órdenes de compra', ['created_by']],
        'quotations' => ['presupuesto', 'presupuestos', ['created_by']],
        'receipts' => ['comprobante', 'comprobantes', ['created_by']],
        'rehabilitation_records' => ['registro de rehabilitación', 'registros de rehabilitación', ['created_by']],
        'stock_movements' => ['movimiento de inventario', 'movimientos de inventario', ['created_by']],
        'transactions' => ['transacción', 'transacciones', ['created_by']],
        'treatment_plans' => ['plan de tratamiento', 'planes de tratamiento', ['created_by']],
        'work_schedules' => ['horario', 'horarios', ['user_id']],
    ];

    /**
     * Rules shared by the store and update actions.
     */
    protected function storeRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'role' => 'required|in:'.implode(',', self::ROLES),
            'specialty' => 'nullable|string|max:255',
            'professional_license' => 'nullable|string|max:50',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::query();

            // Filter by role if specified (comma-separated list allowed)
            if ($request->has('role')) {
                $roles = collect(explode(',', (string) $request->get('role')))
                    ->map(fn ($role) => trim($role))
                    ->filter(fn ($role) => $role !== '')
                    ->values()
                    ->all();

                $query->whereIn('role', $roles);
            }

            $users = $query->orderBy('name')->get();

            return response()->json([
                'data' => UserResource::collection($users)->resolve(),
                'meta' => [
                    'total' => $users->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener usuarios',
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
            $validated = $request->validate($this->storeRules());

            $validated['password'] = Hash::make($validated['password']);
            $validated['is_active'] = $validated['is_active'] ?? true;

            $user = User::create($validated);

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'user_created',
                    $user,
                    [],
                    [
                        'name' => $user->name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'specialty' => $user->specialty_code ?? $user->specialty,
                        'role' => $user->role,
                        'is_active' => $user->is_active,
                    ]
                );
            }

            // Emitir evento de WebSocket
            event(new UserCreated($user));

            // Limpiar cache del dashboard
            ClearDashboardCache::handle();

            return response()->json([
                'data' => (new UserResource($user))->resolve(),
                'meta' => [
                    'message' => 'Usuario creado exitosamente',
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = User::with([
                'specialties',
                'auditLogs' => function ($query) {
                    $query->with('user:id,name,email')
                        ->orderBy('created_at', 'desc')
                        ->limit(50);
                },
            ])->findOrFail($id);

            return response()->json([
                'data' => (new UserResource($user))->resolve(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Usuario no encontrado',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            if (Auth::id() === $user->id && $request->has('is_active') && ! $request->boolean('is_active')) {
                return response()->json([
                    'message' => 'No puedes desactivar tu propia cuenta.',
                ], 422);
            }

            $validated = $request->validate($this->updateRules($this->storeRules(), [], [
                'username' => 'sometimes|string|max:255|unique:users,username,'.$id,
                'email' => 'sometimes|email|unique:users,email,'.$id,
            ]));

            // Capture old values for audit (only relevant fields)
            $oldValues = [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'specialty' => $user->specialty_code ?? $user->specialty,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ];

            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            $user->update($validated);
            $user->refresh();

            // Capture new values for audit (only relevant fields)
            $newValues = [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'specialty' => $user->specialty_code ?? $user->specialty,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ];

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'user_updated',
                    $user,
                    $oldValues,
                    $newValues
                );
            }

            // Emitir evento de WebSocket
            event(new UserUpdated($user));

            // Limpiar cache del dashboard
            ClearDashboardCache::handle();

            return response()->json([
                'data' => (new UserResource($user))->resolve(),
                'meta' => [
                    'message' => 'Usuario actualizado exitosamente',
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * The account is only hard deleted when it holds no clinical, billing or
     * inventory history; every cascading relation would destroy those rows.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            if ($request->user()->id === $user->id) {
                return response()->json([
                    'message' => 'No puedes eliminar tu propia cuenta.',
                ], 422);
            }

            $blockingMessage = $this->historyBlockingMessage($user);

            if ($blockingMessage !== null) {
                return response()->json([
                    'message' => $blockingMessage,
                ], 409);
            }

            $userData = [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'specialty' => $user->specialty_code ?? $user->specialty,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ];

            $user->delete();

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'user_deleted',
                    $user,
                    $userData,
                    []
                );
            }

            return response()->json([
                'meta' => [
                    'message' => 'Usuario eliminado exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build the message that blocks a hard delete, or null when the account
     * holds no history.
     */
    protected function historyBlockingMessage(User $user): ?string
    {
        $parts = [];

        foreach (self::HISTORY_TABLES as $table => [$singular, $plural, $columns]) {
            $count = DB::table($table)
                ->where(function ($query) use ($columns, $user) {
                    foreach ($columns as $column) {
                        $query->orWhere($column, $user->id);
                    }
                })
                ->count();

            if ($count > 0) {
                $parts[] = $count.' '.($count === 1 ? $singular : $plural);
            }
        }

        if ($parts === []) {
            return null;
        }

        $list = count($parts) === 1
            ? $parts[0]
            : implode(', ', array_slice($parts, 0, -1)).' y '.end($parts);

        return sprintf(
            'No se puede eliminar a %s: tiene %s. Desactívalo en su lugar.',
            $user->name,
            $list
        );
    }

    /**
     * Get active users (professionals only).
     */
    public function active(): JsonResponse
    {
        try {
            $users = User::where('is_active', true)
                ->whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental', 'asistente'])
                ->select('id', 'name', 'role', 'specialty')
                ->get();

            return response()->json([
                'data' => $users,
                'meta' => [
                    'total' => $users->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en UserController@active: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al obtener usuarios activos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
