<?php

namespace Database\Seeders;

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
            // 1. Catalogo de sedes. Va antes de los usuarios porque cada
            //    usuario sembrado se afilia a la sede de prueba (T3).
            BranchSeeder::class,           // Sprint 0 (B-CASH-1): 4 sedes

            // 2. Usuarios y roles
            RoleBasedUsersSeeder::class,

            // 3. Configuracion base (metodos de pago; las sedes ya estan)
            PaymentMethodSeeder::class,    // Sprint 0 (B-CASH-2): 5 metodos del sistema

            // 4. Configuracion clinica
            SpecialtySeeder::class,
            UserSpecialtySeeder::class, // Issue #211: pivote desde specialty legacy
            DiagnosisCatalogSeeder::class, // F6 (#286): codigos CIE-10 del capitulo odontologico
            AppointmentTypeSeeder::class,
            EnvironmentSeeder::class,
            ProcedureCatalogSeeder::class, // depende de SpecialtySeeder

            // 5. Pacientes
            PatientSeeder::class, // 100 pacientes

            // 6. Citas y recordatorios
            SimpleAppointmentsSeeder::class, // 100 citas
            ReminderSchedulesSeeder::class,

            // 7. Sistema de caja
            CashRegisterSeeder::class,

            // 8. Citas completadas con pagos pendientes
            CompletedAppointmentsSeeder::class,

            // 8b. Piezas dentales (FK previa para endodoncia / rehabilitación /
            //     cirugía oral / implantología). Idempotente (32 FDI permanentes).
            DentalPieceSeeder::class,

            // 8c. Superficies de cada pieza activa (#293 S1/S2): un
            //     procedimiento de superficie exige su superficie. Idempotente
            //     (5 por pieza).
            ToothSurfaceSeeder::class,

            // 9. Registros de especialidades
            SpecialtyRecordSeeder::class,

            // 10. Muestra clínica del instrumento de auditoría (#374):
            //    `/medical-records/:id` no tenía fila que medir sobre una base
            //    sembrada a secas. Idempotente (2 historias con su evolución).
            MedicalRecordSeeder::class,
        ]);
    }
}
