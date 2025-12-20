<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use Carbon\Carbon;

class SimpleAppointmentsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Iniciando SimpleAppointmentsSeeder...');

        $patients = Patient::all();
        $professionals = User::whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental', 'asistente'])->get();
        $appointmentTypes = AppointmentType::all();
        $dentalChairs = DentalChair::where('is_active', true)->get();
        $admin = User::where('role', 'administrador')->first();

        if ($patients->isEmpty() || $professionals->isEmpty() || $appointmentTypes->isEmpty() || $dentalChairs->isEmpty()) {
            $this->command->error('No hay suficientes datos para crear citas.');
            return;
        }

        $appointmentCount = 0;
        $startDate = Carbon::parse('2025-01-01');

        // Crear 100 citas distribuidas en los últimos 3 meses
        for ($i = 0; $i < 100; $i++) {
            $patient = $patients->random();
            $professional = $professionals->random();
            $appointmentType = $appointmentTypes->random();
            $dentalChair = $dentalChairs->random();

            // Distribuir en los últimos 3 meses
            $daysAgo = rand(0, 90);
            $scheduledAt = $startDate->copy()->addDays($daysAgo);

            // Horario laboral (8:00 - 17:00)
            $hour = rand(8, 16);
            $minute = rand(0, 1) * 30; // 0 o 30 minutos
            $scheduledAt->setTime($hour, $minute);

            $duration = 30; // 30 minutos por defecto
            $endsAt = $scheduledAt->copy()->addMinutes($duration);

            // Determinar estado según antigüedad
            $status = 'scheduled';
            $treatmentNotes = null;

            if ($daysAgo > 60) {
                $status = 'completed';
                $treatmentNotes = 'Tratamiento completado exitosamente. Paciente colaboró bien durante el procedimiento.';
            } elseif ($daysAgo > 30) {
                $status = 'confirmed';
            } elseif (rand(0, 100) < 5) {
                $status = 'cancelled';
            }

            try {
                Appointment::create([
                    'patient_id' => $patient->id,
                    'user_id' => $professional->id,
                    'dental_chair_id' => $dentalChair->id,
                    'appointment_type_id' => $appointmentType->id,
                    'scheduled_at' => $scheduledAt,
                    'ends_at' => $endsAt,
                    'duration_minutes' => $duration,
                    'status' => $status,
                    'notes' => 'Cita para ' . $appointmentType->name,
                    'treatment_notes' => $treatmentNotes,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]);
                $appointmentCount++;
            } catch (\Exception $e) {
                $this->command->error('Error creando cita: ' . $e->getMessage());
            }
        }

        $this->command->info("{$appointmentCount} citas creadas exitosamente.");
    }
}
