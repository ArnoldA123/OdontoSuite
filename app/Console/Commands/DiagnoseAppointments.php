<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use Illuminate\Console\Command;
use Carbon\Carbon;

class DiagnoseAppointments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:diagnose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose appointments in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Diagnóstico de Citas ===');
        $this->newLine();

        // Total de citas
        $totalAppointments = Appointment::count();
        $this->info("Total de citas en la base de datos: {$totalAppointments}");
        $this->newLine();

        if ($totalAppointments === 0) {
            $this->warn('⚠️  No hay citas en la base de datos.');
            $this->info('Ejecuta: php artisan db:seed --class=AppointmentSeeder');
            return 0;
        }

        // Citas con fechas válidas
        $validDateAppointments = Appointment::whereNotNull('scheduled_at')
            ->whereNotNull('ends_at')
            ->count();
        $this->info("Citas con fechas válidas: {$validDateAppointments}");

        // Citas con fechas inválidas
        $invalidDateAppointments = Appointment::whereNull('scheduled_at')
            ->orWhereNull('ends_at')
            ->count();
        if ($invalidDateAppointments > 0) {
            $this->warn("⚠️  Citas con fechas inválidas: {$invalidDateAppointments}");
        }

        $this->newLine();

        // Verificar relaciones
        $this->info('=== Verificación de Relaciones ===');
        
        $appointmentsWithoutPatient = Appointment::whereDoesntHave('patient')->count();
        if ($appointmentsWithoutPatient > 0) {
            $this->warn("⚠️  Citas sin paciente: {$appointmentsWithoutPatient}");
        }

        $appointmentsWithoutUser = Appointment::whereDoesntHave('user')->count();
        if ($appointmentsWithoutUser > 0) {
            $this->warn("⚠️  Citas sin profesional: {$appointmentsWithoutUser}");
        }

        $appointmentsWithoutType = Appointment::whereDoesntHave('appointmentType')->count();
        if ($appointmentsWithoutType > 0) {
            $this->warn("⚠️  Citas sin tipo: {$appointmentsWithoutType}");
        }

        $appointmentsWithoutChair = Appointment::whereDoesntHave('dentalChair')->count();
        if ($appointmentsWithoutChair > 0) {
            $this->warn("⚠️  Citas sin sillón: {$appointmentsWithoutChair}");
        }

        $this->newLine();

        // Citas por rango de fechas
        $this->info('=== Citas por Rango de Fechas ===');
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $tomorrow = $now->copy()->addDay()->startOfDay();
        $nextWeek = $now->copy()->addWeek()->startOfDay();

        $pastAppointments = Appointment::where('scheduled_at', '<', $today)->count();
        $todayAppointments = Appointment::whereBetween('scheduled_at', [$today, $tomorrow])->count();
        $futureAppointments = Appointment::where('scheduled_at', '>=', $tomorrow)->count();

        $this->info("Citas pasadas: {$pastAppointments}");
        $this->info("Citas de hoy: {$todayAppointments}");
        $this->info("Citas futuras: {$futureAppointments}");

        $this->newLine();

        // Muestra de citas
        $this->info('=== Muestra de Citas ===');
        $sampleAppointments = Appointment::with(['patient', 'user', 'appointmentType', 'dentalChair'])
            ->orderBy('scheduled_at', 'desc')
            ->limit(5)
            ->get();

        if ($sampleAppointments->count() > 0) {
            $this->table(
                ['ID', 'Fecha', 'Paciente', 'Profesional', 'Tipo', 'Estado'],
                $sampleAppointments->map(function ($apt) {
                    return [
                        $apt->id,
                        $apt->scheduled_at?->format('Y-m-d H:i'),
                        $apt->patient ? $apt->patient->first_name . ' ' . $apt->patient->last_name : 'N/A',
                        $apt->user ? $apt->user->name : 'N/A',
                        $apt->appointmentType ? $apt->appointmentType->name : 'N/A',
                        $apt->status,
                    ];
                })
            );
        }

        $this->newLine();
        $this->info('✅ Diagnóstico completado');

        return 0;
    }
}






