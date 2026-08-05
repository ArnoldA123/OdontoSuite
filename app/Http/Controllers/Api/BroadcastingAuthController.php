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
 */
class BroadcastingAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $channelName = $request->input('channel_name');
            $socketId = $request->input('socket_id');

            if (!$channelName || !$socketId) {
                return response()->json(['message' => 'Invalid request'], 400);
            }

            $cleanChannelName = preg_replace('/^private-/', '', $channelName);

            $authorized = false;

            if (preg_match('/^cash-session\.(\d+)$/', $cleanChannelName, $matches)) {
                $authorized = true;
            } elseif (preg_match('/^App\.Models\.User\.(\d+)$/', $cleanChannelName, $matches)) {
                $userId = $matches[1];
                $authorized = (int) $user->id === (int) $userId;
            } elseif (preg_match('/^user\.(\d+)$/', $cleanChannelName, $matches)) {
                $userId = $matches[1];
                $authorized = (int) $user->id === (int) $userId;
            } else {
                Log::warning('Broadcasting auth: Canal no reconocido', ['channel' => $cleanChannelName]);
                return response()->json(['message' => 'Channel not found'], 404);
            }

            if (!$authorized) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            // BF-019 (slice 11): missing REVERB_APP_SECRET / KEY is
            // a service-unavailable condition (the upstream Reverb
            // server isn't reachable / configured) — return 503 instead
            // of 500 so callers can retry without alerting on a code bug.
            $secret = config('broadcasting.connections.reverb.secret');
            if (!$secret) {
                Log::error('Broadcasting auth: REVERB_APP_SECRET no configurado');
                return response()->json(['message' => 'Service unavailable: Reverb not configured'], 503);
            }

            $key = config('broadcasting.connections.reverb.key');
            if (!$key) {
                Log::error('Broadcasting auth: REVERB_APP_KEY no configurado');
                return response()->json(['message' => 'Service unavailable: Reverb not configured'], 503);
            }

            $stringToSign = $socketId . ':' . $channelName;
            $signature = hash_hmac('sha256', $stringToSign, $secret, false);

            return response()->json([
                'auth' => $key . ':' . $signature,
            ]);
        } catch (\Exception $e) {
            Log::error('Broadcasting auth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Internal server error'], 500);
        }
    }
}
