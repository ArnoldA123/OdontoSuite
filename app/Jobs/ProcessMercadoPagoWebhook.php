<?php

namespace App\Jobs;

use App\Events\PaymentReceived;
use App\Models\PaymentGatewayWebhookEvent;
use App\Models\PaymentGatewayTransaction;
use App\Models\Transaction;
use App\Services\MercadoPagoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMercadoPagoWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        public PaymentGatewayWebhookEvent $event
    ) {}

    public function handle(MercadoPagoService $mp): void
    {
        if ($this->event->processed) {
            Log::info('[MP] Webhook ya procesado, ignorando', ['event_id' => $this->event->id]);
            return;
        }

        Log::info('[MP] Procesando webhook', [
            'event_id' => $this->event->id,
            'type' => $this->event->event_type,
            'external_id' => $this->event->external_id,
        ]);

        // Solo nos interesan los eventos de tipo payment
        if ($this->event->event_type !== 'payment') {
            $this->markProcessed(null, 'Tipo de evento ignorado: ' . $this->event->event_type);
            return;
        }

        try {
            // Consultar el pago en MP para obtener el estado actual
            $paymentId = $this->event->external_id;
            $paymentData = $mp->getPayment($paymentId);

            if (!$paymentData) {
                $this->markProcessed(null, 'No se pudo consultar el pago en MP');
                return;
            }

            DB::transaction(function () use ($paymentData) {
                $externalRef = $paymentData['external_reference'];

                if (!$externalRef) {
                    throw new \Exception('external_reference vacio en respuesta MP');
                }

                // Buscar la transaccion local por ID
                $transaction = Transaction::find($externalRef);
                if (!$transaction) {
                    throw new \Exception("Transaccion local #{$externalRef} no encontrada");
                }

                // Actualizar el registro de gateway
                PaymentGatewayTransaction::where('transaction_id', $transaction->id)
                    ->where('gateway', 'mercadopago')
                    ->update([
                        'external_id' => $paymentData['id'],
                        'external_status' => $paymentData['status'],
                        'payer_email' => $paymentData['payer_email'],
                        'webhook_received_at' => now(),
                    ]);

                // Si el pago fue aprobado, marcar la transaccion local
                if ($paymentData['status'] === 'approved') {
                    $transaction->update(['status' => 'paid']);

                    // Disparar evento WebSocket para la UI
                    event(new PaymentReceived($transaction, 'mercadopago', 'approved'));

                    Log::info('[MP] Pago aprobado', [
                        'transaction_id' => $transaction->id,
                        'payment_id' => $paymentData['id'],
                        'amount' => $paymentData['transaction_amount'],
                    ]);
                } elseif (in_array($paymentData['status'], ['rejected', 'cancelled'])) {
                    $transaction->update(['status' => 'failed']);

                    Log::info('[MP] Pago rechazado/cancelado', [
                        'transaction_id' => $transaction->id,
                        'status' => $paymentData['status'],
                    ]);
                } else {
                    Log::info('[MP] Pago en estado pendiente', [
                        'transaction_id' => $transaction->id,
                        'status' => $paymentData['status'],
                    ]);
                }
            });

            $this->markProcessed(true, null);
        } catch (\Exception $e) {
            Log::error('[MP] Error procesando webhook', [
                'event_id' => $this->event->id,
                'error' => $e->getMessage(),
            ]);
            $this->markProcessed(false, $e->getMessage());
            throw $e; // re-lanzar para retry con backoff
        }
    }

    private function markProcessed(?bool $success, ?string $error): void
    {
        $this->event->update([
            'processed' => $success ?? false,
            'processed_at' => now(),
            'error' => $error,
        ]);
    }
}
