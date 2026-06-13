<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

/**
 * Sprint 0 (B-CASH-2): poblar tabla payment_methods con 5 metodos base
 * del sistema que toda clinica peruana usa. is_system=true evita que el
 * admin los borre (romperia transacciones historicas); is_active=true
 * los hace usables. El admin puede desactivarlos o agregar metodos
 * custom (Yape, Plin, etc.) desde la UI en Sprint 2.
 *
 * Comisiones: 0% para efectivo/transferencia (no hay intermediario),
 * 2-3.5% para tarjeta (lo que cobra el procesador). El admin puede
 * ajustar el porcentaje por metodo desde la UI.
 */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'code' => 'cash',
                'name' => 'Efectivo',
                'description' => 'Pago en efectivo en la sede.',
                'requires_authorization' => false,
                'allows_change' => true,
                'commission_percentage' => 0,
                'is_active' => true,
                'is_system' => true,
            ],
            [
                'code' => 'transfer',
                'name' => 'Transferencia bancaria',
                'description' => 'Transferencia BCP, Interbank, BBVA u otros. Requiere voucher.',
                'requires_authorization' => true,
                'allows_change' => false,
                'commission_percentage' => 0,
                'is_active' => true,
                'is_system' => true,
            ],
            [
                'code' => 'credit_card',
                'name' => 'Tarjeta de credito',
                'description' => 'Visa, Mastercard, AmEx. Acepta cuotas.',
                'requires_authorization' => false,
                'allows_change' => false,
                'commission_percentage' => 3.50,
                'is_active' => true,
                'is_system' => true,
            ],
            [
                'code' => 'debit_card',
                'name' => 'Tarjeta de debito',
                'description' => 'Visa debito, Mastercard debito.',
                'requires_authorization' => false,
                'allows_change' => false,
                'commission_percentage' => 2.00,
                'is_active' => true,
                'is_system' => true,
            ],
            [
                'code' => 'yape',
                'name' => 'Yape',
                'description' => 'Pago via Yape (BCP). Requiere codigo de operacion.',
                'requires_authorization' => true,
                'allows_change' => false,
                'commission_percentage' => 0,
                'is_active' => true,
                'is_system' => true,
            ],
        ];

        foreach ($methods as $data) {
            PaymentMethod::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
