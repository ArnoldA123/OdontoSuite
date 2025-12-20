<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\CashRegisterSession;
use Illuminate\Support\Facades\Auth;

class RequireActiveCashSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply to transaction creation routes
        if ($request->isMethod('POST') && $request->is('api/transactions')) {
            $activeSession = CashRegisterSession::where('user_id', Auth::id())
                ->where('status', 'open')
                ->first();

            if (!$activeSession) {
                return response()->json([
                    'message' => 'No hay una sesión de caja abierta. Debe abrir la caja antes de registrar transacciones.',
                    'error' => 'NO_ACTIVE_SESSION'
                ], 422);
            }

            // Add session info to request for use in controllers
            $request->merge(['active_cash_session' => $activeSession]);
        }

        return $next($request);
    }
}

