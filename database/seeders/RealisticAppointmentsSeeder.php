<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\AppointmentType;
use App\Models\DentalChair;
use Carbon\Carbon;

class RealisticAppointmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener datos necesarios
        $patients = Patient::all();
        $professionals = User::whereIn('role', ['odontologo', 'implantologo', 'tecnico_dental', 'asistente'])->get();
        $appointmentTypes = AppointmentType::all();
        $dentalChairs = DentalChair::where('is_active', true)->get();
        $recepcionistas = User::whereIn('role', ['administrador', 'recepcionista'])->get();

        if ($patients->isEmpty() || $professionals->isEmpty() || $appointmentTypes->isEmpty() || $dentalChairs->isEmpty()) {
            $this->command->error('No hay suficientes datos para crear citas. Ejecute primero los seeders de usuarios, pacientes, tipos de cita y ambientes.');
            return;
        }

        $appointments = [];
        $appointmentCount = 0;

        // Distribución temporal de citas por mes
        $monthlyDistribution = [
            '2025-01' => 40,
            '2025-02' => 45,
            '2025-03' => 50,
            '2025-04' => 55,
            '2025-05' => 60,
            '2025-06' => 60,
            '2025-07' => 50,
            '2025-08' => 55,
            '2025-09' => 60,
            '2025-10' => 25, // Solo hasta día 18
        ];

        // Horarios laborales
        $workHours = [
            'monday' => ['08:00', '18:00'],
            'tuesday' => ['08:00', '18:00'],
            'wednesday' => ['08:00', '18:00'],
            'thursday' => ['08:00', '18:00'],
            'friday' => ['08:00', '18:00'],
            'saturday' => ['08:00', '13:00'],
            'sunday' => null, // No hay atención domingos
        ];

        // Crear tratamientos coherentes por paciente
        $patientTreatments = $this->createPatientTreatments($patients, $appointmentTypes);

        foreach ($monthlyDistribution as $month => $targetCount) {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $monthEnd = $month === '2025-10'
                ? Carbon::createFromFormat('Y-m', $month)->day(18)->endOfDay()
                : $monthStart->copy()->endOfMonth();

            $monthAppointments = 0;
            $usedSlots = []; // Para evitar conflictos

            while ($monthAppointments < $targetCount) {
                // Seleccionar fecha aleatoria del mes
                $randomDay = $monthStart->copy()->addDays(rand(0, $monthStart->diffInDays($monthEnd)));

                // Saltar domingos
                if ($randomDay->dayOfWeek === Carbon::SUNDAY) {
                    continue;
                }

                $dayName = strtolower($randomDay->format('l'));
                if (!$workHours[$dayName]) {
                    continue;
                }

                // Generar hora aleatoria dentro del horario laboral
                $startHour = $workHours[$dayName][0];
                $endHour = $workHours[$dayName][1];

                $startTime = Carbon::createFromTimeString($startHour);
                $endTime = Carbon::createFromTimeString($endHour);

                $randomMinutes = rand(0, $startTime->diffInMinutes($endTime));
                $appointmentTime = $startTime->copy()->addMinutes($randomMinutes);

                // Redondear a intervalos de 30 minutos
                $minutes = $appointmentTime->minute;
                if ($minutes < 15) {
                    $appointmentTime->minute(0);
                } elseif ($minutes < 45) {
                    $appointmentTime->minute(30);
                } else {
                    $appointmentTime->addHour()->minute(0);
                }

                // Verificar que no exceda el horario laboral
                if ($appointmentTime->format('H:i') > $endHour) {
                    continue;
                }

                $scheduledAt = $randomDay->copy()->setTimeFromTimeString($appointmentTime->format('H:i:s'));

                // Crear clave única para el slot
                $slotKey = $scheduledAt->format('Y-m-d H:i');
                if (isset($usedSlots[$slotKey])) {
                    continue;
                }

                // Seleccionar paciente y tratamiento
                $patientTreatment = $patientTreatments[array_rand($patientTreatments)];
                $patient = $patientTreatment['patient'];
                $treatmentPlan = $patientTreatment['treatment_plan'];

                // Seleccionar cita del plan de tratamiento
                $appointmentData = $treatmentPlan[array_rand($treatmentPlan)];

                // Verificar si la cita es coherente con la fecha
                if (!$this->isAppointmentDateValid($appointmentData, $scheduledAt, $month)) {
                    continue;
                }

                // Seleccionar profesional según especialidad
                $professional = $this->selectProfessionalForTreatment($appointmentData['type'], $professionals);
                if (!$professional) {
                    continue;
                }

                // Seleccionar ambiente disponible
                $dentalChair = $dentalChairs->random();

                // Calcular duración y hora de fin
                $appointmentType = $appointmentTypes->where('name', $appointmentData['type'])->first();
                $duration = $appointmentType ? $appointmentType->default_duration_minutes : 30;
                $endsAt = $scheduledAt->copy()->addMinutes($duration);

                // Determinar estado según antigüedad
                $status = $this->determineAppointmentStatus($scheduledAt, $month);

                // Crear cita
                $appointment = [
                    'patient_id' => $patient->id,
                    'user_id' => $professional->id,
                    'dental_chair_id' => $dentalChair->id,
                    'appointment_type_id' => $appointmentType->id,
                    'scheduled_at' => $scheduledAt,
                    'ends_at' => $endsAt,
                    'duration_minutes' => $duration,
                    'status' => $status,
                    'notes' => $appointmentData['notes'],
                    'treatment_notes' => $status === 'completed' ? $appointmentData['treatment_notes'] : null,
                    'created_by' => $recepcionistas->random()->id,
                    'updated_by' => $recepcionistas->random()->id,
                    'confirmation_token' => $appointmentType->requires_confirmation ? $this->generateToken() : null,
                    'confirmed_at' => in_array($status, ['confirmed', 'completed']) ? $scheduledAt->copy()->subDays(rand(1, 3)) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $appointments[] = $appointment;
                $usedSlots[$slotKey] = true;
                $monthAppointments++;
                $appointmentCount++;

                // Limitar el total de citas
                if ($appointmentCount >= 500) {
                    break 2;
                }
            }
        }

        // Insertar citas en lotes
        $chunks = array_chunk($appointments, 100);
        foreach ($chunks as $chunk) {
            Appointment::insert($chunk);
        }

        $this->command->info("{$appointmentCount} citas realistas creadas exitosamente.");
    }

    private function createPatientTreatments($patients, $appointmentTypes)
    {
        $patientTreatments = [];

        foreach ($patients as $patient) {
            $treatmentPlan = [];

            // 15% ortodoncia (6-12 meses de tratamiento)
            if (rand(1, 100) <= 15) {
                $treatmentPlan = $this->createOrthodonticsPlan($appointmentTypes);
            }
            // 10% endodoncia + corona
            elseif (rand(1, 100) <= 10) {
                $treatmentPlan = $this->createEndodonticsPlan($appointmentTypes);
            }
            // 30% limpieza semestral
            elseif (rand(1, 100) <= 30) {
                $treatmentPlan = $this->createCleaningPlan($appointmentTypes);
            }
            // 30% tratamientos simples
            elseif (rand(1, 100) <= 30) {
                $treatmentPlan = $this->createSimpleTreatmentPlan($appointmentTypes);
            }
            // 15% casos mixtos
            else {
                $treatmentPlan = $this->createMixedTreatmentPlan($appointmentTypes);
            }

            $patientTreatments[] = [
                'patient' => $patient,
                'treatment_plan' => $treatmentPlan
            ];
        }

        return $patientTreatments;
    }

    private function createOrthodonticsPlan($appointmentTypes)
    {
        $plan = [];

        // Primera cita - colocación
        $plan[] = [
            'type' => 'Ortodoncia',
            'notes' => 'Consulta inicial y colocación de brackets',
            'treatment_notes' => 'Paciente con maloclusión clase II. Colocados brackets metálicos en arcada superior e inferior. Instrucciones de higiene oral.',
            'month_range' => ['2025-01', '2025-03']
        ];

        // Controles mensuales (6-10 citas)
        $controlCount = rand(6, 10);
        for ($i = 1; $i <= $controlCount; $i++) {
            $plan[] = [
                'type' => 'Ortodoncia',
                'notes' => "Control mensual #{$i} - ajuste de brackets",
                'treatment_notes' => "Ajuste realizado. Progreso satisfactorio. Cambio de elásticos. Próximo control en 4 semanas.",
                'month_range' => ['2025-04', '2025-10']
            ];
        }

        return $plan;
    }

    private function createEndodonticsPlan($appointmentTypes)
    {
        return [
            [
                'type' => 'Endodoncia',
                'notes' => 'Tratamiento de conducto - primera sesión',
                'treatment_notes' => 'Endodoncia en pieza 16. Limpieza y conformación de conductos. Medicación intracanal colocada. Cita de control en 2 semanas.',
                'month_range' => ['2025-01', '2025-06']
            ],
            [
                'type' => 'Corona',
                'notes' => 'Preparación para corona - segunda sesión',
                'treatment_notes' => 'Preparación de pieza 16 para corona. Impresión tomada. Corona temporal colocada. Próxima cita en 1 semana.',
                'month_range' => ['2025-01', '2025-08']
            ],
            [
                'type' => 'Corona',
                'notes' => 'Colocación de corona definitiva',
                'treatment_notes' => 'Corona de porcelana colocada en pieza 16. Ajuste oclusal realizado. Paciente satisfecho con el resultado.',
                'month_range' => ['2025-02', '2025-09']
            ]
        ];
    }

    private function createCleaningPlan($appointmentTypes)
    {
        return [
            [
                'type' => 'Limpieza Dental',
                'notes' => 'Profilaxis dental semestral',
                'treatment_notes' => 'Limpieza dental completa realizada. Remoción de sarro y placa. Aplicación de flúor. Higiene oral en buen estado.',
                'month_range' => ['2025-01', '2025-10']
            ],
            [
                'type' => 'Limpieza Dental',
                'notes' => 'Control de limpieza - 6 meses después',
                'treatment_notes' => 'Control de higiene oral. Mantenimiento de profilaxis. Instrucciones de cepillado reforzadas.',
                'month_range' => ['2025-07', '2025-10']
            ]
        ];
    }

    private function createSimpleTreatmentPlan($appointmentTypes)
    {
        $treatments = ['Empaste', 'Extracción', 'Consulta General'];
        $selectedTreatment = $treatments[array_rand($treatments)];

        $plan = [
            [
                'type' => $selectedTreatment,
                'notes' => "Tratamiento: {$selectedTreatment}",
                'treatment_notes' => $this->getTreatmentNotes($selectedTreatment),
                'month_range' => ['2025-01', '2025-10']
            ]
        ];

        // 30% de probabilidad de segunda cita
        if (rand(1, 100) <= 30) {
            $plan[] = [
                'type' => 'Consulta General',
                'notes' => 'Control post-tratamiento',
                'treatment_notes' => 'Control de evolución. Tratamiento exitoso. Paciente sin molestias.',
                'month_range' => ['2025-02', '2025-10']
            ];
        }

        return $plan;
    }

    private function createMixedTreatmentPlan($appointmentTypes)
    {
        $treatments = ['Empaste', 'Extracción', 'Consulta General', 'Limpieza Dental'];
        $selectedTreatments = array_rand($treatments, rand(2, 3));

        $plan = [];
        foreach ($selectedTreatments as $index) {
            $treatment = $treatments[$index];
            $plan[] = [
                'type' => $treatment,
                'notes' => "Tratamiento mixto: {$treatment}",
                'treatment_notes' => $this->getTreatmentNotes($treatment),
                'month_range' => ['2025-01', '2025-10']
            ];
        }

        return $plan;
    }

    private function getTreatmentNotes($treatment)
    {
        $notes = [
            'Empaste' => 'Restauración con resina compuesta en pieza afectada. Aislamiento absoluto. Procedimiento exitoso.',
            'Extracción' => 'Extracción de pieza dental realizada sin complicaciones. Hemostasia lograda. Instrucciones post-operatorias dadas.',
            'Consulta General' => 'Consulta de rutina. Examen clínico completo. Estado de salud bucal estable.',
            'Limpieza Dental' => 'Profilaxis dental completa. Remoción de cálculo y placa bacteriana. Aplicación de flúor tópico.'
        ];

        return $notes[$treatment] ?? 'Tratamiento realizado exitosamente.';
    }

    private function selectProfessionalForTreatment($treatmentType, $professionals)
    {
        // Mapear tratamientos a especialidades
        $specialtyMap = [
            'Ortodoncia' => 'orthodontics',
            'Endodoncia' => 'endodontics',
            'Corona' => 'prosthodontics',
            'Cirugía Oral' => 'oral_surgery',
            'Empaste' => 'general',
            'Extracción' => 'general',
            'Consulta General' => 'general',
            'Limpieza Dental' => 'general'
        ];

        $requiredSpecialty = $specialtyMap[$treatmentType] ?? 'general';

        // Buscar profesional con la especialidad requerida
        $specialist = $professionals->where('specialty', $requiredSpecialty)->first();

        // Si no hay especialista, usar general
        return $specialist ?: $professionals->where('specialty', 'general')->first();
    }

    private function isAppointmentDateValid($appointmentData, $scheduledAt, $currentMonth)
    {
        $monthRange = $appointmentData['month_range'];
        $scheduledMonth = $scheduledAt->format('Y-m');

        return $scheduledMonth >= $monthRange[0] && $scheduledMonth <= $monthRange[1];
    }

    private function determineAppointmentStatus($scheduledAt, $currentMonth)
    {
        $now = now();

        if ($scheduledAt->isPast()) {
            // Citas pasadas: 70% completadas, 20% canceladas, 10% no show
            $rand = rand(1, 100);
            if ($rand <= 70) return 'completed';
            if ($rand <= 90) return 'cancelled';
            return 'no_show';
        } elseif ($scheduledAt->isFuture()) {
            // Citas futuras: 80% confirmadas, 20% programadas
            return rand(1, 100) <= 80 ? 'confirmed' : 'scheduled';
        } else {
            // Citas de hoy
            return 'confirmed';
        }
    }

    private function generateToken()
    {
        return bin2hex(random_bytes(32));
    }
}



































