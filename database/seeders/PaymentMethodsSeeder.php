<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Efectivo',
                'code' => 'CASH',
                'description' => 'Pago en efectivo',
                'requires_authorization' => false,
                'allows_change' => true,
                'commission_percentage' => 0,
                'is_active' => true
            ],
            [
                'name' => 'Tarjeta de Crédito',
                'code' => 'CREDIT_CARD',
                'description' => 'Pago con tarjeta de crédito',
                'requires_authorization' => true,
                'allows_change' => false,
                'commission_percentage' => 3.5,
                'is_active' => true
            ],
            [
                'name' => 'Tarjeta de Débito',
                'code' => 'DEBIT_CARD',
                'description' => 'Pago con tarjeta de débito',
                'requires_authorization' => true,
                'allows_change' => false,
                'commission_percentage' => 2.5,
                'is_active' => true
            ],
            [
                'name' => 'Transferencia Bancaria',
                'code' => 'BANK_TRANSFER',
                'description' => 'Transferencia bancaria',
                'requires_authorization' => true,
                'allows_change' => false,
                'commission_percentage' => 1.0,
                'is_active' => true
            ],
            [
                'name' => 'Yape',
                'code' => 'YAPE',
                'description' => 'Pago con Yape',
                'requires_authorization' => true,
                'allows_change' => false,
                'commission_percentage' => 2.0,
                'is_active' => true
            ],
            [
                'name' => 'Plin',
                'code' => 'PLIN',
                'description' => 'Pago con Plin',
                'requires_authorization' => true,
                'allows_change' => false,
                'commission_percentage' => 2.0,
                'is_active' => true
            ]
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
