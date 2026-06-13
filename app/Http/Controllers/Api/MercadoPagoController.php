<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MercadoPagoController extends Controller
{
    public function __construct(
        private readonly MercadoPagoService $mpService
    ) {}

    /**
     * Crear preferencia de pago MP para una transaccion.
     * POST /api/payments/mercadopago/preference
     */
    public function createPreference(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|integer|exists:transactions,id',
        ]);

        try {
            $transaction = Transaction::with('patient')->findOrFail($validated['transaction_id']);

            $backUrl = config('mercadopago.back_urls.success', '/cash-register?payment=success');
            $failureUrl = config('mercadopago.back_urls.failure', '/cash-register?payment=failure');
            $pendingUrl = config('mercadopago.back_urls.pending', '/cash-register?payment=pending');

            $preference = $this->mpService->createPreference(
                $transaction,
                url($backUrl),
                url($failureUrl),
                url($pendingUrl)
            );

            return response()->json([
                'data' => $preference,
                'meta' => ['message' => 'Preferencia de pago creada'],
            ], 201);
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            return response()->json([
                'message' => 'Error al comunicarse con Mercado Pago.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 502);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la preferencia de pago.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Webhook de Mercado Pago (sin auth — validado por firma HMAC).
     * POST /api/payments/webhooks/mercadopago
     */
    public function webhook(Request $request): JsonResponse
    {
        // Validar firma
        if (!$this->mpService->validateWebhookSignature($request)) {
            Log::warning('[MP] Webhook rechazado: firma invalida', [
                'ip' => $request->ip(),
                'headers' => $request->header('x-signature'),
            ]);
            return response()->json(['message' => 'Firma invalida'], 401);
        }

        $data = $request->all();
        $type = $data['type'] ?? null;
        $externalId = $data['data']['id'] ?? null;

        if (!$type || !$externalId) {
            return response()->json(['message' => 'Payload incompleto'], 422);
        }

        // Registrar evento para idempotency
        $event = \App\Models\PaymentGatewayWebhookEvent::create([
            'gateway' => 'mercadopago',
            'event_type' => $type,
            'external_id' => (string) $externalId,
            'payload' => $data,
            'signature' => $request->header('x-signature'),
            'signature_valid' => true,
            'processed' => false,
        ]);

        // Encolar procesamiento async
        \App\Jobs\ProcessMercadoPagoWebhook::dispatch($event);

        // Responder 200 inmediatamente (MP espera confirmacion rapida)
        return response()->json(['message' => 'Recibido', 'event_id' => $event->id]);
    }
}
