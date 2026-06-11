<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\CashRegisterSession;
use Illuminate\Support\Facades\Auth;

/**
 * RequireActiveCashSession — middleware que bloquea operaciones que requieren
 * una sesión de caja abierta.
 *
 * Alias: `cash.session` (registrado en bootstrap/app.php).
 *
 * Aplica a creación/modificación de transacciones y movimientos de caja:
 *   - POST   /api/transactions           (TransactionController@store)
 *   - PUT    /api/transactions/{id}      (TransactionController@update)
 *   - POST   /api/cash-movements         (CashMovementController@store)
 *
 * Si el usuario no tiene una sesión de caja abierta (status=open), devuelve 422
 * con `error: 'NO_ACTIVE_SESSION'`. Si la tiene, agrega `active_cash_session`
 * al request para que el controller pueda usarla sin hacer una query extra.
 *
 * Si el proyecto evoluciona a sesiones multi-usuario o sesiones por sucursal,
 * este middleware es el lugar para extender la lógica.
 */
class RequireActiveCashSession
{
    public function handle(Request $request, Closure $next)
    {
        // Solo en operaciones de escritura (POST/PUT/PATCH) sobre los recursos
        // que requieren caja abierta. La verificación exacta de la ruta está
        // en el método de cada controller, pero aquí filtramos por método HTTP
        // para no romper lecturas (GET).
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $activeSession = CashRegisterSession::where('user_id', Auth::id())
            ->where('status', 'open')
            ->first();

        if (!$activeSession) {
            return response()->json([
                'message' => 'No hay una sesión de caja abierta. Debe abrir la caja antes de registrar transacciones o movimientos.',
                'error' => 'NO_ACTIVE_SESSION',
            ], 422);
        }

        // Add session info to request for use in controllers
        $request->merge(['active_cash_session' => $activeSession]);

        return $next($request);
    }
}

