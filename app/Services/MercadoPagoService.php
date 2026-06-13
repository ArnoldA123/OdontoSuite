<?php

namespace App\Services;

use App\Models\PaymentGatewayTransaction;
use App\Models\Transaction;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 3 (plan #11): servicio para integracion con Mercado Pago.
 *
 * Encapsula el SDK mercadopago/dx-php v3.10 y provee metodos para:
 * - createPreference: crea una preferencia de pago y devuelve
 *   preference_id + public_key que el frontend usa para montar Bricks.
 * - getPayment: consulta el estado de un pago en MP.
 * - validateWebhookSignature: valida la firma HMAC-SHA256 del webhook
 *   de MP contra el secret access_token.
 */
class MercadoPagoService
{
    private string $accessToken;
    private string $publicKey;
    private string $environment;

    public function __construct()
    {
        $this->environment = config('mercadopago.environment', 'sandbox');
        $creds = $this->environment === 'production'
            ? config('mercadopago.production')
            : config('mercadopago.test');

        $this->accessToken = $creds['access_token'] ?? '';
        $this->publicKey = $creds['public_key'] ?? '';

        if (empty($this->accessToken) || empty($this->publicKey)) {
            Log::warning('[MP] Credenciales no configuradas', [
                'env' => $this->environment,
            ]);
        }

        MercadoPagoConfig::setAccessToken($this->accessToken);
        // Entorno: https://api.mercadopago.com para prod, https://api.mercadopago.com para sandbox tambien
        if ($this->environment === 'sandbox') {
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        }
    }

    /**
     * Crear una preferencia de pago para una transaccion local.
     * Devuelve array con preference_id, public_key e init_point.
     */
    public function createPreference(Transaction $transaction, string $backUrl, string $failureUrl, string $pendingUrl): array
    {
        try {
            $client = new PreferenceClient();

            $payerName = $transaction->patient?->full_name ?? 'Paciente';
            $payerEmail = $transaction->patient?->email ?? 'paciente@odontosuite.pe';

            $preferenceData = [
                'items' => [
                    [
                        'id' => (string) $transaction->id,
                        'title' => $transaction->description ?? 'Pago OdontoSuite',
                        'description' => $transaction->description ?? '',
                        'quantity' => 1,
                        'currency_id' => 'PEN',
                        'unit_price' => (float) $transaction->amount,
                    ],
                ],
                'payer' => [
                    'name' => $payerName,
                    'email' => $payerEmail,
                ],
                'external_reference' => (string) $transaction->id,
                'back_urls' => [
                    'success' => $backUrl,
                    'failure' => $failureUrl,
                    'pending' => $pendingUrl,
                ],
                'auto_return' => 'approved',
                'notification_url' => config('mercadopago.webhook_url', '/api/payments/webhooks/mercadopago'),
                'statement_descriptor' => config('mercadopago.site_name', 'OdontoSuite'),
            ];

            $preference = $client->create($preferenceData);

            $result = [
                'id' => $preference->id,
                'public_key' => $this->publicKey,
                'init_point' => $preference->init_point ?? ($this->environment === 'sandbox' ? $preference->sandbox_init_point : null),
                'sandbox_init_point' => $preference->sandbox_init_point ?? null,
            ];

            // Registrar la transaccion gateway
            PaymentGatewayTransaction::create([
                'transaction_id' => $transaction->id,
                'gateway' => 'mercadopago',
                'external_id' => $preference->id,
                'external_status' => 'pending',
                'amount' => $transaction->amount,
                'currency' => 'PEN',
                'payer_email' => $payerEmail,
            ]);

            Log::info('[MP] Preferencia creada', [
                'transaction_id' => $transaction->id,
                'preference_id' => $preference->id,
            ]);

            return $result;
        } catch (MPApiException $e) {
            Log::error('[MP] Error API al crear preferencia', [
                'transaction_id' => $transaction->id ?? null,
                'error' => $e->getMessage(),
                'api_response' => $e->getApiResponse()?->getContent(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener estado de un pago por su payment_id.
     */
    public function getPayment(string $paymentId): ?array
    {
        try {
            $client = new PaymentClient();
            $payment = $client->get($paymentId);

            return [
                'id' => $payment->id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail ?? null,
                'external_reference' => $payment->external_reference ?? null,
                'payer_email' => $payment->payer?->email ?? null,
                'payment_method_id' => $payment->payment_method_id ?? null,
                'transaction_amount' => $payment->transaction_amount ?? null,
            ];
        } catch (MPApiException $e) {
            Log::warning('[MP] Error al consultar pago', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validar firma del webhook usando el schema HMAC-SHA256 de MP.
     *
     * MP firma con x-signature header que contiene:
     *   ts=<timestamp>,v1=<hmac>
     * El HMAC se calcula con: HMAC-SHA256(secret, "id:<data.id>;...")
     */
    public function validateWebhookSignature(Request $request): bool
    {
        $signatureHeader = $request->header('x-signature');
        if (empty($signatureHeader)) {
            Log::warning('[MP] Webhook sin x-signature header');
            return false;
        }

        // Parsear: "ts=123,v1=abc"
        $parts = [];
        foreach (explode(',', $signatureHeader) as $pair) {
            $kv = explode('=', $pair, 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }

        $ts = $parts['ts'] ?? null;
        $hash = $parts['v1'] ?? null;

        if (empty($ts) || empty($hash)) {
            Log::warning('[MP] x-signature formato invalido');
            return false;
        }

        // El manifest es: "id:<data.id>;request-id:<x-request-id>;ts:<timestamp>"
        $dataId = $request->input('data.id', '');
        $requestId = $request->header('x-request-id', '');
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts}";

        // El secret usado para firmar es el access_token
        $expectedHash = hash_hmac('sha256', $manifest, $this->accessToken);

        $valid = hash_equals($expectedHash, $hash);

        if (!$valid) {
            Log::warning('[MP] Firma webhook invalida', [
                'expected' => $expectedHash,
                'received' => $hash,
            ]);
        }

        return $valid;
    }

    /**
     * Verificar que las credenciales configuradas son validas
     * haciendo un ping a la API de MP.
     */
    public function validateCredentials(): bool
    {
        if (empty($this->accessToken)) {
            return false;
        }

        try {
            $client = new PaymentClient();
            // Obtener el primer pago (page 1, limit 1) para verificar que
            // las credenciales son validas
            $client->search(1, 1);
            return true;
        } catch (\Exception $e) {
            Log::warning('[MP] Credenciales invalidas', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
