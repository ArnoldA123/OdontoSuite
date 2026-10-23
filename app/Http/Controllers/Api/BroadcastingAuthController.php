<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Slice 11 / BF-025 + BF-019.
 *
 * Extracted from the inline closure in routes/api.php so the broadcasting
 * auth handler can be unit-tested, type-hinted, and so the HTTP semantics
 * can be tightened (503 instead of 500 when Reverb is misconfigured).
 *
 * Issue #249: this handler is the effective authorization authority for every
 * private channel. Each domain channel accepts exactly the roles that already
 * have read access to its domain in routes/api.php; routes/channels.php mirrors
 * the same rules as documentation.
 */
class BroadcastingAuthController extends Controller
{
    /**
     * Every authenticated role. Listed explicitly so a new role stays out of a
     * channel until someone decides otherwise.
     *
     * @var string[]
     */
    private const ALL_ROLES = [
        'administrador', 'recepcionista', 'odontologo', 'implantologo',
        'tecnico_dental', 'asistente', 'finanzas',
    ];

    /**
     * Roles with access to appointments and reminders
     * (routes/api.php, "Citas (todos los roles excepto finanzas)").
     *
     * @var string[]
     */
    private const APPOINTMENT_ROLES = [
        'administrador', 'recepcionista', 'odontologo',
        'implantologo', 'tecnico_dental', 'asistente',
    ];

    /**
     * Roles with read access to medical records.
     *
     * @var string[]
     */
    private const MEDICAL_RECORD_ROLES = [
        'administrador', 'odontologo', 'implantologo', 'asistente',
    ];

    /**
     * Roles with access to treatment plans and specialty records.
     *
     * @var string[]
     */
    private const SPECIALIST_ROLES = [
        'administrador', 'odontologo', 'implantologo', 'tecnico_dental',
    ];

    /**
     * Roles that operate the cash register.
     *
     * @var string[]
     */
    private const CASH_ROLES = ['administrador', 'finanzas', 'recepcionista'];

    /**
     * Roles with quotation read access (issue #200-#204).
     *
     * @var string[]
     */
    private const QUOTATION_ROLES = [
        'administrador', 'finanzas', 'odontologo', 'implantologo', 'recepcionista',
    ];

    /**
     * Roles with laboratory read access (F5, #285). The case carries the
     * patient, the work order and the assigned technician, so the desks that
     * answer for it subscribe; finance stays out.
     *
     * @var string[]
     */
    private const LABORATORY_ROLES = [
        'administrador', 'recepcionista', 'odontologo',
        'implantologo', 'tecnico_dental', 'asistente',
    ];

    /**
     * Exact channel name => roles allowed to subscribe.
     *
     * @var array<string, string[]>
     */
    private const ROLE_CHANNELS = [
        // Issue #249: domain channels that used to be public.
        'appointments' => self::APPOINTMENT_ROLES,
        'reminders' => self::APPOINTMENT_ROLES,
        'medical-records' => self::MEDICAL_RECORD_ROLES,
        'specialty-records' => self::SPECIALIST_ROLES,
        'treatment-plans' => self::SPECIALIST_ROLES,
        'cash-register' => self::CASH_ROLES,
        'transactions' => self::CASH_ROLES,
        'procedure-catalog' => self::ALL_ROLES,
        'users' => ['administrador'],
        // Closed before #249.
        'dashboard-updates' => self::ALL_ROLES,
        'quotations' => self::QUOTATION_ROLES,
        'patients' => self::ALL_ROLES,
        // F5 (#285, S4): the prosthetic case notification.
        'lab-cases' => self::LABORATORY_ROLES,
    ];

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $channelName = $request->input('channel_name');
            $socketId = $request->input('socket_id');

            if (! $channelName || ! $socketId) {
                return response()->json(['message' => 'Invalid request'], 400);
            }

            $cleanChannelName = preg_replace('/^private-/', '', $channelName);

            $authorized = false;

            if (preg_match('/^cash-session\.(\d+)$/', $cleanChannelName, $matches)) {
                // Issue #249: the session payload carries payments and ledger
                // movements, so only the roles that operate the cash register
                // may subscribe. It used to accept any authenticated user.
                $authorized = in_array($user->role ?? null, self::CASH_ROLES, true);
            } elseif (preg_match('/^App\.Models\.User\.(\d+)$/', $cleanChannelName, $matches)) {
                $userId = $matches[1];
                $authorized = (int) $user->id === (int) $userId;
            } elseif (preg_match('/^user\.(\d+)$/', $cleanChannelName, $matches)) {
                $userId = $matches[1];
                $authorized = (int) $user->id === (int) $userId;
            } elseif (preg_match('/^appointment\.(\d+)$/', $cleanChannelName)) {
                $authorized = in_array($user->role ?? null, self::APPOINTMENT_ROLES, true);
            } elseif (preg_match('/^cash-register\.(\d+|global)$/', $cleanChannelName)) {
                $authorized = in_array($user->role ?? null, self::CASH_ROLES, true);
            } elseif (array_key_exists($cleanChannelName, self::ROLE_CHANNELS)) {
                // Issue #249: domain channels carry patient data, clinical
                // records and amounts, so the handshake mirrors the read
                // authorization of the matching routes/api.php group. A role
                // that is not listed stays out until someone adds it.
                $authorized = in_array($user->role ?? null, self::ROLE_CHANNELS[$cleanChannelName], true);
            } else {
                Log::warning('Broadcasting auth: Canal no reconocido', ['channel' => $cleanChannelName]);

                return response()->json(['message' => 'Channel not found'], 404);
            }

            if (! $authorized) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            // BF-019 (slice 11): missing REVERB_APP_SECRET / KEY is
            // a service-unavailable condition (the upstream Reverb
            // server isn't reachable / configured) — return 503 instead
            // of 500 so callers can retry without alerting on a code bug.
            $secret = config('broadcasting.connections.reverb.secret');
            if (! $secret) {
                Log::error('Broadcasting auth: REVERB_APP_SECRET no configurado');

                return response()->json(['message' => 'Service unavailable: Reverb not configured'], 503);
            }

            $key = config('broadcasting.connections.reverb.key');
            if (! $key) {
                Log::error('Broadcasting auth: REVERB_APP_KEY no configurado');

                return response()->json(['message' => 'Service unavailable: Reverb not configured'], 503);
            }

            $stringToSign = $socketId.':'.$channelName;
            $signature = hash_hmac('sha256', $stringToSign, $secret, false);

            return response()->json([
                'auth' => $key.':'.$signature,
            ]);
        } catch (\Exception $e) {
            Log::error('Broadcasting auth error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Internal server error'], 500);
        }
    }
}
