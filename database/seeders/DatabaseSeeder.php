<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. Usuarios y roles
            RoleBasedUsersSeeder::class,

            // 2. Configuración
            AppointmentTypeSeeder::class,
            EnvironmentSeeder::class,

            // 3. Pacientes
            PatientSeeder::class, // 100 pacientes

            // 4. Citas y recordatorios
            SimpleAppointmentsSeeder::class, // 100 citas
            ReminderSchedulesSeeder::class,

            // 5. Sistema de caja
            CashRegisterSeeder::class,

            // 6. Citas completadas con pagos pendientes
            CompletedAppointmentsSeeder::class,

            // 7. Registros de especialidades
            SpecialtyRecordSeeder::class,
        ]);
    }
}
