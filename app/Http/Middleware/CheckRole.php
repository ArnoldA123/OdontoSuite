<?php

namespace App\Http\Middleware;

/**
 * CheckRole — middleware canónico para control de acceso basado en roles.
 *
 * Alias registrado en `bootstrap/app.php` como `role`:
 *   $middleware->alias(['role' => \App\Http\Middleware\CheckRole::class]);
 *
 * Uso en rutas:
 *   Route::middleware('role:administrador,odontologo')->group(...)
 *
 * Este es el ÚNICO middleware de roles. El antiguo `RoleMiddleware.php` quedó
 * como código muerto y fue eliminado (C-6/I-5, Sprint 2 del plan maestro de
 * inconsistencias).
 *
 * IMPORTANTE: Este middleware asume que el usuario ya está autenticado.
 * En el grupo de rutas debe ir DESPUÉS de `auth:sanctum`, por ejemplo:
 *   Route::middleware(['auth:sanctum', 'role:administrador'])->group(...)
 */
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Verificar que el usuario esté autenticado
        if (!$request->user()) {
            abort(401, 'No autenticado');
        }

        // Verificar que el usuario tenga uno de los roles permitidos
        if (!in_array($request->user()->role, $roles)) {
            abort(403, 'No tienes permisos para acceder a este recurso');
        }

        return $next($request);
    }
}



































