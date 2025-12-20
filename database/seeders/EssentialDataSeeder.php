<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder esencial para instalación inicial del sistema.
 *
 * Este seeder solo crea los datos mínimos necesarios para que el sistema funcione:
 * - Usuarios básicos (admin, recepcionista, odontólogo)
 * - Tipos de citas
 * - Ambientes/sillas dentales
 * - Métodos de pago
 * - Sucursal principal
 * - Piezas dentales
 *
 * NO incluye datos de prueba como pacientes, citas, transacciones, etc.
 *
 * Uso en PC nueva:
 * php artisan migrate
 * php artisan db:seed --class=EssentialDataSeeder
 */
class EssentialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Iniciando seeders de datos esenciales...');

        $this->call([
            // 1. Usuarios esenciales (solo admin, recepcionista, odontólogo)
            EssentialUsersSeeder::class,

            // 2. Configuración del sistema
            AppointmentTypeSeeder::class,      // Tipos de citas
            EnvironmentSeeder::class,           // Ambientes/sillas dentales
            PaymentMethodsSeeder::class,         // Métodos de pago
            BranchSeeder::class,                // Sucursal principal
            DentalPiecesSeeder::class,          // Piezas dentales (32 piezas permanentes)
        ]);

        $this->command->info('');
        $this->command->info('✓ Datos esenciales creados exitosamente.');
        $this->command->info('');
        $this->command->info('Usuarios creados:');
        $this->command->info('  - admin@odontosuite.com (password: password)');
        $this->command->info('  - recepcionista@odontosuite.com (password: password)');
        $this->command->info('  - odontologo@odontosuite.com (password: password)');
        $this->command->info('');
        $this->command->info('Para crear datos de prueba, ejecuta:');
        $this->command->info('  php artisan db:seed --class=DatabaseSeeder');
    }
}
