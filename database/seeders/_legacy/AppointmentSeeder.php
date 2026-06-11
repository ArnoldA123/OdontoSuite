<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar que existan los datos necesarios
        $patients = Patient::where('is_active', true)->get();
        $professionals = User::whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental', 'asistente'])
            ->where('is_active', true)
            ->get();
        $appointmentTypes = AppointmentType::where('is_active', true)->get();
        $dentalChairs = DentalChair::where('is_active', true)->get();
        $recepcionistas = User::whereIn('role', ['administrador', 'recepcionista'])
            ->where('is_active', true)
            ->get();

        if ($patients->isEmpty()) {
            $this->command->warn('⚠️  No hay pacientes activos. Ejecuta PatientSeeder primero.');
            return;
        }

        if ($professionals->isEmpty()) {
            $this->command->warn('⚠️  No hay profesionales activos. Ejecuta RoleBasedUsersSeeder primero.');
            return;
        }

        if ($appointmentTypes->isEmpty()) {
            $this->command->warn('⚠️  No hay tipos de cita activos. Ejecuta AppointmentTypeSeeder primero.');
            return;
        }

        if ($dentalChairs->isEmpty()) {
            $this->command->warn('⚠️  No hay sillones dentales activos. Ejecuta EnvironmentSeeder primero.');
            return;
        }

        if ($recepcionistas->isEmpty()) {
            $this->command->warn('⚠️  No hay recepcionistas. Usando el primer usuario disponible.');
            $recepcionistas = User::where('is_active', true)->limit(1)->get();
        }

        $this->command->info('Creando citas de prueba...');

        $now = Carbon::now();
        $createdCount = 0;

        // Crear citas para hoy (algunas pasadas, algunas futuras)
        $today = $now->copy()->startOfDay();
        for ($i = 0; $i < 5; $i++) {
            $hour = 8 + ($i * 2); // 8, 10, 12, 14, 16
            $scheduledAt = $today->copy()->setTime($hour, 0);
            
            // Solo crear si la hora es futura o muy reciente (últimas 2 horas)
            if ($scheduledAt->isFuture() || $scheduledAt->diffInHours($now) <= 2) {
                $appointment = $this->createAppointment(
                    $patients->random(),
                    $professionals->random(),
                    $appointmentTypes->random(),
                    $dentalChairs->random(),
                    $recepcionistas->first(),
                    $scheduledAt,
                    60
                );
                if ($appointment) {
                    $createdCount++;
                }
            }
        }

        // Crear citas para mañana
        $tomorrow = $now->copy()->addDay()->startOfDay();
        for ($i = 0; $i < 8; $i++) {
            $hour = 8 + ($i * 1.5); // Distribuidas durante el día
            $scheduledAt = $tomorrow->copy()->setTime((int)$hour, ($hour - (int)$hour) * 60);
            
            $appointment = $this->createAppointment(
                $patients->random(),
                $professionals->random(),
                $appointmentTypes->random(),
                $dentalChairs->random(),
                $recepcionistas->first(),
                $scheduledAt,
                60
            );
            if ($appointment) {
                $createdCount++;
            }
        }

        // Crear citas para esta semana
        for ($day = 2; $day <= 7; $day++) {
            $date = $now->copy()->addDays($day)->startOfDay();
            for ($i = 0; $i < 6; $i++) {
                $hour = 9 + ($i * 1.5);
                $scheduledAt = $date->copy()->setTime((int)$hour, ($hour - (int)$hour) * 60);
                
                $appointment = $this->createAppointment(
                    $patients->random(),
                    $professionals->random(),
                    $appointmentTypes->random(),
                    $dentalChairs->random(),
                    $recepcionistas->first(),
                    $scheduledAt,
                    60
                );
                if ($appointment) {
                    $createdCount++;
                }
            }
        }

        // Crear citas para el próximo mes
        $nextMonth = $now->copy()->addMonth()->startOfDay();
        for ($i = 0; $i < 10; $i++) {
            $day = rand(1, 28);
            $hour = 9 + rand(0, 7);
            $scheduledAt = $nextMonth->copy()->setDate($nextMonth->year, $nextMonth->month, $day)->setTime($hour, 0);
            
            $appointment = $this->createAppointment(
                $patients->random(),
                $professionals->random(),
                $appointmentTypes->random(),
                $dentalChairs->random(),
                $recepcionistas->first(),
                $scheduledAt,
                60
            );
            if ($appointment) {
                $createdCount++;
            }
        }

        $this->command->info("✅ {$createdCount} citas de prueba creadas exitosamente.");
    }

    /**
     * Crear una cita
     */
    private function createAppointment(
        $patient,
        $professional,
        $appointmentType,
        $dentalChair,
        $createdBy,
        Carbon $scheduledAt,
        int $durationMinutes
    ): ?Appointment {
        try {
            $endsAt = $scheduledAt->copy()->addMinutes($durationMinutes);

            // Verificar que no haya conflicto
            $conflict = Appointment::where(function($q) use ($professional, $dentalChair, $scheduledAt, $endsAt) {
                $q->where(function($subQ) use ($professional, $scheduledAt, $endsAt) {
                    $subQ->where('user_id', $professional->id)
                         ->whereBetween('scheduled_at', [$scheduledAt, $endsAt])
                         ->where('status', '!=', 'cancelled');
                })->orWhere(function($subQ) use ($dentalChair, $scheduledAt, $endsAt) {
                    $subQ->where('dental_chair_id', $dentalChair->id)
                         ->whereBetween('scheduled_at', [$scheduledAt, $endsAt])
                         ->where('status', '!=', 'cancelled');
                });
            })->exists();

            if ($conflict) {
                return null; // Saltar esta cita si hay conflicto
            }

            return Appointment::create([
                'patient_id' => $patient->id,
                'user_id' => $professional->id,
                'dental_chair_id' => $dentalChair->id,
                'appointment_type_id' => $appointmentType->id,
                'scheduled_at' => $scheduledAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $durationMinutes,
                'status' => $scheduledAt->isPast() ? 'completed' : 'scheduled',
                'notes' => 'Cita de prueba generada automáticamente',
                'created_by' => $createdBy->id,
                'updated_by' => $createdBy->id,
            ]);
        } catch (\Exception $e) {
            $this->command->warn("Error creando cita: {$e->getMessage()}");
            return null;
        }
    }
}







