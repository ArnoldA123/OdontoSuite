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

            // 2. Configuracion base (sucursales y metodos de pago)
            BranchSeeder::class,           // Sprint 0 (B-CASH-1): 3 sedes
            PaymentMethodSeeder::class,    // Sprint 0 (B-CASH-2): 5 metodos del sistema

            // 3. Configuracion clinica
            SpecialtySeeder::class,
            AppointmentTypeSeeder::class,
            EnvironmentSeeder::class,
            ProcedureCatalogSeeder::class, // depende de SpecialtySeeder

            // 4. Pacientes
            PatientSeeder::class, // 100 pacientes

            // 5. Citas y recordatorios
            SimpleAppointmentsSeeder::class, // 100 citas
            ReminderSchedulesSeeder::class,

            // 6. Sistema de caja
            CashRegisterSeeder::class,

            // 7. Citas completadas con pagos pendientes
            CompletedAppointmentsSeeder::class,

            // 8. Registros de especialidades
            SpecialtyRecordSeeder::class,
        ]);
    }
}
