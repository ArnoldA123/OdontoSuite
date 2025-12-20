<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CashRegisterSession;
use App\Models\Transaction;
use App\Models\CashMovement;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Branch;
use Carbon\Carbon;

class CashRegisterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener datos necesarios
        $users = User::all();
        $branches = Branch::all();
        $patients = Patient::all();
        $paymentMethods = PaymentMethod::all();

        if ($users->isEmpty() || $branches->isEmpty() || $patients->isEmpty() || $paymentMethods->isEmpty()) {
            $this->command->warn('No hay suficientes datos para crear sesiones de caja. Ejecute otros seeders primero.');
            return;
        }

        // Crear sesiones de caja de los últimos 30 días
        for ($i = 0; $i < 30; $i++) {
            $date = Carbon::now()->subDays($i);
            $user = $users->random();
            $branch = $branches->random();

            // Crear sesión de caja
            $session = CashRegisterSession::create([
                'user_id' => $user->id,
                'branch_id' => $branch->id,
                'opening_amount' => rand(50, 200),
                'opened_at' => $date->copy()->setTime(8, 0),
                'opening_notes' => 'Apertura de caja - ' . $date->format('d/m/Y'),
                'status' => $i < 5 ? 'open' : 'closed'
            ]);

            // Crear transacciones para la sesión
            $transactions = $this->createTransactionsForSession($session, $patients, $paymentMethods);

            if ($session->status === 'closed') {
                // Calcular montos para sesión cerrada
                $totalIncome = $transactions->where('type', 'income')->sum('amount');
                $totalExpenses = $transactions->where('type', 'expense')->sum('amount');
                $expectedAmount = $session->opening_amount + $totalIncome - $totalExpenses;
                $closingAmount = $expectedAmount + rand(-10, 10); // Pequeña diferencia aleatoria

                $session->update([
                    'closing_amount' => $closingAmount,
                    'expected_amount' => $expectedAmount,
                    'difference_amount' => $closingAmount - $expectedAmount,
                    'closed_at' => $date->copy()->setTime(18, 0),
                    'closing_notes' => 'Cierre de caja - ' . $date->format('d/m/Y')
                ]);
            }

            // Crear movimientos después de actualizar la sesión
            $this->createMovementsForSession($session);
        }

        $this->command->info('Sesiones de caja creadas exitosamente.');
    }

    /**
     * Crear transacciones para una sesión
     */
    private function createTransactionsForSession($session, $patients, $paymentMethods)
    {
        $transactions = collect();
        $transactionCount = rand(5, 20);

        for ($i = 0; $i < $transactionCount; $i++) {
            $patient = $patients->random();
            $paymentMethod = $paymentMethods->random();
            $amount = rand(50, 500);
            $type = rand(0, 9) < 8 ? 'income' : 'expense'; // 80% ingresos, 20% egresos

            $transaction = Transaction::create([
                'patient_id' => $patient->id,
                'appointment_id' => null,
                'treatment_plan_id' => null,
                'payment_method_id' => $paymentMethod->id,
                'cash_register_session_id' => $session->id,
                'created_by' => $session->user_id,
                'transaction_number' => 'TXN-' . $session->opened_at->format('Ymd') . '-' . str_pad($session->id . $i + 1, 4, '0', STR_PAD_LEFT),
                'type' => $type,
                'amount' => $amount,
                'subtotal' => $amount,
                'discount_amount' => rand(0, 1) ? rand(5, 20) : 0,
                'discount_type' => rand(0, 1) ? 'percentage' : 'fixed',
                'description' => $type === 'income' ?
                    'Pago de consulta - ' . $patient->name . ' ' . $patient->last_name :
                    'Gasto operativo - ' . $this->getRandomExpenseDescription(),
                'notes' => rand(0, 1) ? 'Transacción de prueba' : null,
                'reference_number' => $paymentMethod->name === 'Tarjeta' ? 'TXN' . rand(100000, 999999) : null,
                'status' => 'completed',
                'processed_at' => $session->opened_at->copy()->addHours(rand(1, 8))->addMinutes(rand(0, 59)),
                'created_at' => $session->opened_at->copy()->addHours(rand(1, 8))->addMinutes(rand(0, 59))
            ]);

            $transactions->push($transaction);
        }

        return $transactions;
    }

    /**
     * Crear movimientos de caja para una sesión
     */
    private function createMovementsForSession($session)
    {
        $movements = collect();

        // Movimiento de apertura
        CashMovement::create([
            'cash_register_session_id' => $session->id,
            'created_by' => $session->user_id,
            'type' => 'deposit',
            'amount' => $session->opening_amount,
            'description' => 'Apertura de caja',
            'notes' => $session->opening_notes,
            'reference' => 'OPEN-' . $session->id,
            'created_at' => $session->opened_at
        ]);

        // Movimientos adicionales
        $movementCount = rand(2, 8);
        for ($i = 0; $i < $movementCount; $i++) {
            $type = rand(0, 1) ? 'income' : 'expense';
            $amount = rand(20, 100);

            CashMovement::create([
                'cash_register_session_id' => $session->id,
                'created_by' => $session->user_id,
                'type' => $type,
                'amount' => $amount,
                'description' => $type === 'income' ?
                    'Ingreso adicional - ' . $this->getRandomIncomeDescription() :
                    'Egreso - ' . $this->getRandomExpenseDescription(),
                'notes' => 'Movimiento de prueba',
                'reference' => 'MOV-' . $session->id . '-' . ($i + 1),
                'created_at' => $session->opened_at->copy()->addHours(rand(1, 8))->addMinutes(rand(0, 59))
            ]);
        }

        // Movimiento de cierre (si está cerrada)
        if ($session->status === 'closed') {
            CashMovement::create([
                'cash_register_session_id' => $session->id,
                'created_by' => $session->user_id,
                'type' => 'withdrawal',
                'amount' => $session->closing_amount,
                'description' => 'Cierre de caja',
                'notes' => $session->closing_notes,
                'reference' => 'CLOSE-' . $session->id,
                'created_at' => $session->closed_at
            ]);
        }

        return $movements;
    }

    /**
     * Obtener descripción aleatoria de ingreso
     */
    private function getRandomIncomeDescription()
    {
        $descriptions = [
            'Venta de productos',
            'Servicio adicional',
            'Pago de cuota',
            'Reembolso de paciente',
            'Ingreso por comisión'
        ];

        return $descriptions[array_rand($descriptions)];
    }

    /**
     * Obtener descripción aleatoria de egreso
     */
    private function getRandomExpenseDescription()
    {
        $descriptions = [
            'Compra de materiales',
            'Gasto de mantenimiento',
            'Pago de servicios',
            'Retiro de efectivo',
            'Gasto administrativo'
        ];

        return $descriptions[array_rand($descriptions)];
    }
}

