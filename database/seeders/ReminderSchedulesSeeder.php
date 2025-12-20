<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ReminderSchedule;
use App\Models\Appointment;
use Carbon\Carbon;

class ReminderSchedulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener citas que requieren recordatorios
        $appointments = Appointment::whereIn('status', ['confirmed', 'completed'])
            ->get();

        if ($appointments->isEmpty()) {
            $this->command->info('No hay citas con recordatorios para crear.');
            return;
        }

        $reminders = [];

        foreach ($appointments as $appointment) {
            // Crear recordatorio 48 horas antes de la cita
            $reminderTime = Carbon::parse($appointment->scheduled_at)->subHours(48);

            // Solo crear recordatorios para citas que ya pasaron o están próximas
            if ($reminderTime->isPast() || $reminderTime->isToday()) {
                $reminder = [
                    'appointment_id' => $appointment->id,
                    'reminder_template_id' => null, // No usar template por ahora
                    'hours_before' => 48,
                    'scheduled_at' => $reminderTime,
                    'status' => $appointment->scheduled_at->isPast() ? 'sent' : 'pending',
                    'sent_at' => $appointment->scheduled_at->isPast() ? $reminderTime->copy()->addMinutes(rand(5, 30)) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $reminders[] = $reminder;
            }
        }

        // Insertar recordatorios en lotes
        if (!empty($reminders)) {
            $chunks = array_chunk($reminders, 100);
            foreach ($chunks as $chunk) {
                ReminderSchedule::insert($chunk);
            }
        }

        $this->command->info(count($reminders) . ' recordatorios creados exitosamente.');
    }

}



