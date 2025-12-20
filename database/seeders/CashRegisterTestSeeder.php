<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CashRegisterSession;
use App\Models\User;
use App\Models\Branch;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use App\Models\Patient;
use App\Models\Appointment;

class CashRegisterTestSeeder extends Seeder
{
    public function run()
    {
        // Crear usuario si no existe
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Usuario Prueba',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'role' => 'finanzas',
                'is_active' => true
            ]);
        }

        // Crear sucursal si no existe
        $branch = Branch::first();
        if (!$branch) {
            $branch = Branch::create([
                'name' => 'Sucursal Principal',
                'code' => 'SUC-001',
                'address' => 'Dirección de prueba',
                'city' => 'Lima',
                'phone' => '123456789',
                'is_active' => true
            ]);
        }

        // Crear método de pago si no existe
        $paymentMethod = PaymentMethod::first();
        if (!$paymentMethod) {
            $paymentMethod = PaymentMethod::create([
                'name' => 'Efectivo',
                'code' => 'CASH',
                'is_active' => true
            ]);
        }

        // Crear paciente si no existe
        $patient = Patient::first();
        if (!$patient) {
            $patient = Patient::create([
                'first_name' => 'Juan',
                'last_name' => 'Pérez',
                'email' => 'juan@example.com',
                'phone' => '987654321',
                'document_number' => 'DOC-001',
                'birth_date' => '1990-01-01',
                'is_active' => true
            ]);
        }

        // Crear sesión de caja abierta
        $session = CashRegisterSession::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'opening_amount' => 100.00,
            'opening_notes' => 'Sesión de prueba',
            'status' => 'open',
            'opened_at' => now()
        ]);

        // Crear transacciones de prueba
        Transaction::create([
            'cash_register_session_id' => $session->id,
            'patient_id' => $patient->id,
            'appointment_id' => null,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 50.00,
            'description' => 'Consulta dental',
            'reference_number' => 'TXN-001',
            'transaction_number' => 'TXN-001',
            'type' => 'income',
            'status' => 'completed',
            'created_by' => $user->id,
            'created_at' => now()
        ]);

        Transaction::create([
            'cash_register_session_id' => $session->id,
            'patient_id' => $patient->id,
            'appointment_id' => null,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 75.00,
            'description' => 'Limpieza dental',
            'reference_number' => 'TXN-002',
            'transaction_number' => 'TXN-002',
            'type' => 'income',
            'status' => 'completed',
            'created_by' => $user->id,
            'created_at' => now()
        ]);

        echo "Datos de prueba creados exitosamente:\n";
        echo "- Usuario: {$user->name}\n";
        echo "- Sucursal: {$branch->name}\n";
        echo "- Sesión de caja: {$session->id}\n";
        echo "- Transacciones: 2\n";
    }
}
