<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Events\UserCreated;
use App\Events\UserUpdated;
use App\Listeners\ClearDashboardCache;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::query();

            // Filter by role if specified
            if ($request->has('role')) {
                $query->where('role', $request->get('role'));
            }

            $users = $query->get();

            return response()->json([
                'data' => $users,
                'meta' => [
                    'total' => $users->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:users',
                'email' => 'required|email|unique:users',
                'phone' => 'nullable|string|max:20',
                'password' => 'required|string|min:6',
                'role' => 'required|in:admin,recepcion,odontologo',
                'specialty' => 'nullable|string|max:255',
                'is_active' => 'boolean'
            ]);

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
                'data' => $user,
                'meta' => [
                    'message' => 'Usuario creado exitosamente'
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage()
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
                'auditLogs' => function ($query) {
                    $query->with('user:id,name,email')
                          ->orderBy('created_at', 'desc')
                          ->limit(50);
                }
            ])->findOrFail($id);

            return response()->json([
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Usuario no encontrado',
                'error' => $e->getMessage()
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

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'username' => 'sometimes|string|max:255|unique:users,username,' . $id,
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'password' => 'sometimes|string|min:6',
                'role' => 'sometimes|in:admin,recepcion,odontologo',
                'specialty' => 'nullable|string|max:255',
                'is_active' => 'boolean'
            ]);

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
                'data' => $user,
                'meta' => [
                    'message' => 'Usuario actualizado exitosamente'
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            
            // Capture values for audit before deletion
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
                    'message' => 'Usuario eliminado exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search users.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');
            $role = $request->get('role', '');

            $users = User::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('username', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('specialty', 'like', "%{$query}%");
            });

            if ($role) {
                $users->where('role', $role);
            }

            $users = $users->get();

            return response()->json([
                'data' => $users,
                'meta' => [
                    'total' => $users->count(),
                    'query' => $query,
                    'role' => $role
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al buscar usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active users (professionals only)
     */
    public function active(): JsonResponse
    {
        try {
            $users = User::where('is_active', true)
                ->whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental', 'asistente', 'dentista'])
                ->select('id', 'name', 'role', 'specialty')
                ->get();

            return response()->json([
                'data' => $users,
                'meta' => [
                    'total' => $users->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en UserController@active: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener usuarios activos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
