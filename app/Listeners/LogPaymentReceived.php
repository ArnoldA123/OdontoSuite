<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Slice 10 (T-10.3): record every external payment received (MercadoPago
 * webhook or other gateway) in the audit log. PaymentReceived previously
 * broadcast on a public channel with no listener and no consumer; this
 * listener gives it a useful side-effect and the event is also secured
 * with a PrivateChannel in T-10.4.
 *
 * AGENTS.md §7: the listener MUST wrap its body in try/catch + report()
 * so a failure NEVER propagates into the webhook lifecycle (otherwise
 * MercadoPago retries forever).
 */
class LogPaymentReceived
{
    public function handle(PaymentReceived $event): void
    {
        try {
            $transaction = $event->transaction;
            $user = Auth::user();

            // No Auth user when invoked from a webhook job; we still log
            // with user_id = null so the audit trail exists.
            AuditLog::log(
                $user,
                'payment_received',
                $transaction,
                [],
                [
                    'gateway' => $event->gateway,
                    'external_status' => $event->externalStatus,
                    'amount' => (float) $transaction->amount,
                    'description' => $transaction->description,
                ]
            );

            Log::info('PaymentReceived recorded', [
                'transaction_id' => $transaction->id,
                'gateway' => $event->gateway,
                'external_status' => $event->externalStatus,
            ]);
        } catch (Throwable $e) {
            // AGENTS.md §7: swallow + log + report. Listener must NEVER
            // crash the webhook lifecycle.
            Log::error('LogPaymentReceived failed: ' . $e->getMessage(), [
                'transaction_id' => $event->transaction->id ?? null,
                'gateway' => $event->gateway,
                'exception' => $e,
            ]);
            report($e);
        }
    }
}
