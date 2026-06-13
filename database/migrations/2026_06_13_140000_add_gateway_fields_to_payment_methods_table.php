<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2 (B-CASH-2 + B-CASH-4 prep): agregar columnas para preparar
 * la integracion con pasarelas de pago (Mercado Pago en Sprint 3).
 *
 * - gateway_type: enum nullable que identifica la pasarela. Null para
 *   metodos manuales (efectivo, transferencia con voucher, etc.).
 *   Valores esperados: 'mercadopago', 'manual' (futuro: 'stripe',
 *   'izipay', 'niubiz').
 * - gateway_config: JSON nullable con credenciales de la pasarela.
 *   Se encripta en el modelo (PaymentMethod::setGatewayConfigAttribute)
 *   con Crypt::encryptString antes de persistir. El accessor
 *   getGatewayConfigAttribute desencripta al leer.
 *
 * Esto es una migration aditiva (no toca la original). Reversible
 * con migrate:rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('gateway_type', 30)->nullable()->after('description');
            $table->json('gateway_config')->nullable()->after('gateway_type');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['gateway_type', 'gateway_config']);
        });
    }
};
