<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ThrottleLoginAttempts
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = $request->input('username');
        $ip = $request->ip();
        
        // Claves para tracking
        $attemptsKey = "login_attempts:{$ip}:{$username}";
        $blockedKey = "login_blocked:{$ip}:{$username}";
        $failedAttemptsKey = "login_failed_count:{$ip}:{$username}";
        
        // Verificar si está bloqueado (después de 5 errores, bloqueo por 10 minutos)
        if (Cache::has($blockedKey)) {
            $blockedUntil = Cache::get($blockedKey);
            $remainingMinutes = ceil(($blockedUntil - now()->timestamp) / 60);
            
            Log::warning('Login attempt blocked', [
                'ip' => $ip,
                'username' => $username,
                'blocked_until' => $blockedUntil,
                'remaining_minutes' => $remainingMinutes
            ]);
            
            return response()->json([
                'message' => 'Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente.',
                'errors' => [
                    'username' => ["Has excedido el número máximo de intentos. Intenta nuevamente en {$remainingMinutes} minuto(s)."],
                ],
                'meta' => [
                    'blocked_until' => $blockedUntil,
                    'remaining_minutes' => $remainingMinutes,
                ],
            ], 429);
        }
        
        // Verificar rate limiting: máximo 3 intentos por minuto
        $attempts = Cache::get($attemptsKey, 0);
        
        if ($attempts >= 3) {
            Log::warning('Login rate limit exceeded', [
                'ip' => $ip,
                'username' => $username,
                'attempts' => $attempts
            ]);
            
            return response()->json([
                'message' => 'Demasiados intentos de inicio de sesión.',
                'errors' => [
                    'username' => ['Has excedido el límite de intentos. Por favor, espera un minuto antes de intentar nuevamente.'],
                ],
            ], 429);
        }
        
        // Incrementar contador de intentos (expira en 1 minuto)
        Cache::put($attemptsKey, $attempts + 1, now()->addMinute());
        
        // Procesar la solicitud
        $response = $next($request);
        
        // Si el login falló, incrementar contador de errores
        if ($response->getStatusCode() === 401 || $response->getStatusCode() === 422) {
            $failedCount = Cache::get($failedAttemptsKey, 0) + 1;
            
            // Si alcanza 5 errores, bloquear por 10 minutos
            if ($failedCount >= 5) {
                $blockedUntil = now()->addMinutes(10)->timestamp;
                Cache::put($blockedKey, $blockedUntil, now()->addMinutes(10));
                Cache::forget($failedAttemptsKey);
                
                Log::warning('Login account blocked after 5 failed attempts', [
                    'ip' => $ip,
                    'username' => $username,
                    'blocked_until' => $blockedUntil
                ]);
            } else {
                // Mantener contador de errores (expira en 10 minutos)
                Cache::put($failedAttemptsKey, $failedCount, now()->addMinutes(10));
            }
        } else {
            // Si el login fue exitoso, limpiar contadores
            Cache::forget($attemptsKey);
            Cache::forget($failedAttemptsKey);
            Cache::forget($blockedKey);
        }
        
        return $response;
    }
}

